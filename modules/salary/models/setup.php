<?php
/**
 * @filesource modules/salary/models/setup.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Setup;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * โมเดลสำหรับแสดงรายการเงินเดือนรายเดือนของทุกคน (setup.php)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query หน้าเพจ สำหรับการแสดงรายการเงินเดือนทั้งหมด
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select('S.id', 'U.name', 'S.year', 'S.month', 'S.basic_salary', 'S.deduction', 'S.social_security', 'S.tax', 'S.net_salary', 'S.status')
            ->from('salary S')
            ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
            ->where(['U.active', 1]);
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            // รับค่าจากการ POST
            $action = $request->post('action')->toString();
            if (preg_match_all('/,?([a-zA-Z0-9]+),?/', $request->post('id', '')->toString(), $match)) {
                if ($action == 'view') {
                    // ดูรายละเอียดเงินเดือน
                    $ret['modal'] = Language::trans(\Salary\Slip\View::create()->render($request, (int) $match[1][0], $login));
                } elseif (Login::checkPermission($login, 'can_manage_salary') ||
                    ($action === 'approve' && Login::checkPermission($login, 'can_approve_salary')) ||
                    ($action === 'send_email' && Login::checkPermission($login, 'can_approve_salary'))) {
                    // Model
                    $model = new \Kotchasan\Model();
                    if ($action === 'delete') {
                        // ลบรายการเงินเดือน
                        $ids = [];
                        $query = $model->db()->createQuery()
                            ->select('id')
                            ->from('salary')
                            ->where(['id', $match[1]])
                            ->toArray();
                        foreach ($query->execute() as $item) {
                            $ids[] = $item['id'];
                        }
                        // ลบข้อมูล
                        if (!empty($ids)) {
                            $model->db()->createQuery()->delete('salary', ['id', $ids])->execute();
                            // Log
                            \Index\Log\Model::add(0, 'salary', 'Delete', '{LNG_Delete} {LNG_Salary} ID : '.implode(', ', $match[1]), $login['id']);
                            // reload
                            $ret['location'] = 'reload';
                        }
                    } elseif ($action === 'recalculate') {
                        // คำนวณเงินเดือนใหม่
                        $ids = [];
                        $query = $model->db()->createQuery()
                            ->select('id', 'basic_salary', 'allowance', 'overtime', 'bonus', 'deduction')
                            ->from('salary')
                            ->where(['id', $match[1]])
                            ->toArray();

                        $updatedCount = 0;
                        foreach ($query->execute() as $item) {
                            $ids[] = $item['id'];

                            // เตรียมข้อมูลสำหรับคำนวณ
                            $salaryData = [
                                'basic_salary' => $item['basic_salary'],
                                'allowance' => $item['allowance'],
                                'overtime' => $item['overtime'],
                                'bonus' => $item['bonus'],
                                'deduction' => $item['deduction']
                            ];

                            // คำนวณเงินเดือนใหม่
                            $result = \Salary\Calculator\Model::calculateNetSalary($salaryData);

                            // อัปเดตข้อมูลในฐานข้อมูล
                            $model->db()->createQuery()
                                ->update('salary')
                                ->set([
                                    'social_security' => $result['social_security'],
                                    'tax' => $result['income_tax'],
                                    'net_salary' => $result['net_salary']
                                ])
                                ->where(['id', $item['id']])
                                ->execute();

                            $updatedCount++;
                        }

                        if ($updatedCount > 0) {
                            // Log
                            \Index\Log\Model::add(0, 'salary', 'Recalculate', '{LNG_Re-Calculate} {LNG_Salary} ID : '.implode(', ', $match[1]).' ('.$updatedCount.' items)', $login['id']);
                            // reload
                            $ret['location'] = 'reload';
                            $ret['alert'] = Language::get('Saved successfully');
                        }
                    } elseif ($action === 'approve') {
                        // อนุมัติเงินเดือน
                        $ids = [];
                        $query = $model->db()->createQuery()
                            ->select('S.id', 'S.status', 'S.member_id', 'S.year', 'S.month', 'S.basic_salary', 'S.allowance', 'S.overtime', 'S.bonus', 'S.deduction', 'S.social_security', 'S.tax', 'S.net_salary', 'S.remark', 'U.name', 'U.username')
                            ->from('salary S')
                            ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
                            ->where(['S.id', $match[1]])
                            ->toArray();

                        $approvedCount = 0;
                        $emailResults = [];
                        foreach ($query->execute() as $item) {
                            $ids[] = $item['id'];

                            // อนุมัติเฉพาะรายการที่ยังไม่ได้อนุมัติ (status = 0)
                            if ($item['status'] == 0) {
                                $model->db()->createQuery()
                                    ->update('salary')
                                    ->set(['status' => 1])
                                    ->where(['id', $item['id']])
                                    ->execute();

                                $approvedCount++;

                                // ส่งอีเมล์แจ้งการอนุมัติ
                                if (\Salary\Calculator\Model::emailNotification()) {
                                    // ส่งอีเมล์ถ้าเปิดการใช้งาน
                                    $emailResult = \Salary\Email\Model::send([
                                        'member_id' => $item['member_id'],
                                        'name' => $item['name'],
                                        'year' => $item['year'],
                                        'month' => $item['month'],
                                        'basic_salary' => $item['basic_salary'],
                                        'allowance' => $item['allowance'],
                                        'overtime' => $item['overtime'],
                                        'bonus' => $item['bonus'],
                                        'deduction' => $item['deduction'],
                                        'social_security' => $item['social_security'],
                                        'tax' => $item['tax'],
                                        'net_salary' => $item['net_salary'],
                                        'status' => 1,
                                        'remark' => $item['remark']
                                    ], 'approve');
                                    $emailResults[] = $emailResult;
                                }
                            }
                        }

                        if ($approvedCount > 0) {
                            // Log
                            \Index\Log\Model::add(0, 'salary', 'Approve', '{LNG_Approve} {LNG_Salary} ID : '.implode(', ', $match[1]).' ('.$approvedCount.' items)', $login['id']);
                            // reload
                            $ret['location'] = 'reload';
                            $emailMessage = !empty($emailResults) ? ' ('.implode(', ', array_unique($emailResults)).')' : '';
                            $ret['alert'] = Language::get('Saved successfully').$emailMessage;
                        } else {
                            $ret['alert'] = Language::get('No items to approve or all items already approved');
                        }
                    } elseif ($action === 'send_email') {
                        // ส่งอีเมล์
                        $ids = [];
                        $query = $model->db()->createQuery()
                            ->select('S.id', 'S.member_id', 'S.year', 'S.month', 'S.basic_salary', 'S.allowance', 'S.overtime', 'S.bonus', 'S.deduction', 'S.social_security', 'S.tax', 'S.net_salary', 'S.status', 'S.remark', 'U.name', 'U.username')
                            ->from('salary S')
                            ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
                            ->where(['S.id', $match[1]])
                            ->toArray();

                        $sentCount = 0;
                        $emailResults = [];
                        foreach ($query->execute() as $item) {
                            $ids[] = $item['id'];

                            // ส่งอีเมล์
                            $emailResult = \Salary\Email\Model::send([
                                'member_id' => $item['member_id'],
                                'name' => $item['name'],
                                'year' => $item['year'],
                                'month' => $item['month'],
                                'basic_salary' => $item['basic_salary'],
                                'allowance' => $item['allowance'],
                                'overtime' => $item['overtime'],
                                'bonus' => $item['bonus'],
                                'deduction' => $item['deduction'],
                                'social_security' => $item['social_security'],
                                'tax' => $item['tax'],
                                'net_salary' => $item['net_salary'],
                                'status' => $item['status'],
                                'remark' => $item['remark']
                            ], 'send_email');
                            $emailResults[] = $emailResult;
                            $sentCount++;
                        }

                        if ($sentCount > 0) {
                            // Log
                            \Index\Log\Model::add(0, 'salary', 'Send Email', '{LNG_Send Email} {LNG_Salary} ID : '.implode(', ', $match[1]).' ('.$sentCount.' items)', $login['id']);
                            $emailMessage = !empty($emailResults) ? ' ('.implode(', ', array_unique($emailResults)).')' : '';
                            $ret['alert'] = Language::get('Email sent successfully').$emailMessage;
                        }
                    }
                }
            } elseif ($action == 'export') {
                // export รายการเงินเดือน
                $params = $request->getParsedBody();
                unset($params['action']);
                unset($params['src']);
                $params['module'] = 'salary-export';
                $ret['location'] = WEB_URL.'export.php?'.http_build_query($params);
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * อ่านข้อมูลเงินเดือนรายการเดียว
     *
     * @param int $id
     *
     * @return array|null คืนค่าข้อมูลเงินเดือน หรือ null หากไม่พบ
     */
    public static function get($id)
    {
        if ($id > 0) {
            return static::createQuery()
                ->from('salary S')
                ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
                ->where(['S.id', $id])
                ->first('S.*', 'U.name', 'U.id_card');
        } else {
            return (object) [
                'id' => 0,
                'member_id' => 0,
                'name' => '',
                'id_card' => '',
                'year' => date('Y'),
                'month' => date('m'),
                'basic_salary' => 0,
                'allowance' => 0,
                'overtime' => 0,
                'bonus' => 0,
                'deduction' => 0,
                'social_security' => 0,
                'tax' => 0,
                'net_salary' => 0,
                'status' => 0,
                'remark' => ''
            ];
        }
    }

    /**
     * Export ข้อมูลเงินเดือนเป็น CSV
     *
     * @param array $params
     *
     * @return string
     */
    public static function export($params)
    {
        $where = [];
        if (!empty($params['year']) && $params['year'] != 0) {
            $where[] = ['S.year', $params['year']];
        }
        if (!empty($params['month']) && $params['month'] != 0) {
            $where[] = ['S.month', sprintf('%02d', $params['month'])];
        }

        $model = new static();
        $query = $model->db()->createQuery()
            ->select('U.name', 'S.year', 'S.month', 'S.basic_salary', 'S.allowance', 'S.overtime', 'S.bonus', 'S.deduction', 'S.social_security', 'S.tax', 'S.net_salary', 'S.status', 'S.remark')
            ->from('salary S')
            ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
            ->where($where)
            ->order('S.year DESC', 'S.month DESC', 'U.name ASC')
            ->toArray();

        $header = [
            Language::get('Name'),
            Language::get('Year'),
            Language::get('Month'),
            Language::get('Basic Salary'),
            Language::get('Allowance'),
            Language::get('Overtime'),
            Language::get('Bonus'),
            Language::get('Deduction'),
            Language::get('Social Security'),
            Language::get('Tax'),
            Language::get('Net Salary'),
            Language::get('Status'),
            Language::get('Remark')
        ];

        $data = [];
        $months = Language::get('MONTH_LONG');
        foreach ($query->execute() as $item) {
            $item['month'] = isset($months[(int) $item['month']]) ? $months[(int) $item['month']] : $item['month'];
            $item['status'] = $item['status'] == 1 ? Language::get('Approved') : Language::get('Waiting for approval');
            $data[] = [
                $item['name'],
                $item['year'],
                $item['month'],
                number_format($item['basic_salary'], 2),
                number_format($item['allowance'], 2),
                number_format($item['overtime'], 2),
                number_format($item['bonus'], 2),
                number_format($item['deduction'], 2),
                number_format($item['social_security'], 2),
                number_format($item['tax'], 2),
                number_format($item['net_salary'], 2),
                $item['status'],
                $item['remark']
            ];
        }

        // ชื่อไฟล์
        $filename = 'salary_list';
        if (!empty($params['year'])) {
            $filename .= '_'.$params['year'];
        }
        if (!empty($params['month']) && $params['month'] != 0) {
            $filename .= '_'.sprintf('%02d', $params['month']);
        }

        // ดาวน์โหลดไฟล์ CSV
        return \Kotchasan\Csv::send($filename, $header, $data, self::$cfg->csv_language);
    }
}
