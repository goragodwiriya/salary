<?php
/**
 * @filesource modules/salary/controllers/dashboard.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Dashboard;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API หน้าแรกของระบบเงินเดือน (การ์ดสรุปและกราฟ)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/salary/dashboard/get
     * การ์ดสรุปของหน้าแรก แสดงคนละชุดตามสิทธิ์
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

            $canManage = ApiController::hasPermission($login, ['can_manage_salary', 'can_approve_salary']);

            return $this->successResponse(Model::cards($login, $canManage), 'Dashboard loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * GET api/salary/dashboard/trend
     * กราฟแนวโน้มเงินเดือน ผู้ดูแลเห็นแยกตามแผนก สมาชิกเห็นของตัวเอง
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function trend(Request $request)
    {
        return $this->graph($request, function ($login, $canManage) {
            return $canManage ? Model::departmentTrend() : Model::memberTrend((int) $login->id);
        });
    }

    /**
     * GET api/salary/dashboard/employeetrend
     * กราฟแนวโน้มจำนวนพนักงานที่มีเงินเดือนแยกตามแผนก (เฉพาะผู้ดูแล)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function employeetrend(Request $request)
    {
        return $this->graph($request, function ($login, $canManage) {
            return $canManage ? Model::departmentEmployeeTrend() : [];
        }, true);
    }

    /**
     * GET api/salary/dashboard/departments
     * กราฟวงกลมเงินเดือนเฉลี่ยแยกตามแผนก (เฉพาะผู้ดูแล)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function departments(Request $request)
    {
        return $this->graph($request, function ($login, $canManage) {
            return $canManage ? Model::departmentAverage() : [];
        }, true);
    }

    /**
     * GET api/salary/dashboard/employees
     * กราฟวงกลมจำนวนพนักงานแยกตามแผนก (เฉพาะผู้ดูแล)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function employees(Request $request)
    {
        return $this->graph($request, function ($login, $canManage) {
            return $canManage ? Model::departmentEmployeeCount() : [];
        }, true);
    }

    /**
     * GET api/salary/dashboard/distribution
     * กราฟการกระจายของเงินเดือน (เฉพาะผู้ดูแล)
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function distribution(Request $request)
    {
        return $this->graph($request, function ($login, $canManage) {
            return $canManage ? Model::salaryDistribution() : [];
        }, true);
    }

    /**
     * ตัวช่วยของทุก endpoint ที่คืนค่าข้อมูลกราฟ
     *
     * @param Request $request
     * @param callable $callback
     * @param bool $manageOnly ต้องมีสิทธิ์ดูแลเงินเดือนเท่านั้น
     *
     * @return \Kotchasan\Http\Response
     */
    protected function graph(Request $request, $callback, $manageOnly = false)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $canManage = ApiController::hasPermission($login, ['can_manage_salary', 'can_approve_salary']);

            // กราฟเฉพาะผู้ดูแลคืนค่าว่างให้สมาชิกทั่วไป ไม่ตอบ 403
            // เพราะกราฟอาจยิงคำขอก่อนที่ data-if จะถอดส่วนนั้นออกจากหน้า
            $series = $manageOnly && !$canManage ? [] : $callback($login, $canManage);

            return $this->successResponse($series, 'Graph loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
