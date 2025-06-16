<?php
/**
 * @filesource modules/index/models/email.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Email;

use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE และ SMS ไปยังผู้ที่เกี่ยวข้อง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ส่งอีเมลสมัครสมาชิก, ยืนยันสมาชิก
     *
     * @param array $save
     * @param string $password
     *
     * @return string
     */
    public static function send($save, $password)
    {
        if (preg_match('/^[0-9]{10,10}$/', $save['username'])) {
            // Code
            $codes = explode(':', $save['activatecode']);
            // OTP
            $msg = Language::replace('Your OTP code is :otp. Please enter this code on the website to confirm your phone number.', [':otp' => $codes[0]]);
            // send SMS
            $err = \Gcms\Sms::send($save['username'], $msg);
        } else {
            // send Email
            $msg = "{LNG_Your registration information}<br>\n<br>\n";
            $msg .= '{LNG_Username} : '.$save['username']."<br>\n";
            $msg .= '{LNG_Password} : '.$password."<br>\n";
            $msg .= '{LNG_Name} : '.$save['name'];
            if (!empty($save['activatecode'])) {
                $url = WEB_URL.'index.php?module=welcome&amp;id='.$save['activatecode'];
                $msg .= "<br>\n{LNG_Please click the link to verify your email address.} : <a href='$url'>".$url.'</a>';
            }
            $msg = Language::trans($msg);
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Welcome new members');
            $err = \Kotchasan\Email::send($save['username'], self::$cfg->noreply_email, $subject, $msg);
            $err = $err->error() ? $err->getErrorMessage() : '';
        }
        return strip_tags($err);
    }

    /**
     * ส่งข้อความแจ้งเตือนการสมัครสมาชิกของ user
     *
     * @return string
     */
    public static function sendApprove()
    {
        $title = Language::get('Please check the new member registration.');
        $msg = $title."<br>\n<br>\n".WEB_URL.'index.php?module=member&sort=active';
        $msg = Language::trans($msg);
        $subject = '['.self::$cfg->web_title.'] '.$title;
        // แอดมิน (สามารถอนุมัติสมาชิกได้)
        $query = \Kotchasan\Model::createQuery()
            ->select('username', 'telegram_id')
            ->from('user')
            ->where([
                ['status', 1],
                ['active', 1]
            ]);
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }
        foreach ($query->execute() as $item) {
            $emails[] = $item->username;
            if (!empty($item->telegram_id)) {
                $telegrams[$item->telegram_id] = $item->telegram_id;
            }
        }
        // ส่งอีเมลไปยังแอดมิน
        \Kotchasan\Email::send(implode(',', $emails), self::$cfg->noreply_email, $subject, $msg);
        // ส่งข้อความไปยัง Telegram
        \Gcms\Telegram::sendTo($telegrams, $msg);
        // ข้อความแจ้งไปยัง user
        return Language::get('The message has been sent to the admin successfully. Please wait a moment for the admin to approve the registration. You can log back in later if approved.');
    }

    /**
     * ส่งข้อความอนุมัติ user
     *
     * @param array $ids
     *
     * @return string
     */
    public static function sendActive($ids = [])
    {
        $title = Language::get('Your account has been approved.');
        $msg = $title.' '.Language::get('You can login at')."<br>\n<br>\n".WEB_URL;
        $msg = Language::trans($msg);
        $subject = '['.self::$cfg->web_title.'] '.$title;
        // แอดมิน (สามารถอนุมัติสมาชิกได้)
        $query = \Kotchasan\Model::createQuery()
            ->select('username', 'name', 'line_uid', 'telegram_id')
            ->from('user')
            ->where([
                ['id', $ids],
                ['id', '!=', 1],
                ['active', 1]
            ]);
        foreach ($query->execute() as $item) {
            if (preg_match('/^[0-9]{10,10}$/', $item->username)) {
                // send SMS
                \Gcms\Sms::send($item->username, $msg);
            } else {
                // send Email
                \Kotchasan\Email::send($item->name.'<'.$item->username.'>', self::$cfg->noreply_email, $subject, $msg);
            }
            // ส่งข้อความไปยัง Line
            \Gcms\Line::sendTo($item->line_uid, $msg);
            // ส่งข้อความไปยัง Telegram Bot
            \Gcms\Telegram::sendTo($item->telegram_id, $msg);
        }
    }
}
