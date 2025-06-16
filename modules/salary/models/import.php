<?php
/**
 * @filesource modules/salary/models/import.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Import;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * นำเข้าข้อมูลเงินเดือนจาก CSV
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * @var int
     */
    private $row = 0;
    /**
     * @var array
     */
    private $month;
    /**
     * @var int
     */
    private $year;
    /**
     * @var array
     */
    private $header = [];
    /**
     * @var string
     */
    private $table_salary;
    /**
     * @var string
     */
    private $table_user;

    /**
     * ชื่อหัวข้อของตาราง
     *
     * @return array
     */
    public static function headers()
    {
        return [
            Language::get('Name'),
            Language::get('Identification No.'),
            Language::get('Basic Salary'),
            Language::get('Allowance'),
            Language::get('Overtime'),
            Language::get('Bonus'),
            Language::get('Deduction'),
            Language::get('Remark')
        ];
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (import.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_manage_salary
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            // สามารถจัดการรายการเงินเดือนได้
            if ($login['active'] == 1 && Login::checkPermission($login, 'can_manage_salary')) {
                // ค่าที่ส่งมา
                $this->month = $request->post('month')->toInt();
                $this->year = $request->post('year')->toInt();

                // ตรวจสอบข้อมูลเดือนและปี
                if ($this->month < 1 || $this->month > 12) {
                    $ret['alert'] = 'Invalid month selected';
                    echo json_encode($ret);
                    return;
                }
                if ($this->year < 2000 || $this->year > 2100) {
                    $ret['alert'] = 'Invalid year selected';
                    echo json_encode($ret);
                    return;
                }
                $this->header = self::headers();
                $this->table_salary = $this->getTableName('salary');
                $this->table_user = $this->getTableName('user');
                // อัปโหลดไฟล์ csv
                foreach ($request->getUploadedFiles() as $item => $file) {
                    /* @var $file \Kotchasan\Http\UploadedFile */
                    if ($file->hasUploadFile()) {
                        if (!$file->validFileExt(['csv'])) {
                            // ชนิดของไฟล์ไม่ถูกต้อง
                            $ret['ret_'.$item] = Language::get('The type of file is invalid');
                        } else {
                            try {
                                // import ข้อมูล
                                \Kotchasan\Csv::read(
                                    $file->getTempFileName(),
                                    [$this, 'importSalary'],
                                    $this->header,
                                    self::$cfg->csv_language
                                );
                                // ส่งค่ากลับ
                                $ret['alert'] = Language::replace('Successfully imported :count items', [':count' => $this->row]);
                                $ret['location'] = WEB_URL.'index.php?module=salary-setup&year='.$this->year.'&month='.sprintf('%02d', $this->month);
                                // log
                                \Index\Log\Model::add(0, 'salary', 'Import', $ret['alert'], $login['id']);
                            } catch (\Throwable $th) {
                                $ret['ret_'.$item] = $th->getMessage();
                            }
                        }
                    } elseif ($file->hasError()) {
                        // upload Error
                        $ret['ret_'.$item] = $file->getErrorMessage();
                    } else {
                        // ไม่ได้เลือกไฟล์
                        $ret['ret_'.$item] = 'Please browse file';
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * ฟังก์ชั่นรับค่าจากการอ่าน CSV
     *
     * @param array $data
     */
    public function importSalary($data)
    {
        $name = Text::topic($data[$this->header[0]]);
        $id_card = preg_replace('/[^0-9]+/', '', $data[$this->header[1]]);

        if ($name != '' && $id_card != '') {
            // ตรวจสอบว่ามีข้อมูลบุคลากรที่มีหมายเลขบัตรประชาชนนี้หรือไม่
            $member = $this->db()->first($this->table_user, ['id_card', $id_card]);
            if ($member) {
                // ดึงข้อมูลจาก CSV
                $basic_salary = (float) preg_replace('/[^0-9\.]/', '', $data[$this->header[2]]);
                $allowance = (float) preg_replace('/[^0-9\.]/', '', $data[$this->header[3]]);
                $overtime = (float) preg_replace('/[^0-9\.]/', '', $data[$this->header[4]]);
                $bonus = (float) preg_replace('/[^0-9\.]/', '', $data[$this->header[5]]);
                $other_deduction = (float) preg_replace('/[^0-9\.]/', '', $data[$this->header[6]]);
                $remark = isset($data[$this->header[7]]) ? Text::topic($data[$this->header[7]]) : 'Imported from CSV';

                // ใช้ Calculator คำนวณอัตโนมัติ
                $calculationData = [
                    'basic_salary' => $basic_salary,
                    'allowance' => $allowance,
                    'overtime' => $overtime,
                    'bonus' => $bonus,
                    'deduction' => $other_deduction
                ];

                // คำนวณด้วย Calculator
                $calculation = \Salary\Calculator\Model::calculateNetSalary($calculationData);

                $salary = [
                    'id' => $member->id.sprintf('%04d', $this->year).sprintf('%02d', $this->month),
                    'member_id' => $member->id,
                    'month' => sprintf('%02d', $this->month),
                    'year' => $this->year,
                    'basic_salary' => $calculation['basic_salary'],
                    'allowance' => $calculation['allowance'],
                    'overtime' => $calculation['overtime'],
                    'bonus' => $calculation['bonus'],
                    'deduction' => $calculation['other_deductions'],
                    'social_security' => $calculation['social_security'],
                    'tax' => $calculation['income_tax'],
                    'net_salary' => $calculation['net_salary'],
                    'remark' => $remark,
                    'create_date' => date('Y-m-d H:i:s')
                ];

                // ตรวจสอบว่ามีข้อมูลของเดือนนี้หรือไม่
                $exists = $this->db()->createQuery()
                    ->from('salary')
                    ->where([
                        ['member_id', $member->id],
                        ['month', sprintf('%02d', $this->month)],
                        ['year', $this->year]
                    ])
                    ->first('id');

                if ($exists) {
                    // อัปเดตข้อมูลเดิม (ไม่รวม id และ create_date)
                    unset($salary['id'], $salary['create_date']);
                    $this->db()->update($this->table_salary, $exists['id'], $salary);
                } else {
                    // บันทึกข้อมูลใหม่
                    $this->db()->insert($this->table_salary, $salary);
                } // นำเข้าข้อมูลสำเร็จ
                ++$this->row;
            }
        }
    }
}
