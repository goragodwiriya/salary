<?php
/**
 * @filesource modules/salary/controllers/record.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Record;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Salary\Base\Model as Base;

/**
 * API ฟอร์มเพิ่ม/แก้ไขรายการเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/salary/record/get
     * อ่านข้อมูลสำหรับฟอร์ม
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_salary')) {
                return $this->errorResponse('Permission required', 403);
            }

            $index = Model::get($request->get('id')->filter('0-9'));
            if ($index === null) {
                return $this->errorResponse('No data available', 404);
            }

            return $this->successResponse([
                'data' => $index,
                'options' => [
                    'member_id' => Base::memberOptions(),
                    'year' => Base::yearOptions(),
                    'month' => Base::monthOptions()
                ]
            ], 'Salary record loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/salary/record/save
     * บันทึกรายการเงินเดือน
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_salary'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id')->filter('0-9');
            $save = [
                'member_id' => $request->post('member_id')->toInt(),
                'year' => $request->post('year')->number(),
                'month' => Base::monthValue($request->post('month')->toInt()),
                'basic_salary' => $request->post('basic_salary')->toDouble(),
                'allowance' => $request->post('allowance')->toDouble(),
                'overtime' => $request->post('overtime')->toDouble(),
                'overtime_hours' => max(0, $request->post('overtime_hours')->toDouble()),
                'bonus' => $request->post('bonus')->toDouble(),
                'deduction' => $request->post('deduction')->toDouble(),
                // ใช้เฉพาะเมื่อปิดการคำนวณอัตโนมัติ เปิดอยู่จะถูกคำนวณทับใน Model::save()
                'social_security' => $request->post('social_security')->toDouble(),
                'tax' => $request->post('tax')->toDouble(),
                'remark' => $request->post('remark')->textarea()
            ];

            $errors = [];
            if ($id === '') {
                // รายการใหม่ ตรวจสอบพนักงาน ปี และเดือน
                if (empty($save['member_id'])) {
                    $errors['member_id'] = Language::get('Please select');
                }
                if (!preg_match('/^[0-9]{4}$/', (string) $save['year'])) {
                    $errors['year'] = Language::get('Please select');
                }
                $month = (int) $save['month'];
                if ($month < 1 || $month > 12) {
                    $errors['month'] = Language::get('Please select');
                }
                if (empty($errors) && Model::findByPeriod($save['member_id'], $save['year'], $save['month'])) {
                    $errors['month'] = Language::get('Salary record for this month already exists');
                }
            } else {
                // แก้ไข พนักงาน ปี และเดือน ใช้ค่าเดิมเสมอ
                $index = Model::get($id);
                if ($index === null) {
                    return $this->errorResponse('No data available', 404);
                }
                $save['member_id'] = (int) $index->member_id;
                $save['year'] = $index->year;
                $save['month'] = $index->month;
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $saved = Model::save($save, $id);

            \Index\Log\Model::add(
                0,
                'salary',
                'Save',
                ($id === '' ? 'Add' : 'Edit').' Salary ID : '.$saved['id'],
                $login->id
            );

            // รายการใหม่ที่อนุมัติทันที แจ้งเตือนให้พนักงานทราบเหมือนตอนกดอนุมัติ
            if ($id === '' && (int) $saved['status'] === 1 && \Salary\Calculator\Model::emailNotification()) {
                \Salary\Email\Model::send($saved, 'create');
            }

            return $this->redirectResponse('/salary-list', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/salary/record/calculate
     * คำนวณประกันสังคม ภาษี และเงินสุทธิ ให้ฟอร์มแสดงผลระหว่างกรอกข้อมูล
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function calculate(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_salary')) {
                return $this->errorResponse('Permission required', 403);
            }

            $result = \Salary\Calculator\Model::calculateNetSalary([
                'basic_salary' => $request->post('basic_salary')->toDouble(),
                'allowance' => $request->post('allowance')->toDouble(),
                'overtime' => $request->post('overtime')->toDouble(),
                'overtime_hours' => $request->post('overtime_hours')->toDouble(),
                'bonus' => $request->post('bonus')->toDouble(),
                'deduction' => $request->post('deduction')->toDouble(),
                'social_security' => $request->post('social_security')->toDouble(),
                'tax' => $request->post('tax')->toDouble()
            ]);

            return $this->successResponse([
                'overtime' => $result['overtime'],
                'overtime_from_hours' => $result['overtime_hours'] > 0,
                'total_income' => $result['total_income'],
                'social_security' => $result['social_security'],
                'tax' => $result['income_tax'],
                'net_salary' => $result['net_salary'],
                'auto_calculate' => \Salary\Calculator\Model::autoCalculate()
            ], 'Calculated');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
