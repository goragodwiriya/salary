<?php
/**
 * @filesource modules/salary/controllers/settings.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Settings;

use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API ตั้งค่าโมดูลเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/salary/settings/get
     * อ่านค่ากำหนดของโมดูล
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
            if (!ApiController::hasPermission($login, 'can_config')) {
                return $this->errorResponse('Permission required', 403);
            }

            return $this->successResponse([
                'data' => (object) [
                    'salary_tax' => (float) self::$cfg->salary_tax,
                    'salary_tax_allowance' => (float) self::$cfg->salary_tax_allowance,
                    'salary_social' => (float) self::$cfg->salary_social,
                    'salary_social_employer' => (float) self::$cfg->salary_social_employer,
                    'salary_social_max' => (float) self::$cfg->salary_social_max,
                    'salary_overtime_rate' => (float) self::$cfg->salary_overtime_rate,
                    'salary_working_days' => (int) self::$cfg->salary_working_days,
                    'salary_working_hours' => (float) self::$cfg->salary_working_hours,
                    'salary_require_approval' => (int) self::$cfg->salary_require_approval,
                    'salary_auto_calculate' => (int) self::$cfg->salary_auto_calculate,
                    'salary_notification' => (int) self::$cfg->salary_notification,
                    'salary_csv_language' => (string) self::$cfg->salary_csv_language,
                    'salary_employee_status' => (int) self::$cfg->salary_employee_status
                ],
                'options' => [
                    'salary_require_approval' => self::booleanOptions(),
                    'salary_auto_calculate' => self::booleanOptions(),
                    'salary_notification' => self::booleanOptions(),
                    'salary_csv_language' => self::arrayOptions(Language::get('CSV_ENCODING')),
                    'salary_employee_status' => \Gcms\Controller::getUserStatusOptions()
                ]
            ], 'Salary settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/salary/settings/save
     * บันทึกค่ากำหนดของโมดูล
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
            if (!ApiController::canModify($login, ['can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $config = Config::load(ROOT_PATH.'settings/config.php');

            // ภาษีและรายการหัก
            $config->salary_tax = self::limit($request->post('salary_tax')->toDouble(), 0, 100);
            $config->salary_tax_allowance = max(0, $request->post('salary_tax_allowance')->toDouble());
            $config->salary_social = self::limit($request->post('salary_social')->toDouble(), 0, 100);
            $config->salary_social_employer = self::limit($request->post('salary_social_employer')->toDouble(), 0, 100);
            $config->salary_social_max = max(0, $request->post('salary_social_max')->toDouble());

            // การคำนวณ
            $config->salary_overtime_rate = self::limit($request->post('salary_overtime_rate')->toDouble(), 1, 10);
            $config->salary_working_days = (int) self::limit($request->post('salary_working_days')->toInt(), 1, 31);
            $config->salary_working_hours = self::limit($request->post('salary_working_hours')->toDouble(), 1, 24);

            // การอนุมัติและการแจ้งเตือน
            $config->salary_require_approval = $request->post('salary_require_approval')->toBoolean() ? 1 : 0;
            $config->salary_auto_calculate = $request->post('salary_auto_calculate')->toBoolean() ? 1 : 0;
            $config->salary_notification = $request->post('salary_notification')->toBoolean() ? 1 : 0;

            // การนำเข้า/ส่งออก
            $config->salary_csv_language = $request->post('salary_csv_language')->filter('A-Z0-9\-');
            $config->salary_employee_status = $request->post('salary_employee_status')->toInt();

            if (!Config::save($config, ROOT_PATH.'settings/config.php')) {
                return $this->errorResponse(Language::replace('File %s cannot be created or is read-only.', 'settings/config.php'), 500);
            }

            \Index\Log\Model::add(0, 'salary', 'Save', 'Salary settings', $login->id);

            return $this->redirectResponse('reload', 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * จำกัดค่าให้อยู่ในช่วงที่กำหนด
     *
     * @param float $value
     * @param float $min
     * @param float $max
     *
     * @return float
     */
    protected static function limit($value, $min, $max)
    {
        return min($max, max($min, $value));
    }

    /**
     * ตัวเลือกใช่/ไม่ใช่
     *
     * @return array
     */
    protected static function booleanOptions()
    {
        return self::arrayOptions(Language::get('BOOLEANS'));
    }

    /**
     * แปลงแอเรย์ของภาษาเป็นตัวเลือกของ select
     *
     * @param mixed $array
     *
     * @return array
     */
    protected static function arrayOptions($array)
    {
        $result = [];
        foreach ((array) $array as $key => $text) {
            $result[] = [
                'value' => (string) $key,
                'text' => $text
            ];
        }

        return $result;
    }
}
