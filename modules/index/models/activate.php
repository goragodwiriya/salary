<?php
/**
 * @filesource modules/index/models/activate.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Activate;

/**
 * activate.php
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ยืนยันสมาชิก
     * สำเร็จคืนค่า true ไม่พบคืนค่า false
     *
     * @param string $activatecode
     *
     * @return object|bool
     */
    public static function execute($activatecode)
    {
        // Model
        $model = new static();
        // table
        $table = $model->getTableName('user');
        // ตรวจสอบรายการที่ activate
        $user = $model->db()->first($table, ['activatecode', $activatecode]);
        if ($user) {
            // activate
            $model->db()->update($table, $user->id, ['activatecode' => '']);
            return true;
        }
        return false;
    }
}
