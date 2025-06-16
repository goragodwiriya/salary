<?php
/**
 * @filesource modules/index/views/editprofile.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Editprofile;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=editprofile
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มแก้ไขสมาชิก
     *
     * @param Request $request
     * @param array   $user
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $user, $login)
    {
        // แอดมิน
        $isAdmin = Login::isAdmin();
        // หมวดหมู่
        $category = \Index\Category\Model::init(false);
        // form
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/index/model/editprofile/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        if ($user['active'] == 1) {
            $fieldset = $form->add('fieldset', [
                'title' => '{LNG_Login information}'
            ]);
            $groups = $fieldset->add('groups');
            // username
            $groups->add('text', [
                'id' => 'register_username',
                'itemClass' => 'width50',
                'labelClass' => 'g-input icon-email',
                'label' => '{LNG_Email}',
                'comment' => '{LNG_Username used for login or request a new password}',
                'disabled' => $isAdmin ? false : true,
                'maxlength' => 50,
                'value' => $user['username'],
                'validator' => ['keyup,change', 'checkUsername', 'index.php/index/model/checker/username']
            ]);
            // password, repassword
            $groups = $fieldset->add('groups', [
                'comment' => '{LNG_To change your password, enter your password to match the two inputs}'
            ]);
            // password
            $groups->add('password', [
                'id' => 'register_password',
                'itemClass' => 'width50',
                'labelClass' => 'g-input icon-password',
                'label' => '{LNG_Password}',
                'placeholder' => '{LNG_Passwords must be at least four characters}',
                'maxlength' => 50,
                'showpassword' => true,
                'validator' => ['keyup,change', 'checkPassword']
            ]);
            // repassword
            $groups->add('password', [
                'id' => 'register_repassword',
                'itemClass' => 'width50',
                'labelClass' => 'g-input icon-password',
                'label' => '{LNG_Confirm password}',
                'placeholder' => '{LNG_Enter your password again}',
                'maxlength' => 50,
                'showpassword' => true,
                'validator' => ['keyup,change', 'checkPassword']
            ]);
        }
        $fieldset = $form->add('fieldset', [
            'title' => '{LNG_Details of} {LNG_User}'
        ]);
        $groups = $fieldset->add('groups');
        // name
        $groups->add('text', [
            'id' => 'register_name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Name}',
            'maxlength' => 150,
            'value' => $user['name']
        ]);
        // sex
        $groups->add('select', [
            'id' => 'register_sex',
            'labelClass' => 'g-input icon-sex',
            'itemClass' => 'width50',
            'label' => '{LNG_Sex}',
            'options' => Language::get('SEXES'),
            'value' => $user['sex']
        ]);
        // หมวดหมู่
        $a = 0;
        foreach ($category->items() as $k => $label) {
            if ($isAdmin || !$category->isEmpty($k)) {
                if (in_array($k, self::$cfg->categories_multiple)) {
                    if (!$category->isEmpty($k)) {
                        $fieldset->add('checkboxgroups', [
                            'id' => 'register_'.$k,
                            'itemClass' => 'item',
                            'label' => $category->name($k),
                            'labelClass' => 'g-input icon-group',
                            'options' => $category->toSelect($k),
                            'value' => empty($user[$k]) ? [] : $user[$k],
                            'disabled' => !$isAdmin && in_array($k, self::$cfg->categories_disabled)
                        ]);
                    }
                } else {
                    if ($a % 2 == 0) {
                        $groups = $fieldset->add('groups');
                    }
                    $a++;
                    if ($isAdmin) {
                        $groups->add('text', [
                            'id' => 'register_'.$k,
                            'labelClass' => 'g-input icon-menus',
                            'itemClass' => 'width50',
                            'label' => $label,
                            'datalist' => $category->toSelect($k),
                            'value' => empty($user[$k]) ? '' : $user[$k][0],
                            'text' => true
                        ]);
                    } else {
                        $groups->add('select', [
                            'id' => 'register_'.$k,
                            'labelClass' => 'g-input icon-menus',
                            'itemClass' => 'width50',
                            'label' => $label,
                            'options' => $category->toSelect($k),
                            'value' => empty($user[$k]) ? '' : $user[$k][0],
                            'disabled' => !$isAdmin && in_array($k, self::$cfg->categories_disabled)
                        ]);
                    }
                }
            }
        }
        $groups = $fieldset->add('groups');
        // id_card
        $groups->add('number', [
            'id' => 'register_id_card',
            'labelClass' => 'g-input icon-profile',
            'itemClass' => 'width50',
            'label' => '{LNG_Identification No.}',
            'maxlength' => 13,
            'value' => $user['id_card'],
            'validator' => ['keyup,change', 'checkIdcard']
        ]);
        // phone
        $groups->add('text', [
            'id' => 'register_phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'maxlength' => 32,
            'value' => $user['phone']
        ]);
        // address
        $fieldset->add('text', [
            'id' => 'register_address',
            'labelClass' => 'g-input icon-address',
            'itemClass' => 'item',
            'label' => '{LNG_Address}',
            'maxlength' => 150,
            'value' => $user['address']
        ]);
        $groups = $fieldset->add('groups');
        // country
        $groups->add('text', [
            'id' => 'register_country',
            'labelClass' => 'g-input icon-world',
            'itemClass' => 'width33',
            'label' => '{LNG_Country}',
            'datalist' => \Kotchasan\Country::all(),
            'value' => $user['country']
        ]);
        // provinceID
        $groups->add('text', [
            'id' => 'register_province',
            'name' => 'register_provinceID',
            'labelClass' => 'g-input icon-location',
            'itemClass' => 'width33',
            'label' => '{LNG_Province}',
            'datalist' => [],
            'text' => $user['province'],
            'value' => $user['provinceID']
        ]);
        // zipcode
        $groups->add('number', [
            'id' => 'register_zipcode',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width33',
            'label' => '{LNG_Zipcode}',
            'maxlength' => 10,
            'value' => $user['zipcode']
        ]);
        $groups = $fieldset->add('groups');
        if (!empty(self::$cfg->line_official_account) && !empty(self::$cfg->line_channel_access_token) && $user['social'] != 3) {
            // line_uid
            $groups->add('text', [
                'id' => 'register_line_uid',
                'itemClass' => 'width50',
                'labelClass' => 'g-input icon-line',
                'label' => '{LNG_LINE user ID}',
                'placeholder' => 'U1234abc...',
                'comment' => '{LNG_Enter the LINE user ID you received when adding friends. Or type userId sent to the official account to request a new user ID. This information is used for receiving private messages from the system via LINE.}',
                'maxlength' => 33,
                'value' => $user['line_uid']
            ]);
        }
        if (!empty(self::$cfg->telegram_bot_token) && !empty(self::$cfg->telegram_bot_username)) {
            // telegram_id
            $groups->add('text', [
                'id' => 'register_telegram_id',
                'itemClass' => 'width50',
                'labelClass' => 'g-input icon-telegram',
                'label' => '{LNG_Telegram ID}',
                'placeholder' => '{LNG_Log in to Telegram to request an ID}',
                'maxlength' => 13,
                'value' => $user['telegram_id']
            ]);
            $groups->add('div', [
                'class' => 'width20',
                'innerHTML' => '<div>&nbsp;</div><div id=telegram_login class=right data-username="'.self::$cfg->telegram_bot_username.'"></div>'
            ]);
        }
        // รูปภาพสมาชิก
        foreach (self::$cfg->member_images as $key => $label) {
            // delete_$key
            $fieldset->add('checkbox', [
                'id' => 'delete_'.$key,
                'itemClass' => 'item',
                'label' => '{LNG_Remove} '.$label,
                'value' => 1
            ]);
            // icon
            $ext = $key === 'avatar' ? self::$cfg->stored_img_type : '.png';
            if (is_file(ROOT_PATH.DATA_FOLDER.$key.'/'.$user['id'].$ext)) {
                $img = WEB_URL.DATA_FOLDER.$key.'/'.$user['id'].$ext.'?'.time();
            } else {
                $img = WEB_URL.'skin/img/noicon.png';
            }
            $fieldset->add('file', [
                'id' => $key,
                'labelClass' => 'g-input icon-image',
                'itemClass' => 'item',
                'label' => $label,
                'comment' => '{LNG_Browse image uploaded, type :type} ({LNG_resized automatically})',
                'dataPreview' => $key.'Image',
                'previewSrc' => $img,
                'accept' => self::$cfg->member_img_typies
            ]);
        }
        $fieldset = $form->add('fieldset', [
            'title' => '{LNG_Other}'
        ]);
        // status
        $fieldset->add('select', [
            'id' => 'register_status',
            'itemClass' => 'item',
            'label' => '{LNG_Member status}',
            'labelClass' => 'g-input icon-star0',
            'disabled' => $isAdmin && $user['id'] != $login['id'] && $user['id'] != 1 ? false : true,
            'options' => self::$cfg->member_status,
            'value' => $user['status']
        ]);
        if ($isAdmin) {
            // permission
            $fieldset->add('checkboxgroups', [
                'id' => 'register_permission',
                'itemClass' => 'item',
                'label' => '{LNG_Permission}',
                'labelClass' => 'g-input icon-list',
                'options' => \Gcms\Controller::getPermissions(),
                'value' => $user['permission']
            ]);
        }
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);
        $fieldset->add('hidden', [
            'id' => 'register_id',
            'value' => $user['id']
        ]);
        \Gcms\Controller::$view->setContentsAfter([
            '/:type/' => implode(', ', self::$cfg->member_img_typies)
        ]);
        // Javascript
        $form->script('initEditProfile("register");');
        // คืนค่า HTML
        return $form->render();
    }
}
