<?php
/**
 * @filesource modules/salary/models/email.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Email;

use Kotchasan\Language;
use Salary\Base\Model as Base;

/**
 * แจ้งเตือนรายการเงินเดือนทาง Email, LINE และ Telegram
 *
 * ส่งถึงเจ้าของเงินเดือน และผู้ดูแลระบบ (status = 1) เหมือนระบบเดิม
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ส่งข้อความแจ้งเตือน
     *
     * @param object|array $salary ข้อมูลเงินเดือน 1 รายการ
     * @param string $action approve|create|recalculate|send_email
     *
     * @return string ข้อความผลการส่ง
     */
    public static function send($salary, $action = 'approve')
    {
        $salary = (object) $salary;

        $lines = [];
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }

        $employee_name = isset($salary->name) ? $salary->name : '';
        $employee_email = '';
        $line_uid = '';
        $telegram_id = '';

        // โหมดตัวอย่างส่งหาเจ้าของเงินเดือนกับแอดมินคนแรกเท่านั้น
        $where = self::$cfg->demo_mode
            ? [['U.id', [(int) $salary->member_id, 1]]]
            : [['U.id', (int) $salary->member_id], ['U.status', 1]];

        $query = static::createQuery()
            ->select('U.id', 'U.username', 'U.name', 'U.line_uid', 'U.telegram_id')
            ->from('user U')
            ->where([['U.active', 1]])
            ->where($where, 'OR')
            ->groupBy('U.id');

        foreach ($query->fetchAll() as $item) {
            if ((int) $item->id === (int) $salary->member_id) {
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

        $actions = [
            'approve' => 'Approved',
            'recalculate' => 'Re-Calculate',
            'create' => 'Created',
            'send_email' => 'Notification'
        ];
        $actionText = Language::get(isset($actions[$action]) ? $actions[$action] : 'Updated');

        $msg = [
            '{LNG_Salary} '.$actionText.' ['.self::$cfg->web_title.']',
            '{LNG_Name} : '.$employee_name,
            '{LNG_Month} {LNG_Year} : '.Base::periodText($salary->year, $salary->month),
            '{LNG_Basic Salary} : '.number_format((float) $salary->basic_salary, 2).' {LNG_THB}',
            '{LNG_Allowance} : '.number_format((float) $salary->allowance, 2).' {LNG_THB}',
            Base::overtimeLabel($salary->overtime_hours ?? 0).' : '.number_format((float) $salary->overtime, 2).' {LNG_THB}',
            '{LNG_Bonus} : '.number_format((float) $salary->bonus, 2).' {LNG_THB}',
            '{LNG_Deduction} : '.number_format((float) $salary->deduction, 2).' {LNG_THB}',
            '{LNG_Social Security} : '.number_format((float) $salary->social_security, 2).' {LNG_THB}',
            '{LNG_Income Tax} : '.number_format((float) $salary->tax, 2).' {LNG_THB}',
            '{LNG_Net Salary} : '.number_format((float) $salary->net_salary, 2).' {LNG_THB}',
            '{LNG_Status} : '.Base::statusText($salary->status)
        ];
        if (!empty($salary->remark)) {
            $msg[] = '{LNG_Remark} : '.$salary->remark;
        }
        $msg[] = 'URL : '.WEB_URL.'salary';

        $message = Language::trans(implode("\n", $msg));

        $ret = [];
        $sent = false;

        if (!empty(self::$cfg->telegram_bot_token)) {
            $sent = true;
            foreach ([$telegrams, $telegram_id] as $to) {
                if (!empty($to)) {
                    $err = \Gcms\Telegram::sendTo($to, $message);
                    if ($err != '') {
                        $ret[] = $err;
                    }
                }
            }
        }

        if (!empty(self::$cfg->line_channel_access_token)) {
            $sent = true;
            foreach ([$lines, $line_uid] as $to) {
                if (!empty($to)) {
                    $err = \Gcms\Line::sendTo($to, $message);
                    if ($err != '') {
                        $ret[] = $err;
                    }
                }
            }
        }

        if (self::$cfg->noreply_email != '') {
            $subject = Language::trans('['.self::$cfg->web_title.'] {LNG_Salary} '.$actionText);
            $body = nl2br($message);
            $recipients = $emails;
            // ส่งอีเมลถึงเจ้าของเงินเดือนเสมอ
            if (!empty($employee_email)) {
                array_unshift($recipients, $employee_name.'<'.$employee_email.'>');
            }
            foreach ($recipients as $item) {
                $sent = true;
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $body);
                if ($err->error()) {
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }

        if (!$sent) {
            // ไม่ได้ตั้งค่าช่องทางแจ้งเตือนไว้เลย
            return Language::get('Saved successfully');
        }

        return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
    }
}
