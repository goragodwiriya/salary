<?php
/**
 * @filesource modules/hr/models/Termination.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Model;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Database\Sql;

/**
 * Model for handling employee termination data.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Termination extends \Kotchasan\Model
{
    /**
     * Query termination data for use in a DataTable.
     *
     * @param array $params Parameters for filtering the query.
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params = [])
    {
        return static::createQuery()
            ->select(
                'T.id',
                'T.employee_id',
                Sql::CONCAT(['E.first_name', Sql::expr("'\s'"), 'E.last_name'], 'employee_name'),
                'E.employee_code',
                'T.notice_date',
                'T.last_day_date',
                'T.termination_type',
                'T.reason' // Might be too long for a table, consider a summary or tooltip
            )
            ->from('terminations T')
            ->join('employees E', 'INNER', ['E.id', 'T.employee_id']);
        // Add where conditions if needed, e.g., by year or status
    }

    /**
     * Get termination details by ID.
     *
     * @param int $id The ID of the termination record.
     * @return object|null The termination data object or null if not found.
     */
    public static function get($id)
    {
        if (empty($id)) {
            return null;
        }
        return static::createQuery()
            ->from('terminations')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Get a list of active employees for select dropdown.
     *
     * @return array
     */
    public static function getEmployeesForSelect()
    {
        $query = static::createQuery()
            ->select('id', Sql::CONCAT(['first_name', Sql::expr("' '"), 'last_name', Sql::expr("' ('"), 'employee_code', Sql::expr("')'")], 'name'))
            ->from('employees')
            ->where(['status', 'active']) // Or all employees if needed for backdated terminations
            ->order('first_name')
            ->cacheOn() // Cache this query as it might be used frequently
            ->toArray();

        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item['id']] = $item['name'];
        }
        return $result;
    }

    /**
     * Handle submission of termination form (add/edit).
     *
     * @param Request $request
     * @return array JSON response.
     */
    public function submit(Request $request)
    {
        $ret = [];
        // Session, permission, and referer check
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'hr_management')) { // Placeholder for actual permission
                try {
                    // Get an instance of the table
                    $table_terminations = $this->getTableName('terminations');

                    // Values to save
                    $save = [
                        'employee_id' => $request->post('employee_id')->toInt(),
                        'notice_date' => $request->post('notice_date')->date(),
                        'last_day_date' => $request->post('last_day_date')->date(),
                        'reason' => $request->post('reason')->textarea(), // or ->text() if single line
                        'termination_type' => $request->post('termination_type')->text()
                    ];

                    // Termination ID for update, 0 for new
                    $termination_id = $request->post('id')->toInt();

                    // Validate required fields
                    if (empty($save['employee_id'])) {
                        $ret['alert'] = Language::get('Please select an employee.');
                        $ret['input'] = 'employee_id';
                    } elseif (empty($save['last_day_date'])) {
                        $ret['alert'] = Language::get('Please fill in').' '.Language::get('Last Day Worked');
                        $ret['input'] = 'last_day_date';
                    } elseif (empty($save['termination_type'])) {
                        $ret['alert'] = Language::get('Please select a termination type.');
                        $ret['input'] = 'termination_type';
                    } else {
                        // Additional validation: notice_date <= last_day_date
                        if (!empty($save['notice_date']) && !empty($save['last_day_date']) && strtotime($save['notice_date']) > strtotime($save['last_day_date'])) {
                             $ret['alert'] = Language::get('Notice date cannot be after the last day worked.');
                             $ret['input'] = 'notice_date';
                        } else {
                            $db = $this->db();
                            $db->begin();

                            if ($termination_id > 0) {
                                // Update
                                $db->update($table_terminations, $termination_id, $save);
                                $ret['alert'] = Language::get('Saved successfully');
                            } else {
                                // New termination
                                $save['created_at'] = date('Y-m-d H:i:s');
                                $termination_id = $db->insert($table_terminations, $save);
                                $ret['alert'] = Language::get('Added successfully');
                            }

                            // Update employee status to 'terminated'
                            if ($termination_id && $save['employee_id']) {
                                $table_employees = $this->getTableName('employees');
                                $db->update($table_employees, $save['employee_id'], ['status' => 'terminated']);
                            }

                            $db->commit();

                            // Log
                            // \Index\Log\Model::add($termination_id, 'hr', 'Save', Language::get('Termination Record').' ID : '.$termination_id, $login['id']);

                            $ret['location'] = $request->getUri()->withParams(['module' => 'hr-termination-list'])->toArray(); // Redirect to list page
                            \Kotchasan\Cache::clear();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    if (isset($db) && $db->inTransaction()) {
                        $db->rollback();
                    }
                    $ret['alert'] = $e->getMessage();
                } catch (\Throwable $e) {
                    if (isset($db) && $db->inTransaction()) {
                        $db->rollback();
                    }
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
        echo json_encode($ret);
    }
}
?>
