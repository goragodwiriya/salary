<?php
/**
 * @filesource modules/salary/controllers/initmenu.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        if ($login) {
            // รายการเมนูสมาชิกทุกคน
            $menu->addTopLvlMenu('salary', '{LNG_Salary}', null, [
                [
                    'text' => '{LNG_My Slip}',
                    'url' => 'index.php?module=salary'
                ]
            ], 'member');
            // สามารถตั้งค่าระบบได้
            if (Login::checkPermission($login, 'can_config')) {
                $menu->add('settings', '{LNG_Settings} {LNG_Salary}', 'index.php?module=salary-settings', null, 'salary');
            }
            // สามารถตั้งค่าเงินเดือน และดูสถิติได้
            if (Login::checkPermission($login, 'can_manage_salary')) {
                $submenus = [];
                $submenus['salary'] = [
                    'text' => '{LNG_List of} {LNG_Salary}',
                    'url' => 'index.php?module=salary-setup'
                ];
                $submenus['import'] = [
                    'text' => '{LNG_Import Salary Data}',
                    'url' => 'index.php?module=salary-import'
                ];
                $submenus['importusers'] = [
                    'text' => '{LNG_Import Employee Data}',
                    'url' => 'index.php?module=salary-importusers'
                ];
                $menu->add('report', '{LNG_Salary}', null, $submenus, 'salary');
            }
        }
    }
}
