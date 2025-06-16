<?php
/**
 * @filesource modules/salary/models/settings.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Settings;

use Gcms\Config;
use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * รับค่าจาก (settings.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_config
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if ($login['active'] == 1 && Login::checkPermission($login, 'can_config')) {
                // โหลด config
                $config = Config::load(ROOT_PATH.'settings/config.php');

                // Tax and deductions
                $config->salary_tax = $request->post('salary_tax')->toDouble();
                $config->salary_social = $request->post('salary_social')->toDouble();
                $config->salary_social_employer = $request->post('salary_social_employer')->toDouble();
                $config->salary_social_max = $request->post('salary_social_max')->toDouble();

                // Calculation settings
                $config->salary_overtime_rate = $request->post('salary_overtime_rate')->toDouble();
                $config->salary_working_days = $request->post('salary_working_days')->toInt();
                $config->salary_working_hours = $request->post('salary_working_hours')->toDouble();

                // Approval settings
                $config->salary_require_approval = $request->post('salary_require_approval')->toBoolean();
                $config->salary_auto_calculate = $request->post('salary_auto_calculate')->toBoolean();
                $config->salary_notification = $request->post('salary_notification')->toBoolean();

                // CSV language
                $config->csv_language = $request->post('csv_language')->filter('A-Z0-9\-');
                // save config
                if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                    // log
                    \Index\Log\Model::add(0, 'salary', 'Save', '{LNG_Module settings} {LNG_Salary}', $login['id']);
                    // คืนค่า
                    $ret['alert'] = Language::get('Saved successfully');
                    $ret['location'] = 'reload';
                    // เคลียร์
                    $request->removeToken();
                } else {
                    // ไม่สามารถบันทึก config ได้
                    $ret['alert'] = Language::replace('File %s cannot be created or is read-only.', 'settings/config.php');
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
