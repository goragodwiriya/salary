<?php
/**
 * @filesource modules/hr/models/Employee.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Model; // Adjusted namespace to be simpler, Hr\Employee\Model might be too nested for Kotchasan if controllers are Hr\Employee\Controller

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Database\Sql;

/**
 * Model for handling employee data.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Employee extends \Kotchasan\Model
{
    /**
     * Query employee data for use in a DataTable.
     *
     * @param array $params Parameters for filtering the query (e.g., status).
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params = [])
    {
        $where = [];
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $where[] = ['E.status', $params['status']];
        }

        return static::createQuery()
            ->select(
                'E.id',
                'E.employee_code',
                'E.first_name',
                'E.last_name',
                'E.department',
                'E.position',
                'E.start_date',
                'E.status',
                'E.salary'
            )
            ->from('employees E')
            ->where($where);
    }

    /**
     * Get employee details by ID.
     *
     * @param int $id The ID of the employee.
     * @return object|null The employee data object or null if not found.
     */
    public static function get($id)
    {
        if (empty($id)) {
            return null;
        }
        return static::createQuery()
            ->from('employees')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Handle submission of employee form (add/edit).
     *
     * @param Request $request
     * @return array JSON response.
     */
    public function submit(Request $request)
    {
        $ret = [];
        // Session, permission, and referer check
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) { // Assuming admin/permission check will be added later
            if (Login::checkPermission($login, 'hr_management')) { // Placeholder for actual permission
                try {
                    // Get an instance of the table
                    $table_employees = $this->getTableName('employees');

                    // Values to save
                    $save = [
                        'employee_code' => $request->post('employee_code')->text(),
                        'first_name' => $request->post('first_name')->text(),
                        'last_name' => $request->post('last_name')->text(),
                        'department' => $request->post('department')->text(),
                        'position' => $request->post('position')->text(),
                        'start_date' => $request->post('start_date')->date(),
                        'salary' => $request->post('salary')->toFloat(),
                        'manager_id' => $request->post('manager_id')->toInt(),
                        'status' => $request->post('status', 'active')->text()
                    ];

                    // Employee ID for update, 0 for new
                    $employee_id = $request->post('id')->toInt();

                    // Validate required fields
                    if (empty($save['employee_code'])) {
                        $ret['alert'] = Language::get('Please fill in').' '.Language::get('Employee Code');
                        $ret['input'] = 'employee_code';
                    } elseif (empty($save['first_name'])) {
                        $ret['alert'] = Language::get('Please fill in').' '.Language.get('First Name');
                        $ret['input'] = 'first_name';
                    } elseif (empty($save['last_name'])) {
                        $ret['alert'] = Language.get('Please fill in').' '.Language.get('Last Name');
                        $ret['input'] = 'last_name';
                    } else {
                        // Check for duplicate employee_code
                        $query = static::createQuery()
                            ->from('employees')
                            ->where(['employee_code', $save['employee_code']]);
                        if ($employee_id > 0) {
                            $query->andWhere(['id', '!=', $employee_id]);
                        }
                        $search = $query->first('id');

                        if ($search) {
                            $ret['alert'] = Language::get('This Employee Code is already in use.');
                            $ret['input'] = 'employee_code';
                        } else {
                            if ($employee_id > 0) {
                                // Update
                                $this->db()->update($table_employees, $employee_id, $save);
                                $ret['alert'] = Language::get('Saved successfully');
                            } else {
                                // New employee
                                $save['created_at'] = date('Y-m-d H:i:s');
                                $employee_id = $this->db()->insert($table_employees, $save);
                                $ret['alert'] = Language::get('Added successfully');
                            }
                            // Log
                            // \Index\Log\Model::add($employee_id, 'hr', 'Save', Language::get('Employee').' ID : '.$employee_id, $login['id']);

                            $ret['location'] = $request->getUri()->withParams(['module' => 'hr-employee-list'])->toArray(); // Redirect to employee list page
                            // Clear cache if any
                            \Kotchasan\Cache::clear();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                } catch (\Throwable $e) { // Catch any other error
                    $ret['alert'] = $e->getMessage();
                }
            } else {
                $ret['alert'] = Language::get('You do not have permission to perform this action.');
            }
        } else {
            $ret['alert'] = Language::get('Unable to complete the transaction.');
        }

        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction due to an unknown error.');
        }
        // Send JSON response
        echo json_encode($ret);
    }
}
?>
