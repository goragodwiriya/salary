<?php
/**
 * @filesource modules/salary/models/slips.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Slips;

/**
 * ประวัติเงินเดือนของสมาชิกที่ล็อกอิน
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
     * แสดงเฉพาะรายการที่อนุมัติแล้ว และเป็นของสมาชิกที่ล็อกอินเท่านั้น
     * (การกันข้อมูลข้ามคนอยู่ที่ query นี้ ไม่ใช่ที่ checkAuthorization)
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['member_id', (int) $params['member_id']],
            ['status', 1]
        ];
        if (!empty($params['year'])) {
            $where[] = ['year', (string) $params['year']];
        }

        return static::createQuery()
            ->select(
                'id',
                'year',
                'month',
                'basic_salary',
                'allowance',
                'overtime',
                'bonus',
                'deduction',
                'social_security',
                'tax',
                'net_salary',
                'create_date'
            )
            ->from('salary')
            ->where($where);
    }

    /**
     * ปีที่สมาชิกคนนี้มีข้อมูลเงินเดือน
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function years($member_id)
    {
        $query = static::createQuery()
            ->select('year')
            ->from('salary')
            ->where([
                ['member_id', (int) $member_id],
                ['status', 1]
            ])
            ->groupBy('year')
            ->orderBy('year', 'desc');

        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[] = [
                'value' => (string) $item->year,
                'text' => (string) \Salary\Base\Model::displayYear($item->year)
            ];
        }

        return $result;
    }
}
