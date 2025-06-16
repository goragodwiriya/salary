<?php
/**
 * @filesource modules/index/models/telegram.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Telegram;

use Kotchasan\Http\Request;

/**
 * module=telegramsettings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * ทดสอบการส่ง Telegram
     *
     * @param Request $request
     */
    public function test(Request $request)
    {
        // referer
        if ($request->isReferer() && $request->isAjax()) {
            $bot_token = $request->post('bot_token')->topic();
            $chat_id = $request->post('chat_id')->topic();
            // ทดสอบส่งข้อความ Telegram
            echo \Gcms\Telegram::sendTo($chat_id, strip_tags(self::$cfg->web_title), $bot_token);
        }
    }
}
