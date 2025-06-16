<?php
/**
 * @filesource modules/index/models/telegramlogin.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Telegramlogin;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Telegram Login
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รับข้อมูลที่ส่งมาจากการเข้าระบบด้วยบัญชี Telegram
     *
     * @param Request $request
     */
    public function chklogin(Request $request)
    {
        // session, token
        if ($request->initSession() && $request->isSafe()) {
            $ret = [];
            try {
                // สุ่มรหัสผ่านใหม่
                $password = \Kotchasan\Password::uniqid();
                // db
                $db = $this->db();
                // table
                $user_table = $this->getTableName('user');
                // ตรวจสอบสมาชิกกับ db
                $telegram_id = $request->post('id')->number();
                $telegram_user = $request->post('username')->username();
                $username = $telegram_id.'@'.$telegram_user;
                $search = $db->createQuery()
                    ->from('user')
                    ->where(['username', $username])
                    ->toArray()
                    ->first();
                if ($search === false) {
                    // ยังไม่เคยลงทะเบียน, ลงทะเบียนใหม่
                    $name = trim($request->post('first_name')->topic().' '.$request->post('last_name')->topic());
                    $save = \Index\Register\Model::execute($this, [
                        'username' => $username,
                        'password' => $password,
                        'name' => $name,
                        // Telegram
                        'social' => 4,
                        'telegram_id' => $telegram_id,
                        'token' => self::$cfg->new_members_active == 1 ? \Kotchasan\Password::uniqid(40) : null,
                        // โหมดตัวอย่างเป็นแอดมิน, ไม่ใช่เป็นสมาชิกทั่วไป
                        'status' => self::$cfg->demo_mode ? 1 : 0,
                        // 0 รอ Approve, 1 เข้าระบบได้ทันที
                        'active' => self::$cfg->new_members_active
                    ]);
                    $image = $request->post('photo_url')->url();
                    if (!empty($image)) {
                        $arrContextOptions = [
                            "ssl" => [
                                "verify_peer" => false,
                                "verify_peer_name" => false
                            ]
                        ];
                        $image = @file_get_contents($image, false, stream_context_create($arrContextOptions));
                        if ($image) {
                            file_put_contents(ROOT_PATH.DATA_FOLDER.'avatar/'.$save['id'].self::$cfg->stored_img_type, $image);
                        }
                    }
                    // log
                    \Index\Log\Model::add($save['id'], 'index', 'User', '{LNG_Register} (Telegram)', $save['id']);
                } elseif ($search['social'] == 4) {
                    if ($search['active'] == 1) {
                        // เคยเยี่ยมชมแล้ว อัปเดตการเยี่ยมชม
                        $save = $search;
                        $save['salt'] = \Kotchasan\Password::uniqid();
                        $save['token'] = \Kotchasan\Password::uniqid(40);
                        // อัปเดต
                        $db->update($user_table, $search['id'], $save);
                        $save['permission'] = explode(',', trim($save['permission'], " \t\n\r\0\x0B,"));
                        // log
                        \Index\Log\Model::add($save['id'], 'index', 'User', '{LNG_Login} (Telegram) IP '.$request->getClientIp(), $save['id']);
                    } elseif (self::$cfg->new_members_active == 0) {
                        // ยังไม่ได้ Approve
                        $save = false;
                        $ret['alert'] = Language::get('Your account has not been approved, please wait or contact the administrator.');
                        $ret['isMember'] = 0;
                    } else {
                        // ไม่ใช่สมาชิกปัจจุบัน ไม่สามารถเข้าระบบได้
                        $save = false;
                        $ret['alert'] = Language::get('Unable to complete the transaction');
                        $ret['isMember'] = 0;
                    }
                } else {
                    // ไม่สามารถ login ได้ เนื่องจากมี email อยู่ก่อนแล้ว
                    $save = false;
                    $ret['alert'] = Language::replace('This :name already exist', [':name' => Language::get('Username')]);
                    $ret['isMember'] = 0;
                }
                if (is_array($save)) {
                    if ($save['active'] === 1) {
                        // สามารถเข้าระบบได้
                        unset($save['password']);
                        $_SESSION['login'] = $save;
                        // คืนค่า
                        $ret['isMember'] = 1;
                        $ret['alert'] = Language::replace('Welcome %s, login complete', $save['name']);
                        // Telegram Bot
                        \Gcms\Telegram::sendTo($save['telegram_id'], $ret['alert']);
                    } else {
                        // ส่งข้อความแจ้งเตือนการสมัครสมาชิกของ user
                        $ret['alert'] = \Index\Email\Model::sendApprove();
                    }
                    // เคลียร์
                    $request->removeToken();
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
            // คืนค่าเป็น json
            echo json_encode($ret);
        }
    }
}
