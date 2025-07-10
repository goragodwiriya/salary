<?php
/**
 * @filesource modules/hr/views/Termination.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Termination;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Html;
use Kotchasan\Form;
use Kotchasan\Date;
use Kotchasan\Language;

/**
 * View for displaying termination records list and form.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * Render the termination records list using DataTable.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML for the DataTable.
     */
    public function render(Request $request, $login)
    {
        $params = $request->getQueryParams();
        $params['module'] = 'hr-termination';

        $table = new DataTable([
            'uri' => $request->getUri()->withParams($params)->toString(),
            'model' => \Hr\Model\Termination::toDataTable(),
            'perPage' => $request->cookie('hrTermination_perPage', 30)->toInt(),
            'sort' => $request->cookie('hrTermination_sort', 'id desc')->toString(),
            'onRow' => [$this, 'onRow'],
            'buttons' => [
                'edit' => [
                    'class' => 'icon-edit button green',
                    'href' => $request->getUri()->withParams(['action' => 'edit', 'id' => ':id'])->toString(),
                    'text' => '{LNG_Edit}'
                ]
            ],
            'headers' => [
                'id' => ['text' => '{LNG_ID}'],
                'employee_code' => ['text' => '{LNG_Employee Code}', 'sort' => 'employee_code'],
                'employee_name' => ['text' => '{LNG_Employee Name}', 'sort' => 'employee_name'],
                'notice_date' => ['text' => '{LNG_Notice Date}', 'sort' => 'notice_date', 'class' => 'center'],
                'last_day_date' => ['text' => '{LNG_Last Day Worked}', 'sort' => 'last_day_date', 'class' => 'center'],
                'termination_type' => ['text' => '{LNG_Type}', 'sort' => 'termination_type', 'class' => 'center'],
                'reason' => ['text' => '{LNG_Reason}']
            ],
            'cols' => [
                'notice_date' => ['class' => 'center'],
                'last_day_date' => ['class' => 'center'],
                'termination_type' => ['class' => 'center'],
            ]
        ]);

        setcookie('hrTermination_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('hrTermination_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);

        return $table->render();
    }

    /**
     * Callback function for formatting each row in DataTable.
     *
     * @param array $item The data for the current row.
     * @param int $o The row index.
     * @param object $prop Properties of the TR element.
     * @return array The modified $item.
     */
    public function onRow($item, $o, $prop)
    {
        $item['notice_date'] = $item['notice_date'] ? Date::format($item['notice_date'], 'd M Y') : '';
        $item['last_day_date'] = Date::format($item['last_day_date'], 'd M Y');
        $item['termination_type'] = Language::get('TERMINATION_TYPES', '', $item['termination_type']); // Assumes TERMINATION_TYPES in language
        $item['reason'] = nl2br(htmlspecialchars($item['reason'])); // Basic formatting for display
        return $item;
    }

    /**
     * Render the form for adding or editing a termination record.
     *
     * @param Request $request
     * @param object|null $termination Termination data object for editing, or null for new.
     * @param array $login Login information.
     * @return string HTML for the form.
     */
    public function renderForm(Request $request, $termination, $login)
    {
        $form = Html::create('form', [
            'id' => 'termination_form',
            'class' => 'setup_frm',
            'method' => 'post',
            'action' => 'index.php/hr/model/termination/submit',
            'autocomplete' => 'off',
            'ajax' => true,
            'token' => true
        ]);

        $fieldset = $form->add('fieldset');
        $title = $termination ? '{LNG_Edit Termination Record}' : '{LNG_Add New Termination Record}';
        $fieldset->add('legend', ['innerHTML' => '<span>'.$title.'</span>']);

        if ($termination) {
            $fieldset->add('hidden', ['id' => 'id', 'name' => 'id', 'value' => $termination->id]);
        }

        // Employee (Dropdown)
        $employees = \Hr\Model\Termination::getEmployeesForSelect(); // Or \Hr\Model\Employee::getEmployeesForSelect()
        $fieldset->add('select', [
            'id' => 'employee_id',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Employee}',
            'options' => [0 => '{LNG_Please select an employee}'] + $employees,
            'value' => isset($termination->employee_id) ? $termination->employee_id : 0,
            'required' => true,
            // Add 'disabled' => $termination ? true : false if employee cannot be changed on edit
        ]);

        // Notice Date
        $fieldset->add('date', [
            'id' => 'notice_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Notice Date}',
            'value' => isset($termination->notice_date) ? $termination->notice_date : ''
        ]);

        // Last Day Worked
        $fieldset->add('date', [
            'id' => 'last_day_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Last Day Worked}',
            'value' => isset($termination->last_day_date) ? $termination->last_day_date : date('Y-m-d'),
            'required' => true
        ]);

        // Termination Type (Dropdown)
        // Example: ['voluntary' => 'Voluntary', 'involuntary' => 'Involuntary']
        $termination_types = Language::get('TERMINATION_TYPES');
        $fieldset->add('select', [
            'id' => 'termination_type',
            'labelClass' => 'g-input icon-star0', // Choose appropriate icon
            'itemClass' => 'item',
            'label' => '{LNG_Termination Type}',
            'options' => ['' => '{LNG_Please select}'] + $termination_types,
            'value' => isset($termination->termination_type) ? $termination->termination_type : '',
            'required' => true
        ]);

        // Reason for Leaving
        $fieldset->add('textarea', [
            'id' => 'reason',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Reason for Leaving}',
            'rows' => 5,
            'value' => isset($termination->reason) ? $termination->reason : ''
        ]);

        $fieldset = $form->add('fieldset', ['class' => 'submit']);
        $fieldset->add('submit', [
            'class' => 'button ok large icon-save',
            'value' => '{LNG_Save}'
        ]);
        $fieldset->add('a', [
            'href' => $request->getUri()->withParams(['module' => 'hr-termination'])->toString(),
            'class' => 'button cancel large icon-cancel',
            'innerHTML' => '{LNG_Cancel}'
        ]);

        $form->script('initEditInPlace("termination_form");');

        return $form->render();
    }
}
?>
