<?php
/**
 * @filesource modules/salary/controllers/settings.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Settings;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตั้งค่าโมดูล salary
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Module settings} {LNG_Salary}');
        // เลือกเมนู
        $this->menu = 'settings';
        // สมาชิก
        $login = Login::isMember();
        // สามารถตั้งค่าระบบได้
        if (Login::checkPermission($login, 'can_config')) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', [
                'class' => 'breadcrumbs'
            ]);
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-money">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_Salary}</span></li>');
            $section->add('header', [
                'innerHTML' => '<h2 class="icon-settings">'.$this->title.'</h2>'
            ]);
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'salary'));
            $div = $section->add('div', [
                'class' => 'content_bg'
            ]);
            // แสดงฟอร์ม
            $div->appendChild(\Salary\Settings\View::create()->render());
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
