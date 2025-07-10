<?php
/**
 * @filesource modules/hr/controllers/Employee.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Employee; // Namespace based on Salary module (Module\ControllerName)

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Controller for managing employees.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * Display employee list or employee add/edit form.
     *
     * @param Request $request
     * @return string
     */
    public function render(Request $request)
    {
        // Check if the user is a logged-in member
        $login = Login::isMember();
        if (!$login) {
            // Not logged in, redirect to login page or show error
            return \Index\Error\Controller::execute($this, $request->getUri()); // Or redirect
        }

        // Check permissions (placeholder - this should be more specific)
        if (!Login::checkPermission($login, 'hr_management')) {
             // No permission, show 404 or access denied
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        // Determine action: 'add', 'edit', or default to list view
        $action = $request->request('action')->toString();
        $id = $request->request('id')->toInt();

        if ($action === 'edit' && $id > 0) {
            return $this->showForm($request, $id, $login);
        } elseif ($action === 'add') {
            return $this->showForm($request, 0, $login);
        } else {
            return $this->showList($request, $login);
        }
    }

    /**
     * Display the list of employees.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML content for the employee list.
     */
    private function showList(Request $request, $login)
    {
        // Page title
        $this->title = Language::get('Employee List');
        // Active menu
        $this->menu = 'hr'; // Main HR menu

        // Create main section
        $section = Html::create('section');
        // Breadcrumbs
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>'); // Main HR
        $ul->appendChild('<li><span>'.$this->title.'</span></li>');

        $header = $section->add('header', [
            'innerHTML' => '<h2 class="icon-users">'.$this->title.'</h2>'
        ]);

        // Add "Add New Employee" button to header
        $header->add('a', [
            'class' => 'button green icon-add float_right',
            'href' => $request->getUri()->withParams(['module' => 'hr-employee', 'action' => 'add'])->toString(),
            'innerHTML' => '{LNG_Add New Employee}'
        ]);


        // Content container
        $div = $section->add('div', ['class' => 'content_bg']);

        // Pass request and login to the view
        $div->appendChild(\Hr\Employee\View::create()->render($request, $login));

        return $section->render();
    }

    /**
     * Display the form for adding or editing an employee.
     *
     * @param Request $request
     * @param int $id Employee ID for editing, 0 for adding.
     * @param array $login Login information.
     * @return string HTML content for the form.
     */
    private function showForm(Request $request, $id, $login)
    {
        // Get employee data if it's an edit
        $employee = ($id > 0) ? \Hr\Model\Employee::get($id) : null;

        if ($id > 0 && !$employee) {
            // Employee not found for editing
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        // Page title
        $this->title = Language::get($id > 0 ? 'Edit Employee' : 'Add New Employee');
        // Active menu
        $this->menu = 'hr';

        // Create main section
        $section = Html::create('section');
        // Breadcrumbs
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>');
        $ul->appendChild('<li><a href="'.$request->getUri()->withParams(['module' => 'hr-employee'])->toString().'">{LNG_Employee List}</a></li>');
        $ul->appendChild('<li><span>'.($id > 0 ? '{LNG_Edit}' : '{LNG_Add}').'</span></li>');

        $section->add('header', [
            'innerHTML' => '<h2 class="icon-edit">'.$this->title.'</h2>'
        ]);

        // Content container
        $div = $section->add('div', ['class' => 'content_bg']);

        // Pass employee data (or null for new) to the view
        $div->appendChild(\Hr\Employee\View::create()->renderForm($request, $employee, $login));

        return $section->render();
    }
}
?>
