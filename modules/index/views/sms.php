<?php
/**
 * @filesource modules/index/views/sms.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Sms;

use Kotchasan\Html;

/**
 * module=sms.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มตั้งค่า sms.
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
            'action' => 'index.php/index/model/sms/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-host',
            'title' => '{LNG_API settings} <a href="https://www.thaibulksms.com/" target=_blank class=icon-help></a>'
        ]);
        // sms_username
        $fieldset->add('text', [
            'id' => 'sms_username',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Username}',
            'value' => isset($config->sms_username) ? $config->sms_username : ''
        ]);
        // sms_password
        $fieldset->add('password', [
            'id' => 'sms_password',
            'labelClass' => 'g-input icon-password',
            'itemClass' => 'item',
            'label' => '{LNG_Password}',
            'value' => isset($config->sms_password) ? $config->sms_password : ''
        ]);
        // sms_api_key
        $fieldset->add('text', [
            'id' => 'sms_api_key',
            'labelClass' => 'g-input icon-password',
            'itemClass' => 'item',
            'label' => '{LNG_API Key}',
            'value' => isset($config->sms_api_key) ? $config->sms_api_key : ''
        ]);
        // sms_api_secret
        $fieldset->add('text', [
            'id' => 'sms_api_secret',
            'labelClass' => 'g-input icon-password',
            'itemClass' => 'item',
            'label' => '{LNG_API Secret}',
            'value' => isset($config->sms_api_secret) ? $config->sms_api_secret : ''
        ]);
        // sms_sender
        $fieldset->add('text', [
            'id' => 'sms_sender',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'item',
            'label' => '{LNG_Sender Name}',
            'comment' => '{LNG_SMS_SENDER_COMMENT}',
            'value' => empty($config->sms_sender) ? 'THAIBLUKSMS' : $config->sms_sender
        ]);
        // sms_type
        $fieldset->add('select', [
            'id' => 'sms_type',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Credit Type}',
            'options' => \Gcms\Sms::check_credit(),
            'value' => isset($config->sms_type) ? $config->sms_type : 'standard'
        ]);
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);

        return $form->render();
    }
}
