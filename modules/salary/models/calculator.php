<?php
/**
 * @filesource modules/salary/models/calculator.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Calculator;

/**
 * สูตรคำนวณเงินเดือน ภาษี และประกันสังคม
 *
 * ทุกหน้าที่บันทึกเงินเดือน (ฟอร์ม, นำเข้า CSV, คำนวณใหม่) เรียกใช้คลาสนี้ตัวเดียวกัน
 * เพื่อให้ผลลัพธ์ตรงกันเสมอ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค่าใช้จ่ายของเงินได้จากเงินเดือน (มาตรา 40(1)) หักได้ร้อยละ 50
     */
    const EXPENSE_RATE = 0.5;

    /**
     * ค่าใช้จ่ายของเงินได้จากเงินเดือน หักได้ไม่เกินปีละ 100,000 บาท
     */
    const EXPENSE_MAX = 100000;

    /**
     * ขั้นบันไดภาษีเงินได้บุคคลธรรมดา (เงินได้สุทธิต่อปี => อัตราภาษี)
     *
     * @var array
     */
    protected static $taxBrackets = [
        [150000, 0], // 0-150,000: ยกเว้น
        [300000, 0.05], // 150,001-300,000: 5%
        [500000, 0.10], // 300,001-500,000: 10%
        [750000, 0.15], // 500,001-750,000: 15%
        [1000000, 0.20], // 750,001-1,000,000: 20%
        [2000000, 0.25], // 1,000,001-2,000,000: 25%
        [5000000, 0.30], // 2,000,001-5,000,000: 30%
        [PHP_INT_MAX, 0.35] // เกิน 5,000,000: 35%
    ];

    /**
     * คำนวณภาษีเงินได้หัก ณ ที่จ่ายของเดือนนี้
     *
     * อัตราคงที่ : (รายได้ทั้งหมดของเดือน - รายการที่หักได้) x อัตรา
     *
     * ขั้นบันได (วิธีของกรมสรรพากรสำหรับเงินเดือน)
     * 1. ประมาณเงินได้ทั้งปีจากรายได้ประจำของเดือนนี้ x 12
     * 2. หักค่าใช้จ่ายร้อยละ 50 ไม่เกิน 100,000 บาท
     * 3. หักค่าลดหย่อนตามการตั้งค่า และรายการที่หักได้ x 12
     * 4. คิดภาษีขั้นบันไดของทั้งปี แล้วหาร 12
     * โบนัสเป็นเงินได้ครั้งเดียว ไม่นำไปคูณ 12 แต่หักภาษีส่วนที่เพิ่มขึ้นเพราะโบนัส
     * ทั้งจำนวนในเดือนที่จ่าย
     *
     * @param float $regularIncome รายได้ประจำของเดือน (ไม่รวมโบนัส)
     * @param float $bonus โบนัสที่จ่ายในเดือนนี้
     * @param float $deductible รายการที่หักได้ของเดือนนี้ (ประกันสังคม, รายการหักอื่นๆ)
     * @param float|null $taxRate เปอร์เซ็นต์ภาษีคงที่ null = ใช้ค่าจากการตั้งค่า, 0 = ขั้นบันได
     *
     * @return float ภาษีที่ต้องหักในเดือนนี้
     */
    public static function calculateIncomeTax($regularIncome, $bonus = 0, $deductible = 0, $taxRate = null)
    {
        if ($taxRate === null) {
            $taxRate = self::$cfg->salary_tax;
        }

        // กำหนดเปอร์เซ็นต์ไว้ = คิดแบบคงที่, 0 = คิดแบบขั้นบันไดตามกฎหมายไทย
        if ($taxRate > 0) {
            $taxableIncome = max(0, $regularIncome + $bonus - $deductible);

            return round($taxableIncome * ($taxRate / 100), self::$cfg->salary_decimals);
        }

        $yearlyIncome = $regularIncome * 12;
        $yearlyDeductible = $deductible * 12;
        $yearlyTax = self::calculateYearlyTax($yearlyIncome, $yearlyDeductible);
        $tax = $yearlyTax / 12;
        if ($bonus > 0) {
            $tax += self::calculateYearlyTax($yearlyIncome + $bonus, $yearlyDeductible) - $yearlyTax;
        }

        return round($tax, self::$cfg->salary_decimals);
    }

    /**
     * คำนวณภาษีเงินได้ทั้งปีแบบขั้นบันได
     *
     * @param float $yearlyIncome เงินได้ทั้งปี
     * @param float $yearlyDeductible รายการที่หักได้ทั้งปี (ไม่รวมค่าลดหย่อนจากการตั้งค่า)
     *
     * @return float
     */
    public static function calculateYearlyTax($yearlyIncome, $yearlyDeductible = 0)
    {
        $expense = min($yearlyIncome * self::EXPENSE_RATE, self::EXPENSE_MAX);
        $netIncome = $yearlyIncome - $expense - (float) self::$cfg->salary_tax_allowance - $yearlyDeductible;
        if ($netIncome <= 0) {
            return 0;
        }

        $tax = 0;
        $previousBracket = 0;
        foreach (self::$taxBrackets as list($bracket, $rate)) {
            if ($netIncome <= $bracket) {
                $tax += ($netIncome - $previousBracket) * $rate;
                break;
            }
            $tax += ($bracket - $previousBracket) * $rate;
            $previousBracket = $bracket;
        }

        return $tax;
    }

    /**
     * คำนวณประกันสังคม (ส่วนของพนักงาน)
     *
     * @param float $grossSalary รายได้รวม
     * @param float|null $socialRate เปอร์เซ็นต์ประกันสังคม null = ใช้ค่าจากการตั้งค่า
     *
     * @return float
     */
    public static function calculateSocialSecurity($grossSalary, $socialRate = null)
    {
        if ($socialRate === null) {
            $socialRate = self::$cfg->salary_social;
        }

        // คิดจากฐานเงินเดือนไม่เกินเพดานที่กำหนด
        $salaryForCalculation = min($grossSalary, self::$cfg->salary_social_max);

        return round($salaryForCalculation * ($socialRate / 100), self::$cfg->salary_decimals);
    }

    /**
     * คำนวณประกันสังคม (ส่วนของนายจ้าง)
     *
     * @param float $grossSalary รายได้รวม
     * @param float|null $employerRate เปอร์เซ็นต์ประกันสังคมนายจ้าง null = ใช้ค่าจากการตั้งค่า
     *
     * @return float
     */
    public static function calculateEmployerSocialSecurity($grossSalary, $employerRate = null)
    {
        if ($employerRate === null) {
            $employerRate = self::$cfg->salary_social_employer;
        }

        $salaryForCalculation = min($grossSalary, self::$cfg->salary_social_max);

        return round($salaryForCalculation * ($employerRate / 100), self::$cfg->salary_decimals);
    }

    /**
     * คำนวณค่าล่วงเวลา
     *
     * @param float $hourlyRate อัตราค่าแรงต่อชั่วโมง
     * @param float $overtimeHours จำนวนชั่วโมงล่วงเวลา
     * @param float|null $overtimeRate ตัวคูณค่าล่วงเวลา null = ใช้ค่าจากการตั้งค่า
     *
     * @return float
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
     * ไม่ปัดเศษ ปัดครั้งเดียวที่ค่าล่วงเวลา ไม่เช่นนั้นเศษที่ปัดทิ้งจะถูกคูณตามจำนวนชั่วโมง
     *
     * @param float $basicSalary ฐานเงินเดือน
     * @param int|null $workingDays วันทำงานต่อเดือน null = ใช้ค่าจากการตั้งค่า
     * @param float|null $workingHours ชั่วโมงทำงานต่อวัน null = ใช้ค่าจากการตั้งค่า
     *
     * @return float
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
        if ($totalHours <= 0) {
            return 0;
        }

        return $basicSalary / $totalHours;
    }

    /**
     * คำนวณเงินเดือนสุทธิจากรายการรายได้และรายหัก
     *
     * ค่าล่วงเวลา : ระบุ overtime_hours = คิดจากฐานเงินเดือน, ไม่ระบุ = ใช้ overtime ที่ส่งมา
     * รายได้รวม = ฐานเงินเดือน + เบี้ยเลี้ยง + โบนัส + ค่าล่วงเวลา
     * ประกันสังคม คิดจากรายได้รวมก่อนหักภาษี
     * ภาษี คิดจากรายได้หลังหักประกันสังคมและรายการหักอื่นๆ (ดู calculateIncomeTax)
     * ปิดการคำนวณอัตโนมัติ = ใช้ social_security และ tax ที่ส่งมาตามจริง
     *
     * @param array $salaryData
     *
     * @return array
     */
    public static function calculateNetSalary($salaryData)
    {
        $decimals = self::$cfg->salary_decimals;
        $basicSalary = (float) ($salaryData['basic_salary'] ?? 0);
        $allowance = (float) ($salaryData['allowance'] ?? 0);
        $bonus = (float) ($salaryData['bonus'] ?? 0);
        $deduction = (float) ($salaryData['deduction'] ?? 0);
        $overtimeHours = max(0, (float) ($salaryData['overtime_hours'] ?? 0));
        if ($overtimeHours > 0) {
            $overtime = self::calculateOvertime(self::calculateHourlyRate($basicSalary), $overtimeHours);
        } else {
            $overtime = (float) ($salaryData['overtime'] ?? 0);
        }

        $totalIncome = $basicSalary + $allowance + $bonus + $overtime;
        if (self::autoCalculate()) {
            $socialSecurity = self::calculateSocialSecurity($totalIncome, $salaryData['social_rate'] ?? null);
            $incomeTax = self::calculateIncomeTax(
                $totalIncome - $bonus,
                $bonus,
                $socialSecurity + $deduction,
                $salaryData['tax_rate'] ?? null
            );
        } else {
            $socialSecurity = round((float) ($salaryData['social_security'] ?? 0), $decimals);
            $incomeTax = round((float) ($salaryData['tax'] ?? 0), $decimals);
        }
        $totalDeductions = $incomeTax + $socialSecurity + $deduction;

        return [
            'basic_salary' => $basicSalary,
            'allowance' => $allowance,
            'bonus' => $bonus,
            'overtime' => $overtime,
            'overtime_hours' => $overtimeHours,
            'total_income' => $totalIncome,
            'taxable_income' => max(0, $totalIncome - $socialSecurity - $deduction),
            'income_tax' => $incomeTax,
            'social_security' => $socialSecurity,
            'other_deductions' => $deduction,
            'total_deductions' => $totalDeductions,
            'net_salary' => round($totalIncome - $totalDeductions, $decimals),
            'employer_social_security' => self::calculateEmployerSocialSecurity($totalIncome)
        ];
    }

    /**
     * ต้องอนุมัติก่อนหรือไม่
     *
     * @return bool
     */
    public static function requiresApproval()
    {
        return !empty(self::$cfg->salary_require_approval);
    }

    /**
     * คำนวณประกันสังคมและภาษีให้อัตโนมัติหรือไม่
     * ปิด = ผู้ดูแลกรอกหรือนำเข้าประกันสังคมและภาษีเอง (คำนวณมาจากโปรแกรมอื่น)
     *
     * @return bool
     */
    public static function autoCalculate()
    {
        return !empty(self::$cfg->salary_auto_calculate);
    }

    /**
     * แจ้งเตือนเมื่ออนุมัติหรือไม่
     *
     * @return bool
     */
    public static function emailNotification()
    {
        return !empty(self::$cfg->salary_notification);
    }
}
