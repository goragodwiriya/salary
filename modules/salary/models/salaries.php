<?php
/**
 * @filesource modules/salary/models/salaries.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Salaries;

use Salary\Base\Model as Base;

/**
 * รายการเงินเดือนรายเดือนของพนักงานทุกคน (สำหรับผู้ดูแล)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * ระบบเดิมแสดงเฉพาะรายการของพนักงานที่ยังทำงานอยู่ (U.active = 1)
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['U.active', 1]
        ];
        if (!empty($params['year'])) {
            $where[] = ['S.year', (string) $params['year']];
        }
        if (!empty($params['month'])) {
            $where[] = ['S.month', Base::monthValue($params['month'])];
        }
        if (isset($params['status']) && $params['status'] > -1) {
            $where[] = ['S.status', (int) $params['status']];
        }

        $query = static::createQuery()
            ->select(
                'S.id',
                'S.member_id',
                'U.name',
                'S.year',
                'S.month',
                'S.basic_salary',
                'S.allowance',
                'S.overtime',
                'S.overtime_hours',
                'S.bonus',
                'S.deduction',
                'S.social_security',
                'S.tax',
                'S.net_salary',
                'S.status'
            )
            ->from('salary S')
            ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $keyword = '%'.$params['search'].'%';
            $query->where([
                ['U.name', 'LIKE', $keyword],
                ['U.id_card', 'LIKE', $keyword]
            ], 'OR');
        }

        return $query;
    }

    /**
     * อ่านรายการเงินเดือนตาม id ที่เลือก พร้อมข้อมูลพนักงาน
     * ใช้ร่วมกันโดย approve / recalculate / send_email
     *
     * @param array $ids
     *
     * @return array
     */
    public static function getByIds(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        return static::createQuery()
            ->select(
                'S.id',
                'S.member_id',
                'S.year',
                'S.month',
                'S.basic_salary',
                'S.allowance',
                'S.overtime',
                'S.overtime_hours',
                'S.bonus',
                'S.deduction',
                'S.social_security',
                'S.tax',
                'S.net_salary',
                'S.status',
                'S.remark',
                'U.name',
                'U.username'
            )
            ->from('salary S')
            ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
            ->where([['S.id', $ids]])
            ->fetchAll();
    }

    /**
     * ลบรายการเงินเดือน
     *
     * @param array $ids
     *
     * @return int จำนวนรายการที่ลบ
     */
    public static function remove(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }

        return \Kotchasan\DB::create()->delete('salary', [['id', $ids]], 0);
    }

    /**
     * คำนวณค่าล่วงเวลา (จากชั่วโมง) ประกันสังคม ภาษี และเงินสุทธิ ของรายการที่เลือกใหม่
     * ใช้หลังแก้การตั้งค่า เช่น อัตราประกันสังคม ค่าลดหย่อน หรืออัตราค่าล่วงเวลา
     *
     * @param array $ids
     *
     * @return int จำนวนรายการที่คำนวณใหม่
     */
    public static function recalculate(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }

        $rows = static::createQuery()
            ->select('id', 'basic_salary', 'allowance', 'overtime', 'overtime_hours', 'bonus', 'deduction', 'social_security', 'tax')
            ->from('salary')
            ->where([['id', $ids]])
            ->fetchAll();

        $db = \Kotchasan\DB::create();
        $count = 0;
        foreach ($rows as $item) {
            $result = \Salary\Calculator\Model::calculateNetSalary([
                'basic_salary' => $item->basic_salary,
                'allowance' => $item->allowance,
                'overtime' => $item->overtime,
                'overtime_hours' => $item->overtime_hours,
                'bonus' => $item->bonus,
                'deduction' => $item->deduction,
                'social_security' => $item->social_security,
                'tax' => $item->tax
            ]);
            $db->update('salary', [['id', $item->id]], [
                'overtime' => $result['overtime'],
                'social_security' => $result['social_security'],
                'tax' => $result['income_tax'],
                'net_salary' => $result['net_salary']
            ]);
            ++$count;
        }

        return $count;
    }

    /**
     * อนุมัติรายการเงินเดือน (เฉพาะรายการที่ยังไม่ได้อนุมัติ)
     *
     * @param array $rows รายการจาก getByIds()
     *
     * @return array [จำนวนที่อนุมัติ, ข้อความผลการแจ้งเตือน]
     */
    public static function approve(array $rows)
    {
        $db = \Kotchasan\DB::create();
        $count = 0;
        $messages = [];
        foreach ($rows as $item) {
            if ((int) $item->status !== 0) {
                continue;
            }
            $db->update('salary', [['id', $item->id]], ['status' => 1]);
            ++$count;

            if (\Salary\Calculator\Model::emailNotification()) {
                $item->status = 1;
                $messages[] = \Salary\Email\Model::send($item, 'approve');
            }
        }

        return [$count, array_values(array_unique(array_filter($messages)))];
    }
}
