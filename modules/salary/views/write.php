<?php
/**
 * @filesource modules/salary/views/write.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Write;

use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มเพิ่ม/แก้ไขข้อมูลเงินเดือน
     *
     * @param Request $request
     * @param object   $salary
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $salary, $login)
    {
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/salary/model/write/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-money',
            'title' => '{LNG_Salary information}'
        ]);
        $year_offset = Language::get('YEAR_OFFSET');
        if (isset($salary->id) && $salary->id > 0) {
            // แก้ไขข้อมูลเงินเดือน
            $fieldset->add('text', [
                'id' => 'salary_member_id',
                'labelClass' => 'g-input icon-user',
                'itemClass' => 'item',
                'label' => '{LNG_Name}',
                'value' => $salary->name.' ('.$salary->id_card.')',
                'readonly' => true
            ]);
            $groups = $fieldset->add('groups');
            // ปี
            $groups->add('text', [
                'id' => 'salary_year',
                'labelClass' => 'g-input icon-calendar',
                'itemClass' => 'width50',
                'label' => '{LNG_Year} *',
                'value' => $salary->year + $year_offset,
                'readonly' => true
            ]);
            // เดือน
            $groups->add('text', [
                'id' => 'salary_month',
                'labelClass' => 'g-input icon-calendar',
                'itemClass' => 'width50',
                'label' => '{LNG_Month} *',
                'readonly' => true,
                'value' => Language::get('MONTH_LONG', '', (int) $salary->month)
            ]);
        } else {
            // เลือกสมาชิกสำหรับการเพิ่มใหม่
            $fieldset->add('select', [
                'id' => 'salary_member_id',
                'labelClass' => 'g-input icon-user',
                'itemClass' => 'item',
                'label' => '{LNG_Name} *',
                'options' => \Salary\Write\Model::getMembers(),
                'value' => isset($salary->member_id) ? $salary->member_id : 0
            ]);
            $groups = $fieldset->add('groups');
            // ปี
            $years = [];
            $currentYear = date('Y');
            for ($i = 2024; $i <= $currentYear + 1; $i++) {
                $years[$i] = $i + $year_offset;
            }
            $groups->add('select', [
                'id' => 'salary_year',
                'labelClass' => 'g-input icon-calendar',
                'itemClass' => 'width50',
                'label' => '{LNG_Year} *',
                'value' => $salary->year,
                'options' => $years
            ]);

            // เดือน
            $groups->add('select', [
                'id' => 'salary_month',
                'labelClass' => 'g-input icon-calendar',
                'itemClass' => 'width50',
                'label' => '{LNG_Month} *',
                'options' => Language::get('MONTH_LONG'),
                'value' => (int) $salary->month
            ]);
        }
        // ฐานเงินเดือน
        $fieldset->add('currency', [
            'id' => 'salary_basic_salary',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Basic Salary}',
            'value' => isset($salary->basic_salary) ? $salary->basic_salary : 0,
            'step' => '0.01'
        ]);
        // เบี้ยเลี้ยง
        $fieldset->add('currency', [
            'id' => 'salary_allowance',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Allowance}',
            'value' => isset($salary->allowance) ? $salary->allowance : 0,
            'step' => '0.01'
        ]);
        // ค่าล่วงเวลา
        $fieldset->add('currency', [
            'id' => 'salary_overtime',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Overtime}',
            'value' => isset($salary->overtime) ? $salary->overtime : 0,
            'step' => '0.01'
        ]);

        // โบนัส
        $fieldset->add('currency', [
            'id' => 'salary_bonus',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Bonus}',
            'value' => isset($salary->bonus) ? $salary->bonus : 0,
            'step' => '0.01'
        ]);
        // หักอื่นๆ
        $fieldset->add('currency', [
            'id' => 'salary_deduction',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Deduction}',
            'value' => isset($salary->deduction) ? $salary->deduction : 0,
            'step' => '0.01'
        ]);
        // ประกันสังคม
        $fieldset->add('currency', [
            'id' => 'salary_social_security',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Social Security}',
            'value' => isset($salary->social_security) ? $salary->social_security : 0,
            'step' => '0.01',
            'readonly' => true,
            'comment' => '{LNG_Auto calculated from settings}'
        ]);

        // ภาษี
        $fieldset->add('currency', [
            'id' => 'salary_tax',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Income Tax}',
            'value' => isset($salary->tax) ? $salary->tax : 0,
            'step' => '0.01',
            'readonly' => true,
            'comment' => '{LNG_Auto calculated from settings}'
        ]);

        // เงินเดือนสุทธิ (แสดงผล)
        $fieldset->add('currency', [
            'id' => 'salary_net_salary',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Net Salary}',
            'value' => isset($salary->net_salary) ? $salary->net_salary : 0,
            'step' => '0.01',
            'readonly' => true,
            'comment' => '{LNG_Calculated automatically}'
        ]);
        // หมายเหตุ
        $fieldset->add('textarea', [
            'id' => 'salary_remark',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Remark}',
            'value' => isset($salary->remark) ? $salary->remark : ''
        ]);
        $fieldset->add('hidden', [
            'id' => 'salary_id',
            'value' => $salary->id
        ]);

        // Submit button fieldset
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);

        // Javascript
        $form->script('initSalaryWrite();');
        // คืนค่า HTML
        return $form->render();
    }
}
