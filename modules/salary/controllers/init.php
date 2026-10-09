<?php
/**
 * @filesource modules/salary/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Init;

use Gcms\Api as ApiController;

/**
 * เมนูและสิทธิ์ของโมดูลเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * สิทธิ์ของโมดูล
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_salary',
            'text' => '{LNG_Can set the module} ({LNG_Salary})'
        ];
        $permissions[] = [
            'value' => 'can_approve_salary',
            'text' => '{LNG_Can be approve} ({LNG_Salary})'
        ];

        return $permissions;
    }

    /**
     * เมนูของโมดูล
     *
     * สมาชิกทุกคนเห็นสลิปของตัวเอง ผู้ดูแลเงินเดือนเห็นรายการและการนำเข้าข้อมูลเพิ่ม
     * ส่วนหน้าตั้งค่าไปอยู่ใต้เมนู Settings เหมือนโมดูลอื่น
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }

        $children = [
            [
                'title' => '{LNG_My Slip}',
                'url' => '/salary',
                'icon' => 'icon-file'
            ]
        ];

        if (ApiController::hasPermission($login, ['can_manage_salary', 'can_approve_salary'])) {
            $children[] = [
                'title' => '{LNG_List of} {LNG_Salary}',
                'url' => '/salary-list',
                'icon' => 'icon-list'
            ];
        }

        if (ApiController::hasPermission($login, 'can_manage_salary')) {
            $children[] = [
                'title' => '{LNG_Import Salary Data}',
                'url' => '/salary-import',
                'icon' => 'icon-import'
            ];
        }

        $menus = parent::insertMenuAfter($menus, [
            [
                'title' => '{LNG_Salary}',
                'icon' => 'icon-money',
                'children' => $children
            ]
        ], 'dashboard');

        $settings = [];
        if (ApiController::hasPermission($login, 'can_config')) {
            $settings[] = [
                'title' => '{LNG_Module Settings}',
                'url' => '/salary-settings',
                'icon' => 'icon-cog'
            ];
        }
        // การนำเข้าพนักงานสร้าง/แก้ไขบัญชีสมาชิก จึงใช้สิทธิ์เดียวกับหน้าสมาชิก (แอดมินเท่านั้น)
        if (\Index\Users\Model::canManage($login)) {
            $settings[] = [
                'title' => '{LNG_Import Employee Data}',
                'url' => '/salary-importusers',
                'icon' => 'icon-group'
            ];
        }

        if (!empty($settings)) {
            $menus = parent::insertMenuChildren($menus, [
                [
                    'title' => '{LNG_Salary}',
                    'icon' => 'icon-money',
                    'children' => $settings
                ]
            ], 'settings', null, 1);
        }

        return $menus;
    }
}
