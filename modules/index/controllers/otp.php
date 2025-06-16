<?php
/**
 * @filesource modules/index/controllers/otp.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Otp;

use Kotchasan;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;
use Kotchasan\Jwt;

/**
 * module=otp
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * forgot, login register
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        try {
            // ID
            $token = $request->get('id', '')->toString();
            // JWT
            $jwt = Jwt::create(self::$cfg->password_key);
            // decode JWT
            $payload = $jwt->decode($token);
            if ($payload) {
                // อ่าน USER ตามที่เลือก (OTP)
                $user = \Index\Otp\Model::get($payload['username']);
                if ($user && preg_match('/^([0-9]{4,4}):([0-9]+)$/', $user->activatecode, $codes)) {
                    // เวลาหมดอายุ
                    $payload['expired'] = (int) $codes[2];
                    // otp.html
                    $content = \Index\Otp\View::create()->render($request, $payload, $token);
                    // ส่งออก เป็น HTML
                    $response = new Response();
                    $response->withContent($content)->send();
                    exit;
                }
            }
        } catch (\Exception $ex) {
        }
        // 404
        return \Index\Error\Controller::execute($this);
    }
}
