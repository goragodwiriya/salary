<?php
/**
 * @filesource modules/index/views/otp.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Otp;

use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Template;

/**
 * ยืนยัน OTP (Modal)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มยืนยันหมายเลขโทรศัพท์ (OTP)
     *
     * @param Request $request
     * @param array $payload
     * @param string $token
     *
     * @return string
     */
    public static function render(Request $request, $payload, $token)
    {
        // otp.html
        $template = Template::createFromFile(ROOT_PATH.self::$cfg->skin.'/otp.html');
        $template->add([
            '/{ID}/' => $token,
            '/{TIME}/' => \Gcms\View::getTimeElapsed($payload['expired']),
            '/{TOKEN}/' => $request->createToken(),
            /* ภาษา */
            '/{LNG_([^}]+)}/e' => '\Kotchasan\Language::parse(array(1=>"$1"))',
            /* ภาษา ที่ใช้งานอยู่ */
            '/{LANGUAGE}/' => Language::name(),
            /* template name */
            '/{SKIN}/' => self::$cfg->skin,
            /* website URL */
            '/{WEBURL}/' => WEB_URL,
            // เลขเวอร์ชั่นของไฟล์
            '/{REV}/' => isset(self::$cfg->reversion) ? self::$cfg->reversion : ''
        ]);
        // คืนค่า HTML
        return $template->render();
    }
}
