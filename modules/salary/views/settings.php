<?php
/**
 * @filesource modules/salary/views/settings.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Settings;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=salary-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มตั้งค่า
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/salary/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-percent',
            'title' => '{LNG_Tax and Deductions}'
        ]);
        // salary_tax
        $fieldset->add('currency', [
            'id' => 'salary_tax',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Income Tax} %',
            'comment' => '{LNG_Fill in 0 to use the calculation of steps according to Thai law. Or the desired percentage (such as 3 for 3%)}',
            'step' => 0.01,
            'min' => 0,
            'max' => 100,
            'value' => isset(self::$cfg->salary_tax) ? self::$cfg->salary_tax : 0
        ]);
        // salary_social
        $fieldset->add('currency', [
            'id' => 'salary_social',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Social Security} %',
            'comment' => '{LNG_Social security percentage (employee contribution)}',
            'step' => 0.01,
            'min' => 0,
            'max' => 100,
            'value' => isset(self::$cfg->salary_social) ? self::$cfg->salary_social : 5
        ]);
        // salary_social_employer
        $fieldset->add('currency', [
            'id' => 'salary_social_employer',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Social Security Employer} %',
            'comment' => '{LNG_Social security percentage (employer contribution)}',
            'step' => 0.01,
            'min' => 0,
            'max' => 100,
            'value' => isset(self::$cfg->salary_social_employer) ? self::$cfg->salary_social_employer : 5
        ]);
        // salary_social_max
        $fieldset->add('currency', [
            'id' => 'salary_social_max',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Social Security Max Amount}',
            'comment' => '{LNG_Maximum amount for social security calculation}',
            'value' => isset(self::$cfg->salary_social_max) ? self::$cfg->salary_social_max : 15000
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-settings',
            'title' => '{LNG_Calculation Settings}'
        ]);
        // salary_overtime_rate
        $fieldset->add('currency', [
            'id' => 'salary_overtime_rate',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'item',
            'label' => '{LNG_Overtime Rate}',
            'comment' => '{LNG_Overtime hourly rate multiplier (normal rate x this value)}',
            'step' => 0.1,
            'min' => 1,
            'max' => 10,
            'value' => isset(self::$cfg->salary_overtime_rate) ? self::$cfg->salary_overtime_rate : 1.5
        ]);
        // salary_working_days
        $fieldset->add('number', [
            'id' => 'salary_working_days',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Working Days per Month}',
            'comment' => '{LNG_Standard working days per month for calculation}',
            'min' => 1,
            'max' => 31,
            'value' => isset(self::$cfg->salary_working_days) ? self::$cfg->salary_working_days : 22
        ]);
        // salary_working_hours
        $fieldset->add('number', [
            'id' => 'salary_working_hours',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'item',
            'label' => '{LNG_Working Hours per Day}',
            'comment' => '{LNG_Standard working hours per day}',
            'step' => 0.5,
            'min' => 1,
            'max' => 24,
            'value' => isset(self::$cfg->salary_working_hours) ? self::$cfg->salary_working_hours : 8
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-shield',
            'title' => '{LNG_Approval Settings}'
        ]);
        // salary_require_approval
        $fieldset->add('select', [
            'id' => 'salary_require_approval',
            'labelClass' => 'g-input icon-valid',
            'itemClass' => 'item',
            'label' => '{LNG_Require Approval}',
            'comment' => '{LNG_Require approval before salary payment}',
            'options' => Language::get('BOOLEANS'),
            'value' => isset(self::$cfg->salary_require_approval) ? self::$cfg->salary_require_approval : 1
        ]);
        // salary_auto_calculate
        $fieldset->add('select', [
            'id' => 'salary_auto_calculate',
            'labelClass' => 'g-input icon-calculator',
            'itemClass' => 'item',
            'label' => '{LNG_Auto Calculate}',
            'comment' => '{LNG_Automatically calculate salary components}',
            'options' => Language::get('BOOLEANS'),
            'value' => isset(self::$cfg->salary_auto_calculate) ? self::$cfg->salary_auto_calculate : 1
        ]);
        // salary_notification
        $fieldset->add('select', [
            'id' => 'salary_notification',
            'labelClass' => 'g-input icon-email',
            'itemClass' => 'item',
            'label' => '{LNG_Email Notification}',
            'comment' => '{LNG_Send email notification when salary is approved}',
            'options' => Language::get('BOOLEANS'),
            'value' => isset(self::$cfg->salary_notification) ? self::$cfg->salary_notification : 1
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-excel',
            'title' => '{LNG_Import/Export Settings}'
        ]);
        // csv_language
        $fieldset->add('select', [
            'id' => 'csv_language',
            'labelClass' => 'g-input icon-excel',
            'itemClass' => 'item',
            'label' => '{LNG_Language}',
            'comment' => '{LNG_The language code of the CSV file used for data import-export}',
            'options' => Language::get('CSV_ENCODING'),
            'value' => isset(self::$cfg->csv_language) ? self::$cfg->csv_language : 'UTF-8'
        ]);
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);
        // คืนค่า HTML
        return $form->render();
    }
}
