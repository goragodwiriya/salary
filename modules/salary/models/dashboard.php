<?php
/**
 * @filesource modules/salary/models/dashboard.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Dashboard;

use Kotchasan\Database\Sql;
use Kotchasan\Language;
use Salary\Base\Model as Base;

/**
 * ข้อมูลสรุปและกราฟของหน้าแรก
 *
 * ระบบเดิมมีบล็อกกราฟอยู่ใน Home\View แต่เรียกเมธอดที่ไม่มีอยู่จริงใน Home\Model
 * ทำให้กราฟทั้งหมดใช้งานไม่ได้ ที่นี่จึงเขียนสูตรของกราฟทั้ง 6 แบบขึ้นมาให้ครบ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รายการ [ปี, เดือน] ย้อนหลังจากเดือนปัจจุบัน เรียงจากเก่าไปใหม่
     *
     * @param int $count จำนวนเดือน
     *
     * @return array
     */
    public static function periods($count = 12)
    {
        $result = [];
        for ($i = $count - 1; $i >= 0; --$i) {
            $time = mktime(0, 0, 0, (int) date('n') - $i, 1, (int) date('Y'));
            $result[] = [
                'year' => date('Y', $time),
                'month' => date('m', $time),
                'key' => date('Y-m', $time),
                'label' => Base::periodText(date('Y', $time), date('m', $time), true)
            ];
        }

        return $result;
    }

    /**
     * อ่านรายการเงินเดือนของช่วงเวลาที่กำหนด พร้อมแผนกของพนักงาน
     *
     * @param array $periods ผลลัพธ์จาก periods()
     * @param int $member_id 0 = ทุกคน
     *
     * @return array
     */
    public static function rowsOfPeriods(array $periods, $member_id = 0)
    {
        if (empty($periods)) {
            return [];
        }

        $years = array_values(array_unique(array_column($periods, 'year')));
        $where = [
            ['S.year', $years],
            ['U.active', 1]
        ];
        if ($member_id > 0) {
            $where[] = ['S.member_id', (int) $member_id];
        }

        $rows = static::createQuery()
            ->select('S.member_id', 'S.year', 'S.month', 'S.basic_salary', 'S.net_salary')
            ->from('salary S')
            ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
            ->where($where)
            ->fetchAll();

        // ตัดเฉพาะเดือนที่อยู่ในช่วงที่ขอ (query กรองได้แค่ระดับปี)
        $keys = array_flip(array_column($periods, 'key'));
        $result = [];
        foreach ($rows as $item) {
            $key = $item->year.'-'.$item->month;
            if (isset($keys[$key])) {
                $item->period_key = $key;
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * ข้อมูลการ์ดสรุปของหน้าแรก
     *
     * @param object $login
     * @param bool $canManage
     *
     * @return array
     */
    public static function cards($login, $canManage)
    {
        $year = date('Y');
        $month = date('m');

        if ($canManage) {
            $total_employees = static::createQuery()
                ->select(Sql::COUNT('*', 'count'))
                ->from('user')
                ->where([['active', 1]])
                ->first();

            $current = static::createQuery()
                ->select(Sql::SUM('S.net_salary', 'total'), Sql::COUNT('S.id', 'count'))
                ->from('salary S')
                ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
                ->where([
                    ['S.year', $year],
                    ['S.month', $month],
                    ['U.active', 1]
                ])
                ->first();

            // ระบบเดิมนับรายการที่ยอดเป็นศูนย์ ทั้งที่การ์ดเขียนว่ารอตรวจสอบ
            // ที่ถูกคือรายการที่ยังไม่ได้อนุมัติ
            $pending = static::createQuery()
                ->select(Sql::COUNT('S.id', 'count'))
                ->from('salary S')
                ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
                ->where([
                    ['S.status', 0],
                    ['U.active', 1]
                ])
                ->first();

            $count = $current ? (int) $current->count : 0;
            $total = $current ? (float) $current->total : 0;

            return [
                'can_manage' => true,
                'period_text' => Base::periodText($year, $month),
                'total_employees' => $total_employees ? (int) $total_employees->count : 0,
                'total_salary' => $total,
                'total_salary_text' => number_format($total, 2),
                'average_salary' => $count > 0 ? round($total / $count, 2) : 0,
                'average_salary_text' => $count > 0 ? number_format($total / $count, 2) : '0.00',
                'record_count' => $count,
                'pending_records' => $pending ? (int) $pending->count : 0
            ];
        }

        $member_id = (int) $login->id;

        $mine = static::createQuery()
            ->select('id', 'net_salary')
            ->from('salary')
            ->where([
                ['member_id', $member_id],
                ['year', $year],
                ['month', $month],
                ['status', 1]
            ])
            ->first();

        $total_records = static::createQuery()
            ->select(Sql::COUNT('id', 'count'))
            ->from('salary')
            ->where([
                ['member_id', $member_id],
                ['status', 1]
            ])
            ->first();

        // ระบบเดิมเรียงด้วยคอลัมน์ month ที่เก็บแค่ '01'..'12' จึงได้รายการผิดเดือนเสมอ
        $last = static::createQuery()
            ->select('id', 'year', 'month', 'net_salary')
            ->from('salary')
            ->where([
                ['member_id', $member_id],
                ['status', 1]
            ])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        return [
            'can_manage' => false,
            'period_text' => Base::periodText($year, $month),
            'my_salary' => $mine ? (float) $mine->net_salary : 0,
            'my_salary_text' => $mine ? number_format($mine->net_salary, 2) : '0.00',
            'my_salary_url' => $mine ? WEB_URL.'export.php?module=salary&typ=slip&id='.rawurlencode($mine->id) : '',
            'my_total_records' => $total_records ? (int) $total_records->count : 0,
            'last_salary' => $last ? (float) $last->net_salary : 0,
            'last_salary_text' => $last ? number_format($last->net_salary, 2) : '0.00',
            'last_salary_period' => $last ? Base::periodText($last->year, $last->month) : '',
            'last_salary_url' => $last ? WEB_URL.'export.php?module=salary&typ=slip&id='.rawurlencode($last->id) : ''
        ];
    }

    /**
     * แนวโน้มเงินเดือนของสมาชิก 1 คน (เงินสุทธิและฐานเงินเดือน)
     *
     * @param int $member_id
     * @param int $count จำนวนเดือนย้อนหลัง
     *
     * @return array
     */
    public static function memberTrend($member_id, $count = 12)
    {
        $periods = self::periods($count);
        $rows = self::rowsOfPeriods($periods, $member_id);

        $net = [];
        $basic = [];
        foreach ($rows as $item) {
            $net[$item->period_key] = (float) $item->net_salary;
            $basic[$item->period_key] = (float) $item->basic_salary;
        }

        $netSeries = [];
        $basicSeries = [];
        foreach ($periods as $period) {
            $netSeries[] = [
                'label' => $period['label'],
                'value' => $net[$period['key']] ?? 0
            ];
            $basicSeries[] = [
                'label' => $period['label'],
                'value' => $basic[$period['key']] ?? 0
            ];
        }

        return [
            ['name' => Language::get('Net Salary'), 'data' => $netSeries],
            ['name' => Language::get('Basic Salary'), 'data' => $basicSeries]
        ];
    }

    /**
     * แนวโน้มเงินเดือนเฉลี่ยแยกตามแผนก
     *
     * @param int $count จำนวนเดือนย้อนหลัง
     *
     * @return array
     */
    public static function departmentTrend($count = 6)
    {
        return self::departmentSeries($count, 'salary');
    }

    /**
     * แนวโน้มจำนวนพนักงานที่มีเงินเดือนแยกตามแผนก
     *
     * @param int $count จำนวนเดือนย้อนหลัง
     *
     * @return array
     */
    public static function departmentEmployeeTrend($count = 6)
    {
        return self::departmentSeries($count, 'count');
    }

    /**
     * เงินเดือนเฉลี่ยของเดือนล่าสุดแยกตามแผนก (กราฟวงกลม)
     *
     * @return array
     */
    public static function departmentAverage()
    {
        $series = self::departmentSeries(1, 'salary');
        $points = [];
        foreach ($series as $item) {
            $value = isset($item['data'][0]) ? $item['data'][0]['value'] : 0;
            if ($value > 0) {
                $points[] = [
                    'label' => $item['name'],
                    'value' => $value
                ];
            }
        }

        return self::pieSeries(Language::get('Average Salary'), $points);
    }

    /**
     * จำนวนพนักงานที่ยังทำงานอยู่แยกตามแผนก (กราฟวงกลม)
     *
     * @return array
     */
    public static function departmentEmployeeCount()
    {
        $members = static::createQuery()
            ->select('id')
            ->from('user')
            ->where([['active', 1]])
            ->fetchAll();

        $meta = \Salary\Category\Model::metaOf(array_column($members, 'id'));
        $unknown = Language::get('Not specified');

        $counts = [];
        foreach ($members as $item) {
            $department = $meta[$item->id]['department'] ?? $unknown;
            $counts[$department] = ($counts[$department] ?? 0) + 1;
        }
        arsort($counts);

        $points = [];
        foreach ($counts as $department => $count) {
            $points[] = [
                'label' => $department,
                'value' => $count
            ];
        }

        return self::pieSeries(Language::get('Employee Count'), $points);
    }

    /**
     * การกระจายของเงินเดือนสุทธิในเดือนล่าสุด
     *
     * @return array
     */
    public static function salaryDistribution()
    {
        $ranges = [
            [0, 15000],
            [15000, 30000],
            [30000, 50000],
            [50000, 100000],
            [100000, 0]
        ];

        $periods = self::periods(1);
        $rows = self::rowsOfPeriods($periods);

        $counts = array_fill(0, count($ranges), 0);
        foreach ($rows as $item) {
            $value = (float) $item->net_salary;
            foreach ($ranges as $i => $range) {
                if ($value > $range[0] && ($range[1] === 0 || $value <= $range[1])) {
                    ++$counts[$i];
                    break;
                }
            }
        }

        $points = [];
        foreach ($ranges as $i => $range) {
            if (empty($counts[$i])) {
                continue;
            }
            $points[] = [
                'label' => $range[1] === 0
                    ? '> '.number_format($range[0])
                    : number_format($range[0]).' - '.number_format($range[1]),
                'value' => $counts[$i]
            ];
        }

        return self::pieSeries(Language::get('Employee Count'), $points);
    }

    /**
     * กราฟวงกลมใช้โครงเดียวกับกราฟเส้น คือ 1 ชุดข้อมูลที่มีหลายจุด
     * (GraphRenderer อ่าน series.data ของทุกชนิดกราฟ)
     *
     * @param string $name
     * @param array $points
     *
     * @return array
     */
    protected static function pieSeries($name, array $points)
    {
        return empty($points) ? [] : [['name' => $name, 'data' => $points]];
    }

    /**
     * สร้างชุดข้อมูลกราฟแยกตามแผนก
     *
     * @param int $count จำนวนเดือนย้อนหลัง
     * @param string $mode salary = เงินเดือนเฉลี่ย, count = จำนวนพนักงาน
     *
     * @return array
     */
    protected static function departmentSeries($count, $mode)
    {
        $periods = self::periods($count);
        $rows = self::rowsOfPeriods($periods);
        if (empty($rows)) {
            return [];
        }

        $meta = \Salary\Category\Model::metaOf(array_unique(array_column($rows, 'member_id')));
        $unknown = Language::get('Not specified');

        $totals = [];
        foreach ($rows as $item) {
            $department = $meta[$item->member_id]['department'] ?? $unknown;
            if (!isset($totals[$department][$item->period_key])) {
                $totals[$department][$item->period_key] = ['sum' => 0, 'count' => 0];
            }
            $totals[$department][$item->period_key]['sum'] += (float) $item->net_salary;
            ++$totals[$department][$item->period_key]['count'];
        }
        ksort($totals);

        $result = [];
        foreach ($totals as $department => $byPeriod) {
            $data = [];
            foreach ($periods as $period) {
                $bucket = $byPeriod[$period['key']] ?? ['sum' => 0, 'count' => 0];
                if ($mode === 'count') {
                    $value = $bucket['count'];
                } else {
                    $value = $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 2) : 0;
                }
                $data[] = [
                    'label' => $period['label'],
                    'value' => $value
                ];
            }
            $result[] = [
                'name' => $department,
                'data' => $data
            ];
        }

        return $result;
    }
}
