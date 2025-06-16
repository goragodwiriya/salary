<?php
/**
 * @filesource modules/salary/models/email.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Email;

use Kotchasan\Language;

/**
 * ส่งอีเมลและ LINE แจ้งการอนุมัติเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ส่งอีเมลและ LINE แจ้งการอนุมัติเงินเดือน
     *
     * @param array $salary ข้อมูลเงินเดือน
     * @param string $action การกระทำ (approve, recalculate)
     *
     * @return string
     */
    public static function send($salary, $action = 'approve')
    {
        $lines = [];
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }

        $employee_name = '';
        $employee_email = '';
        $line_uid = '';
        $telegram_id = '';

        // ตรวจสอบรายชื่อผู้รับ
        if (self::$cfg->demo_mode) {
            // โหมดตัวอย่าง ส่งหาเจ้าของเงินเดือนและแอดมินเท่านั้น
            $where = [
                ['id', [$salary['member_id'], 1]]
            ];
        } else {
            // ส่งหาเจ้าของเงินเดือน, ผู้ดูแลเงินเดือน และแอดมิน
            $where = [
                // เจ้าของเงินเดือน
                ['U.id', $salary['member_id']],
                // แอดมิน
                ['U.status', 1]
            ];
        }

        // ตรวจสอบรายชื่อผู้รับ
        $query = static::createQuery()
            ->select('U.id', 'U.username', 'U.name', 'U.line_uid', 'U.telegram_id')
            ->from('user U')
            ->where(['U.active', 1])
            ->andWhere($where, 'OR')
            ->groupBy('U.id')
            ->cacheOn();

        foreach ($query->execute() as $item) {
            if ($item->id == $salary['member_id']) {
                // เจ้าของเงินเดือน
                $employee_name = $item->name;
                $employee_email = $item->username;
                $line_uid = $item->line_uid;
                $telegram_id = $item->telegram_id;
            } else {
                // เจ้าหน้าที่/แอดมิน
                $emails[] = $item->name.'<'.$item->username.'>';
                if (!empty($item->line_uid)) {
                    $lines[$item->line_uid] = $item->line_uid;
                }
                if (!empty($item->telegram_id)) {
                    $telegrams[$item->telegram_id] = $item->telegram_id;
                }
            }
        }

        // สถานะ
        $status = $salary['status'] == 1 ? Language::get('Approved') : Language::get('Waiting for approval');
        $actionText = '';
        switch ($action) {
            case 'approve':
                $actionText = Language::get('Approved');
                break;
            case 'recalculate':
                $actionText = Language::get('Re-Calculate');
                break;
            case 'create':
                $actionText = Language::get('Created');
                break;
            case 'send_email':
                $actionText = Language::get('Notification');
                break;
            default:
                $actionText = Language::get('Updated');
        }

        // ข้อความ
        $monthNames = Language::get('MONTH_LONG');
        $monthName = isset($monthNames[(int) $salary['month']]) ? $monthNames[(int) $salary['month']] : $salary['month'];

        $msg = [
            '{LNG_Salary} '.$actionText.' ['.self::$cfg->web_title.']',
            '{LNG_Name} : '.$employee_name,
            '{LNG_Month} {LNG_Year} : '.$monthName.' '.$salary['year'],
            '{LNG_Basic Salary} : '.number_format($salary['basic_salary'], 2).' {LNG_THB}',
            '{LNG_Allowance} : '.number_format($salary['allowance'], 2).' {LNG_THB}',
            '{LNG_Overtime} : '.number_format($salary['overtime'], 2).' {LNG_THB}',
            '{LNG_Bonus} : '.number_format($salary['bonus'], 2).' {LNG_THB}',
            '{LNG_Deduction} : '.number_format($salary['deduction'], 2).' {LNG_THB}',
            '{LNG_Social Security} : '.number_format($salary['social_security'], 2).' {LNG_THB}',
            '{LNG_Income Tax} : '.number_format($salary['tax'], 2).' {LNG_THB}',
            '{LNG_Net Salary} : '.number_format($salary['net_salary'], 2).' {LNG_THB}',
            '{LNG_Status} : '.$status
        ];

        if (!empty($salary['remark'])) {
            $msg[] = '{LNG_Remark} : '.$salary['remark'];
        }

        $msg[] = 'URL : '.WEB_URL.'index.php?module=salary';

        // ข้อความของ employee
        $employee_msg = Language::trans(implode("\n", $msg));
        // ข้อความของแอดมิน
        $admin_msg = $employee_msg;

        // ส่งข้อความ
        $ret = [];
        if (!empty(self::$cfg->telegram_bot_token)) {
            // Telegram (Admin)
            $err = \Gcms\Telegram::sendTo($telegrams, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // Telegram (Employee)
            $err = \Gcms\Telegram::sendTo($telegram_id, $employee_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }

        if (!empty(self::$cfg->line_channel_access_token)) {
            // LINE (Admin)
            $err = \Gcms\Line::sendTo($lines, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // LINE (Employee)
            $err = \Gcms\Line::sendTo($line_uid, $employee_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }

        if (self::$cfg->noreply_email != '') {
            // หัวข้ออีเมล
            $subject = '['.self::$cfg->web_title.'] {LNG_Salary} '.$actionText;
            $subject = Language::trans($subject);

            // ส่งอีเมลไปยังเจ้าของเงินเดือนเสมอ
            if (!empty($employee_email)) {
                $err = \Kotchasan\Email::send($employee_name.'<'.$employee_email.'>', self::$cfg->noreply_email, $subject, nl2br($employee_msg));
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }

            // รายละเอียดในอีเมล (แอดมิน)
            $admin_msg = nl2br($admin_msg);
            foreach ($emails as $item) {
                // ส่งอีเมล
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $admin_msg);
                if ($err->error()) {
                    // คืนค่า error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }

        if (isset($err)) {
            // ส่งอีเมลสำเร็จ หรือ error การส่งเมล
            return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
        } else {
            // ไม่มีอีเมลต้องส่ง
            return Language::get('Saved successfully');
        }
    }
}
