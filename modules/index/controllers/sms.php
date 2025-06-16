<?php
/**
 * @filesource modules/index/controllers/sms.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Sms;

use Gcms\Config;
use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=sms
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตั้งค่า SMS
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('SMS Settings');
        // เลือกเมนู
        $this->menu = 'settings';
        // แอดมิน, ไม่ใช่สมาชิกตัวอย่าง
        if (Login::notDemoMode(Login::isAdmin())) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', [
                'class' => 'breadcrumbs'
            ]);
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-settings">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_SMS Settings}</span></li>');
            $section->add('header', [
                'innerHTML' => '<h2 class="icon-mobile">'.$this->title.'</h2>'
            ]);
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'sms'));
            // โหลด config
            $config = Config::load(ROOT_PATH.'settings/config.php');
            $div = $section->add('div', [
                'class' => 'content_bg'
            ]);
            // แสดงฟอร์ม
            $div->appendChild(\Index\Sms\View::create()->render($config));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
