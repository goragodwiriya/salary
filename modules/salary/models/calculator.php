<?php
/**
 * @filesource modules/salary/models/calculator.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Calculator;

use Kotchasan\Http\Request;

/**
 * Salary Calculator Helper
 * คลาสสำหรับคำนวณเงินเดือนและองค์ประกอบต่างๆ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * คำนวณภาษีเงินได้ตามกฎหมายไทย (แบบขั้นบันได)
     *
     * หมายเหตุ: รายได้ที่ส่งมาควรเป็นรายได้หลังหักค่าใช้จ่ายที่หักได้แล้ว
     * เช่น ประกันสังคม, ประกันชีวิต, กองทุนสำรองเลี้ยงชีพ เป็นต้น
     *
     * @param float $taxableIncome รายได้สำหรับคำนวณภาษี (หลังหักค่าใช้จ่ายแล้ว)
     * @param float $taxRate เปอร์เซ็นต์ภาษี (default จากการตั้งค่า - ใช้สำหรับแทนที่การคำนวณแบบขั้นบันได)
     *
     * @return float จำนวนภาษีที่ต้องหัก
     */
    public static function calculateIncomeTax($taxableIncome, $taxRate = 0)
    {
        // หากกำหนด taxRate เป็น 0 ใช้การคำนวณแบบขั้นบันได, ถ้าไม่ใช่ 0 ใช้วิธีเดิม (backward compatibility)
        if ($taxRate > 0) {
            return round($taxableIncome * ($taxRate / 100), self::$cfg->salary_decimals);
        }

        // คำนวณภาษีแบบขั้นบันไดตามกฎหมายไทย (ปี 2567)
        $yearlyIncome = $taxableIncome * 12; // แปลงเป็นรายได้ต่อปี
        $tax = 0;

        // ขั้นบันไดภาษีเงินได้
        $taxBrackets = [
            [150000, 0], // 0-150,000: ยกเว้น
            [300000, 0.05], // 150,001-300,000: 5%
            [500000, 0.10], // 300,001-500,000: 10%
            [750000, 0.15], // 500,001-750,000: 15%
            [1000000, 0.20], // 750,001-1,000,000: 20%
            [2000000, 0.25], // 1,000,001-2,000,000: 25%
            [5000000, 0.30], // 2,000,001-5,000,000: 30%
            [PHP_INT_MAX, 0.35] // เกิน 5,000,000: 35%
        ];

        $previousBracket = 0;
        foreach ($taxBrackets as list($bracket, $rate)) {
            if ($yearlyIncome <= $bracket) {
                $taxableAmount = $yearlyIncome - $previousBracket;
                $tax += $taxableAmount * $rate;
                break;
            } else {
                $taxableAmount = $bracket - $previousBracket;
                $tax += $taxableAmount * $rate;
                $previousBracket = $bracket;
            }
        }

        // แปลงกลับเป็นภาษีรายเดือน
        $monthlyTax = $tax / 12;

        return round($monthlyTax, self::$cfg->salary_decimals);
    }

    /**
     * คำนวณประกันสังคม (พนักงาน)
     *
     * @param float $grossSalary เงินเดือนรวม
     * @param float $socialRate เปอร์เซ็นต์ประกันสังคม (default จากการตั้งค่า)
     *
     * @return float จำนวนประกันสังคมที่ต้องหัก
     */
    public static function calculateSocialSecurity($grossSalary, $socialRate = null)
    {
        if ($socialRate === null) {
            $socialRate = self::$cfg->salary_social;
        }

        $maxAmount = self::$cfg->salary_social_max;
        $salaryForCalculation = min($grossSalary, $maxAmount);

        return round($salaryForCalculation * ($socialRate / 100), self::$cfg->salary_decimals);
    }

    /**
     * คำนวณประกันสังคม (นายจ้าง)
     *
     * @param float $grossSalary เงินเดือนรวม
     * @param float $employerRate เปอร์เซ็นต์ประกันสังคมนายจ้าง (default จากการตั้งค่า)
     *
     * @return float จำนวนประกันสังคมที่นายจ้างต้องจ่าย
     */
    public static function calculateEmployerSocialSecurity($grossSalary, $employerRate = null)
    {
        if ($employerRate === null) {
            $employerRate = self::$cfg->salary_social_employer;
        }

        $maxAmount = self::$cfg->salary_social_max;
        $salaryForCalculation = min($grossSalary, $maxAmount);

        return round($salaryForCalculation * ($employerRate / 100), self::$cfg->salary_decimals);
    }

    /**
     * คำนวณค่าล่วงเวลา
     *
     * @param float $hourlyRate อัตราค่าแรงต่อชั่วโมง
     * @param float $overtimeHours จำนวนชั่วโมงล่วงเวลา
     * @param float $overtimeRate อัตราคูณค่าล่วงเวลา (default จากการตั้งค่า)
     *
     * @return float จำนวนเงินค่าล่วงเวลา
     */
    public static function calculateOvertime($hourlyRate, $overtimeHours, $overtimeRate = null)
    {
        if ($overtimeRate === null) {
            $overtimeRate = self::$cfg->salary_overtime_rate;
        }

        return round($hourlyRate * $overtimeHours * $overtimeRate, self::$cfg->salary_decimals);
    }

    /**
     * คำนวณอัตราค่าแรงต่อชั่วโมงจากฐานเงินเดือน
     *
     * @param float $basicSalary ฐานเงินเดือน
     * @param int $workingDays วันทำงานต่อเดือน (default จากการตั้งค่า)
     * @param float $workingHours ชั่วโมงทำงานต่อวัน (default จากการตั้งค่า)
     *
     * @return float อัตราค่าแรงต่อชั่วโมง
     */
    public static function calculateHourlyRate($basicSalary, $workingDays = null, $workingHours = null)
    {
        if ($workingDays === null) {
            $workingDays = self::$cfg->salary_working_days;
        }
        if ($workingHours === null) {
            $workingHours = self::$cfg->salary_working_hours;
        }

        $totalHours = $workingDays * $workingHours;
        return round($basicSalary / $totalHours, self::$cfg->salary_decimals);
    }

    /**
     * คำนวณเงินเดือนสุทธิ
     *
     * @param array $salaryData ข้อมูลเงินเดือน
     *
     * @return array ผลลัพธ์การคำนวณ
     */
    public static function calculateNetSalary($salaryData)
    {
        $basicSalary = floatval($salaryData['basic_salary'] ?? 0);
        $allowance = floatval($salaryData['allowance'] ?? 0);
        $bonus = floatval($salaryData['bonus'] ?? 0);
        $overtime = floatval($salaryData['overtime'] ?? 0);
        $deduction = floatval($salaryData['deduction'] ?? 0);

        // คำนวณรายได้รวม
        $totalIncome = $basicSalary + $allowance + $bonus + $overtime;

        // คำนวณประกันสังคม (คำนวณจากรายได้รวมก่อนหักภาษี)
        $socialSecurity = self::calculateSocialSecurity($totalIncome, $salaryData['social_rate'] ?? null);

        // คำนวณรายได้หลังหักค่าใช้จ่ายที่หักได้ (สำหรับคำนวณภาษี)
        $taxableIncome = $totalIncome - $socialSecurity - $deduction;

        // ป้องกันไม่ให้รายได้สำหรับคำนวณภาษีติดลบ
        $taxableIncome = max(0, $taxableIncome);

        // คำนวณภาษีเงินได้ (จากรายได้หลังหักค่าใช้จ่ายแล้ว)
        $incomeTax = self::calculateIncomeTax($taxableIncome, $salaryData['tax_rate'] ?? 0);

        // คำนวณรายหักรวม
        $totalDeductions = $incomeTax + $socialSecurity + $deduction;

        // เงินเดือนสุทธิ
        $netSalary = $totalIncome - $totalDeductions;

        return [
            'basic_salary' => $basicSalary,
            'allowance' => $allowance,
            'bonus' => $bonus,
            'overtime' => $overtime,
            'total_income' => $totalIncome,
            'taxable_income' => $taxableIncome, // เพิ่มรายได้สำหรับคำนวณภาษี
            'income_tax' => $incomeTax,
            'social_security' => $socialSecurity,
            'other_deductions' => $deduction,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'employer_social_security' => self::calculateEmployerSocialSecurity($totalIncome)
        ];
    }

    /**
     * ตรวจสอบว่าต้องการการอนุมัติหรือไม่
     *
     * @return bool
     */
    public static function requiresApproval()
    {
        return self::$cfg->salary_require_approval;

    }

    /**
     * ตรวจสอบว่าใช้การคำนวณอัตโนมัติหรือไม่
     *
     * @return bool
     */
    public static function autoCalculate()
    {
        return self::$cfg->salary_auto_calculate;
    }

    /**
     * ตรวจสอบว่าใช้การแจ้งเตือนทางอีเมลหรือไม่
     *
     * @return bool
     */
    public static function emailNotification()
    {
        return self::$cfg->salary_notification;
    }

    /**
     * AJAX endpoint สำหรับคำนวณเงินเดือน
     *
     * @param Request $request
     */
    public function ajax(Request $request)
    {
        $ret = ['success' => false];

        // ตรวจสอบ AJAX request
        if ($request->isReferer() && $request->isAjax()) {
            try {
                // รับข้อมูลจาก POST
                $salaryData = [
                    'basic_salary' => $request->post('basic_salary')->toDouble(),
                    'allowance' => $request->post('allowance')->toDouble(),
                    'overtime' => $request->post('overtime')->toDouble(),
                    'bonus' => $request->post('bonus')->toDouble(),
                    'deduction' => $request->post('deduction')->toDouble()
                ];

                // คำนวณเงินเดือน
                $result = self::calculateNetSalary($salaryData);

                $ret = [
                    'success' => true,
                    'income_tax' => $result['income_tax'],
                    'social_security' => $result['social_security'],
                    'net_salary' => $result['net_salary']
                ];

            } catch (Exception $e) {
                $ret['message'] = $e->getMessage();
            }
        } else {
            $ret['message'] = 'Invalid request';
        }

        // Return JSON
        echo json_encode($ret);
    }
}
