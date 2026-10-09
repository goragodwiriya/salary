<?php
/**
 * @filesource modules/salary/controllers/salaries.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Salaries;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Salary\Base\Model as Base;

/**
 * API ตารางรายการเงินเดือนของพนักงานทุกคน (สำหรับผู้ดูแล)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * คอลัมน์ที่เรียงลำดับได้ (ป้องกัน SQL injection)
     *
     * @var array
     */
    protected $allowedSortColumns = ['name', 'year', 'month', 'basic_salary', 'deduction', 'social_security', 'tax', 'net_salary', 'status'];

    /**
     * ตรวจสอบสิทธิ์
     *
     * ผู้อนุมัติต้องเห็นรายการด้วยจึงจะกดอนุมัติได้
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_salary', 'can_approve_salary'])) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

    /**
     * ตัวกรองที่ส่งมาจากหน้าเว็บ
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'year' => $request->get('year', date('Y'))->number(),
            'month' => $request->get('month')->toInt(),
            'status' => $request->get('status', -1)->toInt()
        ];
    }

    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * ตัวเลือกของ filter
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return [
            'year' => Base::yearOptions(),
            'month' => Base::monthOptions(),
            'status' => Base::statusOptions()
        ];
    }

    /**
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $canManage = ApiController::hasPermission($login, 'can_manage_salary') ? 1 : 0;
        foreach ($datas as $item) {
            $item->period_text = Base::periodText($item->year, $item->month, true);
            $item->status_text = Base::statusText($item->status);
            $item->can_manage = $canManage;
        }

        return $datas;
    }

    /**
     * ส่งออกรายการเงินเดือนเป็นไฟล์ CSV
     * GET api/salary/salaries/export?type=csv
     *
     * @param Request $request
     * @param object $login
     *
     * @return void
     */
    protected function handleCsvExport(Request $request, $login)
    {
        $params = $this->parseParams($request, $login);

        $headers = [
            Language::get('Name'),
            Language::get('Year'),
            Language::get('Month'),
            Language::get('Basic Salary'),
            Language::get('Allowance'),
            Language::get('Overtime Hours'),
            Language::get('Overtime'),
            Language::get('Bonus'),
            Language::get('Deduction'),
            Language::get('Social Security'),
            Language::get('Income Tax'),
            Language::get('Net Salary'),
            Language::get('Status')
        ];

        $months = (array) Language::get('MONTH_LONG');
        $formatter = function ($item) use ($months) {
            $month = (int) $item->month;

            return [
                $item->name,
                Base::displayYear($item->year),
                isset($months[$month]) ? $months[$month] : $item->month,
                number_format((float) $item->basic_salary, 2),
                number_format((float) $item->allowance, 2),
                (float) $item->overtime_hours > 0 ? (float) $item->overtime_hours : '',
                number_format((float) $item->overtime, 2),
                number_format((float) $item->bonus, 2),
                number_format((float) $item->deduction, 2),
                number_format((float) $item->social_security, 2),
                number_format((float) $item->tax, 2),
                number_format((float) $item->net_salary, 2),
                Base::statusText($item->status)
            ];
        };

        $filename = 'salary_list';
        if (!empty($params['year'])) {
            $filename .= '_'.$params['year'];
        }
        if (!empty($params['month'])) {
            $filename .= '_'.Base::monthValue($params['month']);
        }

        $this->exportToCsv($params, $login, $headers, $formatter, $filename);
    }

    /**
     * ลบรายการเงินเดือนที่เลือก
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_salary'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $this->selectedIds($request);
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $count = Model::remove($ids);
        if (empty($count)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'salary', 'Delete', 'Delete Salary ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$count.' item(s) successfully', 200, 0, 'table');
    }

    /**
     * คำนวณประกันสังคม ภาษี และเงินสุทธิ ของรายการที่เลือกใหม่
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleRecalculateAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_salary'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $this->selectedIds($request);
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $count = Model::recalculate($ids);
        if (empty($count)) {
            return $this->errorResponse('Failed to process request', 400);
        }

        \Index\Log\Model::add(0, 'salary', 'Recalculate', 'Re-Calculate Salary ID(s) : '.implode(', ', $ids).' ('.$count.' items)', $login->id);

        return $this->redirectResponse('reload', 'Saved successfully', 200, 0, 'table');
    }

    /**
     * อนุมัติรายการเงินเดือนที่เลือก
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleApproveAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_salary', 'can_approve_salary'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $this->selectedIds($request);
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        list($count, $messages) = Model::approve(Model::getByIds($ids));
        if (empty($count)) {
            return $this->errorResponse('No items to approve or all items already approved', 400);
        }

        \Index\Log\Model::add(0, 'salary', 'Approve', 'Approve Salary ID(s) : '.implode(', ', $ids).' ('.$count.' items)', $login->id);

        $message = Language::get('Saved successfully');
        if (!empty($messages)) {
            $message .= ' ('.implode(', ', $messages).')';
        }

        return $this->redirectResponse('reload', $message, 200, 0, 'table');
    }

    /**
     * ส่งข้อความแจ้งเตือนรายการที่เลือกอีกครั้ง
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleSendEmailAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_salary', 'can_approve_salary'])) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $this->selectedIds($request);
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $messages = [];
        $count = 0;
        foreach (Model::getByIds($ids) as $item) {
            $messages[] = \Salary\Email\Model::send($item, 'send_email');
            ++$count;
        }

        if (empty($count)) {
            return $this->errorResponse('No items selected', 400);
        }

        \Index\Log\Model::add(0, 'salary', 'Send Email', 'Send Email Salary ID(s) : '.implode(', ', $ids).' ('.$count.' items)', $login->id);

        $message = Language::get('Email sent successfully');
        $messages = array_values(array_unique(array_filter($messages)));
        if (!empty($messages)) {
            $message .= ' ('.implode(', ', $messages).')';
        }

        return $this->notificationResponse($message);
    }

    /**
     * เปิดหน้าแก้ไขรายการเงินเดือน
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, 'can_manage_salary')) {
            return $this->errorResponse('Permission required', 403);
        }

        return $this->redirectResponse('/salary-edit?id='.rawurlencode($request->post('id')->toString()));
    }

    /**
     * เปิดสลิปเงินเดือนใน modal
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleViewAction(Request $request, $login)
    {
        $payload = \Salary\Slip\Model::modalPayload($request->post('id')->toString(), $login);
        if ($payload === null) {
            return $this->errorResponse('No data available', 404);
        }

        return $this->successResponse($payload, 'Salary slip');
    }

    /**
     * รายการ id ที่เลือก รองรับทั้ง bulk action (ids) และปุ่มในแถว (id)
     *
     * @param Request $request
     *
     * @return array
     */
    protected function selectedIds(Request $request)
    {
        $ids = $request->post('ids', [])->toArray();
        if (empty($ids)) {
            $ids = [$request->post('id')->toString()];
        }
        $ids = array_map(function ($id) {
            return preg_replace('/[^0-9]/', '', (string) $id);
        }, $ids);

        return array_values(array_filter($ids, function ($id) {
            return $id !== '';
        }));
    }
}
