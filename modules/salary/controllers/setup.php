<?php
/**
 * @filesource modules/salary/controllers/setup.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Setup;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * แสดงรายการเงินเดือนรายเดือนของทุกคน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_List of} {LNG_Salary}');
        // เลือกเมนู
        $this->menu = 'report';
        // Login
        if ($login = Login::isMember()) {
            // สามารถจัดการเงินเดือนได้
            if (Login::checkPermission($login, 'can_manage_salary')) {
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', [
                    'class' => 'breadcrumbs'
                ]);
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-money">{LNG_Module}</span></li>');
                $ul->appendChild('<li><span>{LNG_Salary}</span></li>');
                $ul->appendChild('<li><span>{LNG_List of}</span></li>');
                $section->add('header', [
                    'innerHTML' => '<h2 class="icon-list">'.$this->title.'</h2>'
                ]);
                // menu
                $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'salary'));
                $div = $section->add('div', [
                    'class' => 'content_bg'
                ]);
                // ตาราง
                $div->appendChild(\Salary\Setup\View::create()->render($request, $login));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
