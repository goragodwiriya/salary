<?php
/**
 * @filesource modules/index/models/login.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Login;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Jwt;
use Kotchasan\Language;

/**
 * Controller หลัก สำหรับแสดง frontend ของ GCMS
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ฟังก์ชั่นตรวจสอบการ Login
     *
     * @param Request $request
     */
    public function chklogin(Request $request)
    {
        if ($request->initSession() && $request->isSafe()) {
            // ตรวจสอบการ login
            Login::create($request);
            // ตรวจสอบสมาชิก
            $login = Login::isMember();
            $ret = [];
            if ($login) {
                // Login success
                $ret['alert'] = Language::replace('Welcome %s, login complete', $login['name']);
                $ret['url'] = $request->post('login_action')->url();
                // เคลียร์
                $request->removeToken();
            } elseif (preg_match('/^([0-9]{10,10}):([0-9]+)$/', Login::$login_message, $match)) {
                // JWT
                $jwt = Jwt::create(self::$cfg->password_key);
                $token = $jwt->encode([
                    'username' => $match[1],
                    'ret_url' => WEB_URL
                ]);
                $ret['url'] = WEB_URL.'index.php?module=otp&id='.$token;
            } else {
                $ret['ret_'.Login::$login_input] = Login::$login_message;
            }
            // คืนค่า JSON
            echo json_encode($ret);
        }
    }

    /**
     * ฟังก์ชั่นเข้าระบบเป็นสมาชิกอื่น
     * สามารถเข้าระบบได้โดย Super Admin (ID-1) เท่านั้น
     *
     * @param int $id
     * @param array $login
     *
     * @return array
     */
    public static function loginAs($id, $login)
    {
        // เข้าระบบได้โดย Super Admin (ID-1) เท่านั้น
        if ($login['id'] == 1) {
            // ตรวจสอบ $id ที่เลือก
            $user = \Index\Editprofile\Model::get($id);
            if ($user) {
                // อัปเดทการเยี่ยมชมล่าสุด
                $save = [
                    'token' => empty($user['token']) ? \Kotchasan\Password::uniqid(40) : $user['token']
                ];
                $model = \Kotchasan\Model::create();
                $model->db()->update($model->getTableName('user'), $user['id'], $save);
                // log
                \Index\Log\Model::add(0, 'index', 'User', '{LNG_Login as} ID : '.$user['id'], $login['id']);
                // ใช้ token ของ user ที่เลือก
                $_SESSION['login'] = [
                    'username' => $user['username'],
                    'token' => $save['token']
                ];
                // บันทึกการเข้าระบบโดยแอดมิน
                $_SESSION['lastLogin'] = [
                    'username' => $login['username'],
                    'token' => $login['token']
                ];
                // คืนค่ากลับไปเข้าระบบ
                return [
                    'location' => WEB_URL.'index.php'
                ];
            }
        }
    }
}
