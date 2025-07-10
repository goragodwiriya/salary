<?php
/**
 * @filesource modules/hr/models/Recruitment.php
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
use Kotchasan\Date;

/**
 * Model for handling recruitment tracking data.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Recruitment extends \Kotchasan\Model
{
    /**
     * Query recruitment data for use in a DataTable.
     *
     * @param array $params Parameters for filtering the query.
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params = [])
    {
        $where = [];
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $where[] = ['R.status', $params['status']];
        }

        return static::createQuery()
            ->select(
                'R.id',
                'R.position_title',
                'R.department',
                'R.open_date',
                'R.close_date',
                'R.status',
                'R.number_of_applicants',
                Sql::CONCAT(['E.first_name', Sql::expr("'\s'"), 'E.last_name'], 'selected_candidate_name')
            )
            ->from('recruitments R')
            ->leftJoin('employees E', 'E.id', 'R.selected_candidate_id')
            ->where($where);
    }

    /**
     * Get recruitment details by ID.
     *
     * @param int $id The ID of the recruitment record.
     * @return object|null The recruitment data object or null if not found.
     */
    public static function get($id)
    {
        if (empty($id)) {
            return null;
        }
        return static::createQuery()
            ->from('recruitments')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Get a list of all employees for select dropdown (for selected_candidate_id).
     * Could be filtered by status if necessary, but for now, all.
     *
     * @return array
     */
    public static function getPotentialCandidatesForSelect()
    {
        // This is the same as in Termination model, can be centralized later if needed
        $query = static::createQuery()
            ->select('id', Sql::CONCAT(['first_name', Sql::expr("' '"), 'last_name', Sql::expr("' ('"), 'employee_code', Sql::expr("')'")], 'name'))
            ->from('employees')
            // ->where(['status', 'active']) // Or any other relevant status
            ->order('first_name')
            ->cacheOn()
            ->toArray();

        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item['id']] = $item['name'];
        }
        return $result;
    }

    /**
     * Handle submission of recruitment form (add/edit).
     *
     * @param Request $request
     * @return array JSON response.
     */
    public function submit(Request $request)
    {
        $ret = [];
        // Session, permission, and referer check
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'hr_management')) { // Placeholder
                try {
                    $table_recruitments = $this->getTableName('recruitments');

                    $save = [
                        'position_title' => $request->post('position_title')->text(),
                        'department' => $request->post('department')->text(),
                        'open_date' => $request->post('open_date')->date(),
                        'close_date' => $request->post('close_date')->date(),
                        'status' => $request->post('status', 'open')->text(),
                        'number_of_applicants' => $request->post('number_of_applicants')->toInt(),
                        'source_of_applicants' => $request->post('source_of_applicants')->text(),
                        'selected_candidate_id' => $request->post('selected_candidate_id')->toInt(),
                        'candidate_start_date' => $request->post('candidate_start_date')->date(),
                        'cost_per_hire' => $request->post('cost_per_hire')->toFloat()
                    ];

                    // Nullable fields
                    $save['close_date'] = empty($save['close_date']) ? null : $save['close_date'];
                    $save['selected_candidate_id'] = empty($save['selected_candidate_id']) ? null : $save['selected_candidate_id'];
                    $save['candidate_start_date'] = empty($save['candidate_start_date']) ? null : $save['candidate_start_date'];


                    // Calculate Time to Fill if possible
                    if (!empty($save['open_date']) && !empty($save['candidate_start_date'])) {
                        $time_to_fill = Date::compare($save['candidate_start_date'], $save['open_date']); // days
                        $save['time_to_fill_days'] = $time_to_fill >=0 ? $time_to_fill : null;
                    } else {
                        $save['time_to_fill_days'] = null;
                    }

                    $recruitment_id = $request->post('id')->toInt();

                    if (empty($save['position_title'])) {
                        $ret['alert'] = Language::get('Please fill in').' '.Language::get('Position Title');
                        $ret['input'] = 'position_title';
                    } elseif (empty($save['open_date'])) {
                        $ret['alert'] = Language::get('Please fill in').' '.Language::get('Open Date');
                        $ret['input'] = 'open_date';
                    } else {
                        if ($recruitment_id > 0) {
                            $this->db()->update($table_recruitments, $recruitment_id, $save);
                            $ret['alert'] = Language::get('Saved successfully');
                        } else {
                            $save['created_at'] = date('Y-m-d H:i:s');
                            $recruitment_id = $this->db()->insert($table_recruitments, $save);
                            $ret['alert'] = Language::get('Added successfully');
                        }

                        // Log
                        // \Index\Log\Model::add($recruitment_id, 'hr', 'Save', Language::get('Recruitment Record').' ID : '.$recruitment_id, $login['id']);

                        $ret['location'] = $request->getUri()->withParams(['module' => 'hr-recruitment-list'])->toArray();
                        \Kotchasan\Cache::clear();
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                } catch (\Throwable $e) {
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
