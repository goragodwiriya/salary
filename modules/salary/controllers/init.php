<?php
/**
 * @filesource modules/salary/controllers/init.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Init;

/**
 * Init Module
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller
{
    /**
     * รายการ permission ของโมดูล
     *
     * @param array $permissions
     *
     * @return array
     */
    public static function updatePermissions($permissions)
    {
        $permissions['can_manage_salary'] = '{LNG_Can set the module} ({LNG_Salary})';
        $permissions['can_approve_salary'] = '{LNG_Can be approve} ({LNG_Salary})';
        return $permissions;
    }
}
