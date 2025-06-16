<?php
/**
 * @filesource modules/salary/models/write.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Write;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * โมเดลสำหรับเพิ่ม/แก้ไขข้อมูลเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสมาชิกทั้งหมดสำหรับ dropdown
     *
     * @return array
     */
    public static function getMembers()
    {
        $query = static::createQuery()
            ->select('id', 'name', 'id_card')
            ->from('user U')
            ->where(['active', 1])
            ->order('name ASC')
            ->cacheOn();

        $members = [0 => '{LNG_Please select}'];
        foreach ($query->execute() as $item) {
            $members[$item->id] = $item->name.' ('.$item->id_card.')';
        }

        return $members;
    }

    /**
     * บันทึกข้อมูลเงินเดือน
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, member
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_manage_salary')) {
                try {
                    // รับค่าจากการ POST
                    $save = [
                        'member_id' => $request->post('salary_member_id')->toInt(),
                        'year' => $request->post('salary_year')->number(),
                        'month' => sprintf('%02d', $request->post('salary_month')->toInt()),
                        'basic_salary' => $request->post('salary_basic_salary')->toDouble(),
                        'allowance' => $request->post('salary_allowance')->toDouble(),
                        'overtime' => $request->post('salary_overtime')->toDouble(),
                        'bonus' => $request->post('salary_bonus')->toDouble(),
                        'deduction' => $request->post('salary_deduction')->toDouble(),
                        'social_security' => $request->post('salary_social_security')->toDouble(),
                        'tax' => $request->post('salary_tax')->toDouble(),
                        'net_salary' => $request->post('salary_net_salary')->toDouble(),
                        'remark' => $request->post('salary_remark')->textarea()
                    ];
                    // ID ของ salary record
                    $id = $request->post('salary_id')->toInt();
                    // ตรวจสอบข้อมูล
                    if (empty($id)) {
                        // ใหม่
                        if (empty($save['member_id'])) {
                            $ret['ret_salary_member_id'] = 'Please select';
                        }
                        if (empty($save['year']) || !preg_match('/^[0-9]{4}$/', $save['year'])) {
                            $ret['ret_salary_year'] = 'Please select';
                        }
                        if (empty($save['month']) || $save['month'] < 1 || $save['month'] > 12) {
                            $ret['ret_salary_month'] = 'Please select';
                        }
                    } else {
                        // แก้ไข ใช้ข้อมูลเดิม
                        $salary = $this->db()->first($this->getTableName('salary'), $id);
                        if ($salary) {
                            $save['member_id'] = $salary->member_id;
                            $save['year'] = $salary->year;
                            $save['month'] = $salary->month;
                        } else {
                            $ret['alert'] = Language::get('Unable to complete the transaction');
                        }
                    }

                    if (empty($ret)) {
                        // ใช้ Calculator สำหรับการคำนวณ (เฉพาะกรณีที่ไม่มีค่า net_salary หรือค่าเป็น 0)
                        $needsCalculation = empty($save['net_salary']) || $save['net_salary'] == 0;

                        if ($needsCalculation) {
                            $calculationData = [
                                'basic_salary' => $save['basic_salary'],
                                'allowance' => $save['allowance'],
                                'overtime' => $save['overtime'],
                                'bonus' => $save['bonus'],
                                'deduction' => $save['deduction']
                            ];

                            // ใช้ Calculator คำนวณ
                            $calculation = \Salary\Calculator\Model::calculateNetSalary($calculationData);

                            // อัปเดตค่าที่คำนวณได้
                            $save['social_security'] = $calculation['social_security'];
                            $save['tax'] = $calculation['income_tax'];
                            $save['net_salary'] = $calculation['net_salary'];
                        }

                        if (empty($id)) {
                            // เพิ่มใหม่
                            $save['id'] = $save['member_id'].sprintf('%04d', $save['year']).sprintf('%02d', $save['month']);
                            $save['create_date'] = date('Y-m-d H:i:s');

                            // ตรวจสอบการตั้งค่าการอนุมัติ
                            if (\Salary\Calculator\Model::requiresApproval()) {
                                // ต้องการการอนุมัติ - status = 0 (รออนุมัติ)
                                $save['status'] = 0;
                            } else {
                                // ไม่ต้องการการอนุมัติ - status = 1 (อนุมัติแล้ว)
                                $save['status'] = 1;
                            }

                            // ตรวจสอบว่ามีข้อมูลของเดือนนี้หรือไม่
                            $exists = static::createQuery()
                                ->from('salary')
                                ->where([
                                    ['member_id', $save['member_id']],
                                    ['month', $save['month']],
                                    ['year', $save['year']]
                                ])
                                ->first('id');

                            if ($exists) {
                                $ret['alert'] = Language::get('Salary record for this month already exists');
                            } else {
                                // บันทึกข้อมูลใหม่
                                $this->db()->insert($this->getTableName('salary'), $save);
                                // Log
                                \Index\Log\Model::add(0, 'salary', 'Save', '{LNG_Add} {LNG_Salary} ID : '.$save['id'], $login['id']);

                                // ส่งอีเมล์ถ้าไม่ต้องการการอนุมัติและเปิดการใช้งานการแจ้งเตือน
                                if ($save['status'] == 1 && \Salary\Calculator\Model::emailNotification()) {
                                    // ดึงข้อมูลพนักงาน
                                    $member = static::createQuery()
                                        ->from('user')
                                        ->where(['id', $save['member_id']])
                                        ->first('name', 'username');

                                    if ($member) {
                                        // ส่งอีเมล์แจ้งการบันทึกเงินเดือน
                                        $emailData = array_merge($save, [
                                            'name' => $member->name,
                                            'username' => $member->username
                                        ]);
                                        \Salary\Email\Model::send($emailData, 'create');
                                    }
                                }

                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                                // redirect
                                $ret['location'] = $request->getUri()->postBack('index.php', ['module' => 'salary-setup']);
                                // เคลียร์
                                $request->removeToken();
                            }
                        } else {
                            // แก้ไข
                            $this->db()->update($this->getTableName('salary'), $id, $save);
                            // Log
                            \Index\Log\Model::add(0, 'salary', 'Save', '{LNG_Edit} {LNG_Salary} ID : '.$id, $login['id']);
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            // redirect
                            $ret['location'] = $request->getUri()->postBack('index.php', ['module' => 'salary-setup']);
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
