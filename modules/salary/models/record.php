<?php
/**
 * @filesource modules/salary/models/record.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Record;

use Salary\Base\Model as Base;

/**
 * อ่านและบันทึกรายการเงินเดือน 1 รายการ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านรายการเงินเดือนสำหรับฟอร์ม ถ้าไม่ระบุ id คืนค่ารายการเปล่า
     *
     * @param string $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id === '' || $id === '0') {
            return (object) [
                'id' => '',
                'member_id' => 0,
                'member_text' => '',
                'year' => date('Y'),
                'month' => Base::monthValue(date('n')),
                'period_text' => '',
                'basic_salary' => 0,
                'allowance' => 0,
                'overtime' => 0,
                'overtime_hours' => 0,
                'bonus' => 0,
                'deduction' => 0,
                'social_security' => 0,
                'tax' => 0,
                'net_salary' => 0,
                'status' => 0,
                'remark' => '',
                'auto_calculate' => self::autoCalculateFlag()
            ];
        }

        $index = static::createQuery()
            ->select('S.*', 'U.name', 'U.id_card')
            ->from('salary S')
            ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
            ->where([['S.id', $id]])
            ->first();

        if (!$index) {
            return null;
        }

        $index->member_text = empty($index->id_card) ? $index->name : $index->name.' ('.$index->id_card.')';
        $index->period_text = Base::periodText($index->year, $index->month);
        $index->auto_calculate = self::autoCalculateFlag();

        return $index;
    }

    /**
     * โหมดการคำนวณสำหรับฟอร์ม 1 = คำนวณให้, 0 = กรอกประกันสังคมและภาษีเอง
     *
     * @return int
     */
    private static function autoCalculateFlag()
    {
        return \Salary\Calculator\Model::autoCalculate() ? 1 : 0;
    }

    /**
     * ตรวจสอบว่ามีรายการของพนักงานคนนี้ในเดือนที่เลือกแล้วหรือยัง
     *
     * @param int $member_id
     * @param int|string $year
     * @param int|string $month
     *
     * @return object|null
     */
    public static function findByPeriod($member_id, $year, $month)
    {
        return static::createQuery()
            ->select('id')
            ->from('salary')
            ->where([
                ['member_id', (int) $member_id],
                ['year', (string) $year],
                ['month', Base::monthValue($month)]
            ])
            ->first();
    }

    /**
     * บันทึกรายการเงินเดือน
     *
     * ค่าล่วงเวลาจากชั่วโมง ประกันสังคม ภาษี และเงินสุทธิ คำนวณที่ฝั่งเซิร์ฟเวอร์เสมอ
     * ไม่รับค่าที่ส่งมาจากฟอร์มเพื่อกันการแก้ไขค่าจากฝั่งผู้ใช้
     * ยกเว้นประกันสังคมและภาษีเมื่อปิดการคำนวณอัตโนมัติ ซึ่งผู้ดูแลกรอกเอง
     *
     * @param array $save ข้อมูลที่ผ่านการตรวจสอบแล้ว
     * @param string $id ว่าง = เพิ่มใหม่
     *
     * @return array ข้อมูลที่บันทึกจริง
     */
    public static function save(array $save, $id = '')
    {
        $calculation = \Salary\Calculator\Model::calculateNetSalary($save);
        $save['overtime'] = $calculation['overtime'];
        $save['overtime_hours'] = $calculation['overtime_hours'];
        $save['social_security'] = $calculation['social_security'];
        $save['tax'] = $calculation['income_tax'];
        $save['net_salary'] = $calculation['net_salary'];

        $db = \Kotchasan\DB::create();
        if ($id === '') {
            $save['id'] = Base::recordId($save['member_id'], $save['year'], $save['month']);
            $save['create_date'] = date('Y-m-d H:i:s');
            // ไม่ต้องอนุมัติ = อนุมัติให้ทันที พนักงานเห็นสลิปได้เลย
            $save['status'] = \Salary\Calculator\Model::requiresApproval() ? 0 : 1;
            $db->insert('salary', $save);
        } else {
            $save['id'] = $id;
            $db->update('salary', [['id', $id]], $save);
        }

        return $save;
    }
}
