<?php
/**
 * @filesource modules/salary/models/home.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Home;

use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Language;

/**
 * Model สำหรับจัดการข้อมูล Home card ของระบบเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลสำหรับสร้าง card ในหน้า Home
     *
     * @param array $login ข้อมูลผู้ใช้ที่ล็อกอิน
     *
     * @return array
     */
    public static function getCardData($login)
    {
        $result = [
            'total_employees' => 0,
            'total_salary_this_month' => 0,
            'average_salary' => 0,
            'pending_records' => 0,
            'my_salary_this_month' => 0,
            'my_total_records' => 0,
            'last_salary' => 0,
            'last_salary_date' => ''
        ];

        try {
            // นับจำนวนพนักงานทั้งหมด
            $result['total_employees'] = static::createQuery()
                ->from('user U')
                ->where(['U.active', 1])
                ->count();

            // ข้อมูลเงินเดือนประจำเดือนปัจจุบัน
            $currentMonthData = static::createQuery()
                ->select('S.id', 'S.member_id', 'S.net_salary', 'S.basic_salary')
                ->from('salary S')
                ->where([
                    ['S.year', date('Y')],
                    ['S.month', date('m')],
                    ['S.status', 1]
                ])
                ->toArray()
                ->cacheOn()
                ->execute();
            if (!empty($currentMonthData)) {
                $totalSalary = 0;
                $count = 0;

                foreach ($currentMonthData as $item) {
                    // ตรวจสอบว่า net_salary มีอยู่ใน array หรือไม่
                    $salary = isset($item['net_salary']) ? (float) $item['net_salary'] : 0;
                    $totalSalary += $salary;
                    $count++;

                    // เงินเดือนของผู้ใช้ปัจจุบัน - ตรวจสอบ member_id และ login id
                    if (isset($item['member_id']) && isset($login['id']) && $item['member_id'] == $login['id']) {
                        $result['my_salary_this_month'] = $salary;
                        $result['my_salary_id'] = $item['id'];
                    }
                }

                $result['total_salary_this_month'] = $totalSalary;
                $result['average_salary'] = $count > 0 ? ($totalSalary / $count) : 0;
            }

            // จำนวนรายการเงินเดือนของผู้ใช้ปัจจุบัน
            $result['my_total_records'] = static::createQuery()
                ->from('salary')
                ->where(['member_id', $login['id']])
                ->count();

            // เงินเดือนล่าสุดของผู้ใช้
            $lastSalary = static::createQuery()
                ->from('salary')
                ->where([
                    ['member_id', $login['id']],
                    ['status', 1]
                ])
                ->order('year DESC', 'month DESC')
                ->first('id', 'net_salary', 'year', 'month');

            if ($lastSalary) {
                $result['last_salary'] = (float) $lastSalary->net_salary;
                $result['last_salary_date'] = Date::format($lastSalary->year.'-'.$lastSalary->month.'-01', 'M Y');
                $result['last_salary_id'] = $lastSalary->id;
            }

            // นับรายการที่ต้องตรวจสอบ (สำหรับแอดมิน)
            $result['pending_records'] = static::createQuery()
                ->from('salary S')
                ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
                ->where([
                    ['S.net_salary', 0],
                    ['S.basic_salary', 0]
                ], 'OR')
                ->count();

        } catch (\Exception $e) {
            // หากเกิดข้อผิดพลาด ให้ใช้ค่าเริ่มต้น
        }

        return $result;
    }

    /**
     * อ่านข้อมูลแนวโน้มเงินเดือนของสมาชิกทั่วไป
     *
     * @param int $memberId ID ของสมาชิก
     *
     * @return array
     */
    public static function getMemberSalaryTrend($memberId)
    {
        $query = static::createQuery()
            ->select('S.month', 'S.year', 'S.net_salary', 'S.basic_salary')
            ->from('salary S')
            ->where([
                ['S.member_id', $memberId],
                ['S.status', 1]
            ])
            ->order('S.year DESC', 'S.month DESC')
            ->limit(12)
            ->toArray();

        $result = [];
        $year_offset = Language::get('YEAR_OFFSET');
        foreach ($query->execute() as $item) {
            $result[] = [
                'period' => Language::get('MONTH_SHORT', '', (int) $item['month']).' '.($item['year'] + $year_offset),
                'net_salary' => floor($item['net_salary']),
                'basic_salary' => floor($item['basic_salary'])
            ];
        }

        // เรียงลำดับจากเก่าไปใหม่
        return array_reverse($result);
    }

    /**
     * อ่านข้อมูลแนวโน้มเงินเดือนแยกตามแผนก (สำหรับผู้ดูแล)
     *
     * @return array
     */
    public static function getDepartmentSalaryTrend()
    {
        $trends = static::createQuery()
            ->select('D.value department', 'S.month', 'S.year', Sql::AVG('S.net_salary', 'avg_salary'))
            ->from('salary S')
            ->join('user_meta D', 'LEFT', [['D.member_id', 'S.member_id'], ['D.name', 'department']])
            ->where(['S.year', date('Y')])
            ->groupBy('D.value', 'S.month', 'S.year')
            ->cacheOn()
            ->execute();
        $departments = [];
        foreach ($trends as $item) {
            $departments[$item->department] = $item->avg_salary;
        }
        // เรียงลำดับแผนกตามค่าเฉลี่ยเงินเดือน
        arsort($departments);
        // จำกัดแค่ 5 แผนกแรก
        $departments = array_slice($departments, 0, 5, true);
        $result = [];
        $year_offset = Language::get('YEAR_OFFSET');
        foreach ($trends as $item) {
            if (isset($departments[$item->department])) {
                $period = Language::get('MONTH_SHORT', '', (int) $item->month).' '.($item->year + $year_offset);
                if (!isset($result[$period])) {
                    $result[$period] = [];
                }
                $result[$period][$item->department] = floor($item->avg_salary);
            }
        }
        return $result;
    }

    /**
     * อ่านข้อมูลเปรียบเทียบเงินเดือนรายเดือน (สำหรับผู้ดูแล)
     *
     * @return array
     */
    public static function getMonthlyTrendComparison()
    {
        $query = static::createQuery()
            ->select('S.month', 'S.year', Sql::AVG('S.net_salary', 'avg_salary'), Sql::COUNT('S.id', 'employee_count'))
            ->from('salary S')
            ->where(['S.year', date('Y')])
            ->groupBy('S.month', 'S.year')
            ->order('S.month')
            ->toArray();

        $result = [];
        $months = Language::get('MONTH_SHORT');

        foreach ($query->execute() as $item) {
            $result[] = [
                'month' => $months[(int) $item['month']],
                'avg_salary' => floor($item['avg_salary']),
                'employee_count' => floor($item['employee_count'])
            ];
        }

        return $result;
    }

    /**
     * อ่านข้อมูลเงินเดือนตามแผนก (สำหรับกราฟวงกลม)
     *
     * @return array
     */
    public static function getDepartmentSalaryPie()
    {
        $query = static::createQuery()
            ->select('D.value department', Sql::AVG('S.net_salary', 'avg_salary'))
            ->from('salary S')
            ->join('user_meta D', 'LEFT', [['D.member_id', 'S.member_id'], ['D.name', 'department']])
            ->where(['S.year', date('Y')])
            ->groupBy('D.value')
            ->toArray();

        $result = [];
        foreach ($query->execute() as $item) {
            $result[] = [
                'department' => $item['department'] ?: '0',
                'avg_salary' => floor($item['avg_salary'])
            ];
        }

        return $result;
    }

    /**
     * อ่านข้อมูลจำนวนพนักงานตามแผนก (สำหรับกราฟวงกลม)
     *
     * @return array
     */
    public static function getDepartmentEmployeeCount()
    {
        $query = static::createQuery()
            ->select('D.value department', Sql::COUNT('DISTINCT S.member_id', 'employee_count'))
            ->from('salary S')
            ->join('user_meta D', 'LEFT', [['D.member_id', 'S.member_id'], ['D.name', 'department']])
            ->where(['S.year', date('Y')])
            ->groupBy('D.value')
            ->toArray();

        $result = [];
        foreach ($query->execute() as $item) {
            $result[] = [
                'department' => $item['department'] ?: '0',
                'employee_count' => floor($item['employee_count'])
            ];
        }

        return $result;
    }

    /**
     * อ่านข้อมูลแนวโน้มจำนวนพนักงานตามแผนก
     *
     * @return array
     */
    public static function getDepartmentEmployeeTrend()
    {
        $trends = static::createQuery()
            ->select('D.value department', 'S.month', 'S.year', Sql::COUNT('DISTINCT S.member_id', 'employee_count'))
            ->from('salary S')
            ->join('user_meta D', 'LEFT', [['D.member_id', 'S.member_id'], ['D.name', 'department']])
            ->where(['S.year', date('Y')])
            ->groupBy('D.value', 'S.month', 'S.year')
            ->cacheOn()
            ->execute();

        $departments = [];
        foreach ($trends as $item) {
            $departments[$item->department] = $item->employee_count;
        }
        // เรียงลำดับแผนกตามจำนวนพนักงาน
        arsort($departments);
        // จำกัดแค่ 5 แผนกแรก
        $departments = array_slice($departments, 0, 5, true);

        $result = [];
        $year_offset = Language::get('YEAR_OFFSET');
        foreach ($trends as $item) {
            if (isset($departments[$item->department])) {
                $period = Language::get('MONTH_SHORT', '', (int) $item->month).' '.($item->year + $year_offset);
                if (!isset($result[$period])) {
                    $result[$period] = [];
                }
                $result[$period][$item->department] = (int) $item->employee_count;
            }
        }
        return $result;
    }

    /**
     * อ่านข้อมูลการกระจายเงินเดือน (Salary Distribution)
     *
     * @return array
     */
    public static function getSalaryDistribution()
    {
        $query = static::createQuery()
            ->select('S.net_salary')
            ->from('salary S')
            ->where([['S.net_salary', '>', 0], ['S.year', date('Y')]])
            ->toArray();

        $ranges = [
            '0-20K' => ['min' => 0, 'max' => 20000],
            '20K-30K' => ['min' => 20001, 'max' => 30000],
            '30K-40K' => ['min' => 30001, 'max' => 40000],
            '40K-50K' => ['min' => 40001, 'max' => 50000],
            '50K+' => ['min' => 50001, 'max' => 999999999]
        ];

        $distribution = [];
        foreach ($ranges as $range => $limits) {
            $distribution[$range] = 0;
        }

        foreach ($query->execute() as $item) {
            $salary = (float) $item['net_salary'];
            foreach ($ranges as $range => $limits) {
                if ($salary >= $limits['min'] && $salary <= $limits['max']) {
                    $distribution[$range]++;
                    break;
                }
            }
        }

        $result = [];
        foreach ($distribution as $range => $count) {
            if ($count > 0) {
                $result[] = [
                    'range' => $range,
                    'count' => $count
                ];
            }
        }

        return $result;
    }
}
