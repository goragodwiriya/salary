<?php
/**
 * @filesource modules/index/models/checker.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Checker;

use Kotchasan\Language;
use Kotchasan\Validator;

/**
 * ตรวจสอบข้อมูลสมาชิกด้วย Ajax
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ฟังก์ชั่นตรวจสอบความถูกต้องของ username และตรวจสอบ username ซ้ำ
     */
    public function username()
    {
        // referer
        if (self::$request->isReferer()) {
            try {
                // ค่าที่ส่งมา
                $id = self::$request->post('id')->toInt();
                $value = self::$request->post('value')->url();
                if (count(self::$cfg->login_fields) == 1 && in_array('email', self::$cfg->login_fields) && !Validator::email($value)) {
                    // อีเมล์เท่านั้น ตรวจสอบ Email
                    echo Language::replace('Invalid :name', [':name' => Language::get('Email')]);
                } else {
                    // ตรวจสอบ username ซ้ำ
                    $search = $this->db()->first($this->getTableName('user'), ['username', $value]);
                    if ($search && ($id == 0 || $id != $search->id)) {
                        echo Language::replace('This :name already exist', [':name' => Language::get('Username')]);
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
                echo Language::replace('Invalid :name', [':name' => Language::get('Username')]);
            }
        }
    }
}
