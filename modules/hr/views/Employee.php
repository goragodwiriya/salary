<?php
/**
 * @filesource modules/hr/views/Employee.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Employee; // Namespace based on Salary module (Module\ControllerName)

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Html;
use Kotchasan\Form;
use Kotchasan\Date;
use Kotchasan\Language;

/**
 * View for displaying employee list and form.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * Render the employee list using DataTable.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML for the DataTable.
     */
    public function render(Request $request, $login)
    {
        $params = $request->getQueryParams();
        $params['module'] = 'hr-employee'; // Current module for URL building

        // DataTable setup
        $table = new DataTable([
            'uri' => $request->getUri()->withParams($params)->toString(),
            'model' => \Hr\Model\Employee::toDataTable(['status' => $request->request('status', 'active')->toString()]),
            'perPage' => $request->cookie('hrEmployee_perPage', 30)->toInt(),
            'sort' => $request->cookie('hrEmployee_sort', 'id desc')->toString(),
            'onRow' => [$this, 'onRow'],
            'buttons' => [
                'edit' => [
                    'class' => 'icon-edit button green',
                    'href' => $request->getUri()->withParams(['action' => 'edit', 'id' => ':id'])->toString(),
                    'text' => '{LNG_Edit}'
                ],
                // Add delete button later if required by plan
            ],
            'headers' => [
                'id' => ['text' => '{LNG_ID}'],
                'employee_code' => ['text' => '{LNG_Employee Code}', 'sort' => 'employee_code'],
                'first_name' => ['text' => '{LNG_First Name}', 'sort' => 'first_name'],
                'last_name' => ['text' => '{LNG_Last Name}', 'sort' => 'last_name'],
                'department' => ['text' => '{LNG_Department}', 'sort' => 'department'],
                'position' => ['text' => '{LNG_Position}', 'sort' => 'position'],
                'start_date' => ['text' => '{LNG_Start Date}', 'sort' => 'start_date', 'class' => 'center'],
                'status' => ['text' => '{LNG_Status}', 'sort' => 'status', 'class' => 'center'],
                'salary' => ['text' => '{LNG_Salary}', 'sort' => 'salary', 'class' => 'right']
            ],
            'cols' => [
                'start_date' => ['class' => 'center'],
                'status' => ['class' => 'center'],
                'salary' => ['class' => 'right']
            ],
            'actions' => [
                // Example for bulk actions if needed later
                // [
                //     'id' => 'action',
                //     'class' => 'ok',
                //     'options' => [
                //         'delete' => '{LNG_Delete}'
                //     ],
                //     'value' => 'delete'
                // ]
            ],
             // URL for form submissions from actions (e.g. bulk delete)
            'action' => 'index.php/hr/model/employee/action', // This would be for bulk actions, not individual row submits
            'actionCallback' => 'dataTableActionCallback', // JS function to handle response
            'rowClass' => function ($item) {
                return $item['status'] == 'inactive' || $item['status'] == 'terminated' ? 'disabled' : '';
            }
        ]);

        // Save cookie
        setcookie('hrEmployee_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('hrEmployee_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);

        // Filter
        $filter = $table->addFilter(array(
            'name' => 'status',
            'text' => '{LNG_Status}',
            'options' => array('all' => '{LNG_All items}') + Language::get('EMPLOYEE_STATUS'), // Assuming EMPLOYEE_STATUS is defined in language files
            'value' => $request->request('status', 'active')->text()
        ));


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
        $item['start_date'] = Date::format($item['start_date'], 'd M Y');
        $item['salary'] = \Kotchasan\Number::format($item['salary']);
        $item['status'] = Language::get('EMPLOYEE_STATUS_COLOR', '', $item['status']) ? '<span class="status'.$item['status'].'">'.Language::get('EMPLOYEE_STATUS', '', $item['status']).'</span>' : Language::get('EMPLOYEE_STATUS', '', $item['status']);

        return $item;
    }

    /**
     * Render the form for adding or editing an employee.
     *
     * @param Request $request
     * @param object|null $employee Employee data object for editing, or null for new.
     * @param array $login Login information.
     * @return string HTML for the form.
     */
    public function renderForm(Request $request, $employee, $login)
    {
        $form = Html::create('form', [
            'id' => 'employee_form',
            'class' => 'setup_frm',
            'method' => 'post',
            // The action URL should point to the model's submit method.
            // The framework typically routes index.php/module/model/controller/method or similar.
            // For a model named Hr\Model\Employee and method submit, it might be:
            'action' => 'index.php/hr/model/employee/submit',
            'autocomplete' => 'off',
            'ajax' => true, // Enable AJAX form submission via Gcms.js
            'token' => true // Add CSRF token
        ]);

        $fieldset = $form->add('fieldset');
        $title = $employee ? '{LNG_Edit Employee}' : '{LNG_Add New Employee}';
        $legend = $fieldset->add('legend', ['innerHTML' => '<span>'.$title.'</span>']);

        // Employee ID (hidden)
        if ($employee) {
            $fieldset->add('hidden', ['id' => 'id', 'name' => 'id', 'value' => $employee->id]);
        }

        // Employee Code
        $fieldset->add('text', [
            'id' => 'employee_code',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'item',
            'label' => '{LNG_Employee Code}',
            'maxlength' => 50,
            'value' => isset($employee->employee_code) ? $employee->employee_code : '',
            'autofocus' => true,
            'required' => true
        ]);

        // First Name
        $fieldset->add('text', [
            'id' => 'first_name',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_First Name}',
            'maxlength' => 100,
            'value' => isset($employee->first_name) ? $employee->first_name : '',
            'required' => true
        ]);

        // Last Name
        $fieldset->add('text', [
            'id' => 'last_name',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Last Name}',
            'maxlength' => 100,
            'value' => isset($employee->last_name) ? $employee->last_name : '',
            'required' => true
        ]);

        // Department
        $fieldset->add('text', [
            'id' => 'department',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'item',
            'label' => '{LNG_Department}',
            'maxlength' => 100,
            'value' => isset($employee->department) ? $employee->department : ''
        ]);

        // Position
        $fieldset->add('text', [
            'id' => 'position',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Position}',
            'maxlength' => 100,
            'value' => isset($employee->position) ? $employee->position : ''
        ]);

        // Start Date
        $fieldset->add('date', [
            'id' => 'start_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Start Date}',
            'value' => isset($employee->start_date) ? $employee->start_date : date('Y-m-d')
        ]);

        // Salary
        $fieldset->add('number', [
            'id' => 'salary',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Salary}',
            'value' => isset($employee->salary) ? $employee->salary : ''
        ]);

        // Manager ID (Could be a select dropdown populated from employees)
        // For now, a simple number input. This should be improved later.
        $fieldset->add('number', [
            'id' => 'manager_id',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Manager ID}',
            'comment' => '{LNG_Enter the ID of the manager}',
            'value' => isset($employee->manager_id) ? $employee->manager_id : ''
        ]);

        // Status
        $fieldset->add('select', [
            'id' => 'status',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('EMPLOYEE_STATUS'), // Example: ['active' => 'Active', 'inactive' => 'Inactive', 'terminated' => 'Terminated']
            'value' => isset($employee->status) ? $employee->status : 'active'
        ]);

        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // Submit button
        $fieldset->add('submit', [
            'class' => 'button ok large icon-save',
            'value' => '{LNG_Save}'
        ]);
        // Cancel button
        $fieldset->add('a', [
            'href' => $request->getUri()->withParams(['module' => 'hr-employee'])->toString(), // Link to employee list
            'class' => 'button cancel large icon-cancel',
            'innerHTML' => '{LNG_Cancel}'
        ]);

        // Tells Gcms.js about this form
        $form->script('initEditInPlace("employee_form");');

        return $form->render();
    }
}
?>
