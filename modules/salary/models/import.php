<?php
/**
 * @filesource modules/salary/models/import.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Import;

use Kotchasan\Text;
use Salary\Base\Model as Base;
use Salary\Calculator\Model as Calculator;

/**
 * นำเข้าข้อมูลเงินเดือนจากไฟล์ CSV
 *
 * จับคู่พนักงานด้วยเลขประจำตัวประชาชน ถ้ามีรายการของเดือนนั้นแล้วจะปรับปรุงข้อมูลเดิม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * จำนวนแถวที่นำเข้าสำเร็จ
     *
     * @var int
     */
    private $row = 0;

    /**
     * @var int
     */
    private $month;

    /**
     * @var int
     */
    private $year;

    /**
     * ชื่อคอลัมน์จริงในไฟล์ที่อ่านได้ (ชื่อช่องของ columns() => ชื่อคอลัมน์ในไฟล์)
     *
     * @var array
     */
    private $header = [];

    /**
     * หัวข้อของไฟล์ CSV (ใช้ตอนสร้างไฟล์ตัวอย่าง)
     *
     * @return array ชื่อช่องของ columns() => หัวข้อ
     */
    public static function headers()
    {
        return array_map(function ($labels) {
            return $labels[0];
        }, self::columns());
    }

    /**
     * ชื่อคอลัมน์ที่ยอมรับได้ของแต่ละช่อง ตัวแรกคือชื่อที่ใช้สร้างไฟล์ตัวอย่าง
     *
     * ระบบเดิมแปล Name และ Identification No. ไว้คนละคำกับ adminframework
     * จึงรับคำเดิมไว้ด้วย ไฟล์ CSV ที่ผู้ใช้มีอยู่แล้วจะยังนำเข้าได้เหมือนเดิม
     *
     * ปิดการคำนวณอัตโนมัติ ไฟล์ต้องมีประกันสังคมและภาษีมาด้วย
     *
     * @return array ชื่อช่อง => ชื่อคอลัมน์ที่ยอมรับได้
     */
    public static function columns()
    {
        $columns = [
            'name' => Base::labelsOf('Name', ['ชื่อ นามสกุล']),
            'id_card' => Base::labelsOf('Identification No.', ['รหัสพนักงาน']),
            'basic_salary' => Base::labelsOf('Basic Salary'),
            'allowance' => Base::labelsOf('Allowance'),
            'overtime_hours' => Base::labelsOf('Overtime Hours'),
            'overtime' => Base::labelsOf('Overtime'),
            'bonus' => Base::labelsOf('Bonus'),
            'deduction' => Base::labelsOf('Deduction', ['หักอื่นๆ'])
        ];
        if (!Calculator::autoCalculate()) {
            $columns['social_security'] = Base::labelsOf('Social Security');
            $columns['tax'] = Base::labelsOf('Income Tax');
        }
        $columns['remark'] = Base::labelsOf('Remark');

        return $columns;
    }

    /**
     * ช่องที่ไม่บังคับ
     * ชั่วโมงล่วงเวลาเพิ่มมาทีหลัง ไฟล์ของระบบเดิมจึงไม่มีคอลัมน์นี้
     *
     * @return array
     */
    public static function optionalColumns()
    {
        return ['overtime_hours', 'remark'];
    }

    /**
     * อ่านไฟล์ CSV แล้วบันทึกลงฐานข้อมูล
     *
     * @param string $filename ไฟล์ที่อัปโหลดมา
     * @param int $year
     * @param int $month
     *
     * @return int จำนวนรายการที่นำเข้า
     */
    public static function import($filename, $year, $month)
    {
        $model = new static();
        $model->year = (int) $year;
        $model->month = (int) $month;

        \Kotchasan\Csv::read(
            $filename,
            [$model, 'importRow'],
            null,
            self::$cfg->salary_csv_language,
            [$model, 'mapColumns']
        );

        return $model->row;
    }

    /**
     * จับคู่คอลัมน์ของไฟล์กับช่องข้อมูลที่ต้องการ (เรียกครั้งเดียวตอนอ่านบรรทัดหัวตาราง)
     *
     * @param array $columns ชื่อคอลัมน์ที่อ่านได้จากไฟล์
     *
     * @throws \Exception ถ้าไฟล์ขาดคอลัมน์ที่จำเป็น
     */
    public function mapColumns($columns)
    {
        $this->header = Base::matchColumns($columns, self::columns(), self::optionalColumns());
    }

    /**
     * บันทึกข้อมูล 1 แถวจากไฟล์ CSV
     *
     * @param array $data
     */
    public function importRow($data)
    {
        $name = Text::topic($this->column($data, 'name'));
        $id_card = preg_replace('/[^0-9]+/', '', $this->column($data, 'id_card'));
        if ($name === '' || $id_card === '') {
            return;
        }

        $db = \Kotchasan\DB::create();
        $member = $db->first('user', [['id_card', $id_card]]);
        if (!$member) {
            // ไม่มีพนักงานคนนี้ในระบบ ข้ามไป (ต้องนำเข้าข้อมูลพนักงานก่อน)
            return;
        }

        $calculation = Calculator::calculateNetSalary([
            'basic_salary' => $this->amount($data, 'basic_salary'),
            'allowance' => $this->amount($data, 'allowance'),
            'overtime_hours' => $this->amount($data, 'overtime_hours'),
            'overtime' => $this->amount($data, 'overtime'),
            'bonus' => $this->amount($data, 'bonus'),
            'deduction' => $this->amount($data, 'deduction'),
            'social_security' => $this->amount($data, 'social_security'),
            'tax' => $this->amount($data, 'tax')
        ]);

        $save = [
            'member_id' => $member->id,
            'year' => (string) $this->year,
            'month' => Base::monthValue($this->month),
            'basic_salary' => $calculation['basic_salary'],
            'allowance' => $calculation['allowance'],
            'overtime' => $calculation['overtime'],
            'overtime_hours' => $calculation['overtime_hours'],
            'bonus' => $calculation['bonus'],
            'deduction' => $calculation['other_deductions'],
            'social_security' => $calculation['social_security'],
            'tax' => $calculation['income_tax'],
            'net_salary' => $calculation['net_salary'],
            'remark' => Text::topic($this->column($data, 'remark'))
        ];

        $exists = \Salary\Record\Model::findByPeriod($member->id, $this->year, $this->month);
        if ($exists) {
            // ปรับปรุงรายการเดิม คงรหัสและวันที่สร้างไว้
            $db->update('salary', [['id', $exists->id]], $save);
        } else {
            $save['id'] = Base::recordId($member->id, $this->year, $this->month);
            $save['create_date'] = date('Y-m-d H:i:s');
            $save['status'] = Calculator::requiresApproval() ? 0 : 1;
            $db->insert('salary', $save);
        }

        ++$this->row;
    }

    /**
     * อ่านข้อความของช่องที่ต้องการ ไฟล์ไม่มีคอลัมน์นั้นคืนค่าว่าง
     *
     * @param array $data
     * @param string $key ชื่อช่องของ columns()
     *
     * @return string
     */
    private function column($data, $key)
    {
        if (!isset($this->header[$key])) {
            return '';
        }

        return (string) ($data[$this->header[$key]] ?? '');
    }

    /**
     * อ่านค่าตัวเลขจากคอลัมน์ของไฟล์ CSV (ตัดเครื่องหมายจุลภาคและอักขระอื่นออก)
     *
     * @param array $data
     * @param string $key ชื่อช่องของ columns()
     *
     * @return float
     */
    private function amount($data, $key)
    {
        return (float) preg_replace('/[^0-9\.]/', '', $this->column($data, $key));
    }
}
