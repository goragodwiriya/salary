<?php
/**
 * @filesource modules/salary/views/slip.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Slip;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Number;

/**
 * module=salary-slip
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงสลิปเงินเดือน
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $id, $login)
    {
        // รายการที่เลือก
        $salary = \Salary\Index\Model::get($id);
        if (!$salary || !$login) {
            return '';
        }

        if (!Login::checkPermission($login, 'can_manage_salary') && $salary->member_id != $login['id']) {
            // ถ้าไม่ใช่ผู้ดูแลระบบและไม่ใช่เจ้าของข้อมูล
            return '';
        }

        // สร้างสลิปเงินเดือน
        $content = Html::create('div', [
            'class' => 'salary-slip',
            'id' => 'salary-slip'
        ]);

        // ส่วนหัว
        $header = $content->add('div', [
            'class' => 'slip-header'
        ]);
        $header->add('h1', [
            'innerHTML' => '{LNG_Salary slip}'
        ]);
        $header->add('h2', [
            'innerHTML' => self::$cfg->web_title
        ]);

        // ข้อมูลพนักงาน
        $employee_info = $content->add('div', [
            'class' => 'employee-info'
        ]);
        $employee_info->add('div', [
            'innerHTML' => '<strong>{LNG_Name}:</strong> '.$salary->name
        ]);
        if (!empty($salary->id_card)) {
            $employee_info->add('div', [
                'innerHTML' => '<strong>{LNG_Identification No.}:</strong> '.$salary->id_card
            ]);
        }
        if (!empty($salary->position)) {
            $employee_info->add('div', [
                'innerHTML' => '<strong>{LNG_Position}:</strong> '.$salary->position
            ]);
        }
        if (!empty($salary->department)) {
            $employee_info->add('div', [
                'innerHTML' => '<strong>{LNG_Department}:</strong> '.$salary->department
            ]);
        }

        // ข้อมูลเงินเดือน
        $salary_info = $content->add('div', [
            'class' => 'salary-info'
        ]);
        $months = Language::get('MONTH_LONG');
        $year_offset = Language::get('YEAR_OFFSET');
        $salary_info->add('h3', [
            'innerHTML' => Language::replace('Monthly salary :month :year', [':month' => $months[(int) $salary->month], ':year' => $salary->year + $year_offset])
        ]);

        // ตารางรายได้
        $income_table = $salary_info->add('table', [
            'class' => 'salary-table'
        ]);
        $thead = $income_table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['colspan' => 2, 'innerHTML' => '{LNG_Income}']);

        $tbody = $income_table->add('tbody');

        // ฐานเงินเดือน
        if ($salary->basic_salary > 0) {
            $tr = $tbody->add('tr');
            $tr->add('td', ['innerHTML' => '{LNG_Basic Salary}']);
            $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->basic_salary)]);
        }

        // เบี้ยเลี้ยง
        if ($salary->allowance > 0) {
            $tr = $tbody->add('tr');
            $tr->add('td', ['innerHTML' => '{LNG_Allowance}']);
            $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->allowance)]);
        }

        // ค่าล่วงเวลา
        if ($salary->overtime > 0) {
            $tr = $tbody->add('tr');
            $tr->add('td', ['innerHTML' => '{LNG_Overtime}']);
            $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->overtime)]);
        }

        // โบนัส
        if ($salary->bonus > 0) {
            $tr = $tbody->add('tr');
            $tr->add('td', ['innerHTML' => '{LNG_Bonus}']);
            $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->bonus)]);
        }

        // รวมรายได้
        $total_income = $salary->basic_salary + $salary->allowance + $salary->overtime + $salary->bonus;
        $tr = $tbody->add('tr');
        $tr->add('td', ['innerHTML' => '<strong>{LNG_Total income}</strong>']);
        $tr->add('td', ['class' => 'amount total', 'innerHTML' => '<strong>'.Number::format($total_income).'</strong>']);

        // ตารางรายหัก
        if ($salary->deduction > 0 || $salary->social_security > 0 || $salary->tax > 0) {
            $deduction_table = $salary_info->add('table', [
                'class' => 'salary-table'
            ]);
            $thead = $deduction_table->add('thead');
            $tr = $thead->add('tr');
            $tr->add('th', ['colspan' => 2, 'innerHTML' => '{LNG_Deduction}']);

            $tbody = $deduction_table->add('tbody');

            // หักอื่นๆ
            if ($salary->deduction > 0) {
                $tr = $tbody->add('tr');
                $tr->add('td', ['innerHTML' => '{LNG_Deduction}']);
                $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->deduction)]);
            }

            // ประกันสังคม
            if ($salary->social_security > 0) {
                $tr = $tbody->add('tr');
                $tr->add('td', ['innerHTML' => '{LNG_Social Security}']);
                $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->social_security)]);
            }

            // ภาษี
            if ($salary->tax > 0) {
                $tr = $tbody->add('tr');
                $tr->add('td', ['innerHTML' => '{LNG_Income Tax}']);
                $tr->add('td', ['class' => 'amount', 'innerHTML' => Number::format($salary->tax)]);
            }

            // รวมรายหัก
            $total_deduction = $salary->deduction + $salary->social_security + $salary->tax;
            $tr = $tbody->add('tr');
            $tr->add('td', ['innerHTML' => '<strong>{LNG_Total deductions}</strong>']);
            $tr->add('td', ['class' => 'amount total', 'innerHTML' => '<strong>'.Number::format($total_deduction).'</strong>']);
        }

        // เงินสุทธิ
        $net_table = $salary_info->add('table', [
            'class' => 'salary-table net-salary'
        ]);
        $tbody = $net_table->add('tbody');
        $tr = $tbody->add('tr');
        $tr->add('td', ['innerHTML' => '<strong>{LNG_Net Salary}</strong>']);
        $tr->add('td', ['class' => 'amount net', 'innerHTML' => '<strong>'.Number::format($salary->net_salary).' บาท</strong>']);

        // หมายเหตุ
        if (!empty($salary->remark)) {
            $content->add('div', [
                'class' => 'remark',
                'innerHTML' => '<strong>{LNG_Remark}:</strong> '.$salary->remark
            ]);
        }

        $content->add('div', [
            'class' => 'print-button',
            'innerHTML' => '<button type="button" class="button print large icon-print" onclick="printSalarySlip()">{LNG_Print}</button>'
        ]);

        return $content->render();
    }
}
