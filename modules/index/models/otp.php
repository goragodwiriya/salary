<?php
/**
 * @filesource modules/index/models/otp.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Otp;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Jwt;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * OTP Class
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * คืนค่า USER ตามที่เลือก (OTP)
     *
     * @param string $username
     *
     * @return object
     */
    public static function get($username)
    {
        return static::createQuery()
            ->from('user')
            ->where(['username', $username])
            ->first('id', 'username', 'activatecode');
    }

    /**
     * มาจากการกดปุ่ม Login, Editprofile
     *
     * @param Request $request
     *
     * @return void
     */
    public function action(Request $request)
    {
        // session, token, Ajax
        if ($request->initSession() && $request->isSafe() && $request->isAjax()) {
            $ret = [];
            // ค่าที่ส่งมา
            $action = $request->post('action', '')->toString();
            $id = $request->post('id')->url();
            if (preg_match('/(resend|verified)/', $action, $match)) {
                // JWT
                $jwt = Jwt::create(self::$cfg->password_key);
                $payload = $jwt->decode($id);
                // Table
                $table = $this->getTableName('user');
                // Database
                $db = $this->db();
                // ตรวจสอบ Username
                $user = $db->first($table, [
                    ['username', $payload['username']],
                    ['username', '!=', '']
                ]);
                if ($user) {
                    if ($match[1] === 'resend') {
                        // new OTP
                        $otp = Text::generateRandomString();
                        $expired = time() + self::$cfg->otp_request_timeout;
                        // save OTP
                        $db->update($table, $user->id, [
                            'activatecode' => $otp.':'.$expired
                        ]);
                        // send SMS
                        $msg = Language::replace('Your OTP code is :otp. Please enter this code on the website to confirm your phone number.', [':otp' => $otp]);
                        // send SMS
                        \Gcms\Sms::send($user->username, $msg);
                        // log
                        \Index\Log\Model::add(0, 'index', 'SMS', '{LNG_Resend} IP '.$request->getClientIp(), $user->id);
                        // ขอ OTP ใหม่
                        $ret['alert'] = Language::get('The system has sent a new OTP code to the phone number you have registered. Please check the SMS and enter the code to confirm the phone number.');
                    } elseif ($match[1] === 'verified' && preg_match('/^([0-9]{4,4}):([0-9]+)$/', $user->activatecode, $codes)) {
                        // ตรวจสอบ OTP
                        $otp = $codes[1];
                        $expired = (int) $codes[2];
                        // OTP ตรงกันและยังไม่หมดเวลา
                        if ($otp === $request->post('number')->number() && $expired > time()) {
                            // verified OTP
                            $db->update($table, $user->id, [
                                'activatecode' => ''
                            ]);
                            // ไปหน้าแรก
                            $ret['location'] = $payload['ret_url'];
                            // เข้าระบบสำเร็จ
                            $ret['alert'] = Language::get('Welcome. Phone number has been verified. Please log in again.');
                        } else {
                            // OTP ไม่ถูกต้องหรือหมดอายุ
                            $ret['alert'] = Language::get('OTP is invalid or expired. Please request a new OTP.');
                        }
                        // log
                        \Index\Log\Model::add(0, 'index', 'SMS', '{LNG_Verify Account} IP '.$request->getClientIp(), $user->id);
                    } else {
                        // ไปหน้าแรก
                        $ret['location'] = $payload['ret_url'];
                    }
                    // clear
                    $request->removeToken();
                    if (!isset($ret['location'])) {
                        // ฟอร์ม OTP
                        $ret['location'] = WEB_URL.'?module=otp&id='.$id;
                    }
                }
            }
            if (!empty($ret)) {
                // คืนค่าเป็น JSON
                echo json_encode($ret);
            }
        }
    }
}
