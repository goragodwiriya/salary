<?php
/**
 * @filesource modules/index/views/telegramsettings.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Telegramsettings;

use Kotchasan\Html;

/**
 * module=telegramsettings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มตั้งค่า Telegram Bot
     *
     * @param object $config
     *
     * @return string
     */
    public function render($config)
    {
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/index/model/telegramsettings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-telegram',
            'title' => '{LNG_Telegram settings}'
        ]);
        // telegram_bot_username
        $fieldset->add('text', [
            'id' => 'telegram_bot_username',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Bot Username} ({LNG_Telegram Login Widget})',
            'placeholder' => '/mybots',
            'value' => isset(self::$cfg->telegram_bot_username) ? self::$cfg->telegram_bot_username : ''
        ]);
        // telegram_chat_id
        $fieldset->add('text', [
            'id' => 'telegram_chat_id',
            'labelClass' => 'g-input icon-support',
            'itemClass' => 'item',
            'label' => '{LNG_Chat ID}',
            'placeholder' => '@my_notify',
            'value' => isset(self::$cfg->telegram_chat_id) ? self::$cfg->telegram_chat_id : ''
        ]);
        $groups = $fieldset->add('groups', [
            'comment' => '{LNG_Send notification messages When making a transaction}'
        ]);
        // telegram_bot_token
        $groups->add('text', [
            'id' => 'telegram_bot_token',
            'labelClass' => 'g-input icon-password',
            'itemClass' => 'width90',
            'label' => '{LNG_Bot token} <a href="https://www.kotchasan.com/knowledge/how_to_obtain_a_telegram_bot_api_token_and_get_started.html" target=_blank class=icon-help></a>',
            'value' => isset(self::$cfg->telegram_bot_token) ? self::$cfg->telegram_bot_token : ''
        ]);
        $groups->add('button', [
            'id' => 'telegram_test',
            'itemClass' => 'width10',
            'labelClass' => 'g-input',
            'class' => 'magenta button wide center icon-telegram',
            'label' => '&nbsp;',
            'value' => 'Test'
        ]);
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);
        // Javascript
        $form->script('initTelegramSettings();');
        // คืนค่า HTML
        return $form->render();
    }
}
