<?php
/**
 * @filesource modules/salary/controllers/importusers.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Importusers;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-importusers
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * นำเข้าข้อมูลพนักงาน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::trans('{LNG_Import} {LNG_Employee}');
        // เลือกเมนู
        $this->menu = 'report';
        // สามารถจัดการรายการเงินเดือนได้
        if (Login::checkPermission(Login::isMember(), 'can_manage_salary')) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', [
                'class' => 'breadcrumbs'
            ]);
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-users">{LNG_Employee}</span></li>');
            $ul->appendChild('<li><a href="index.php?module=salary-setup&id=0">{LNG_Salary}</a></li>');
            $ul->appendChild('<li><span>{LNG_Import}</span></li>');
            $section->add('header', [
                'innerHTML' => '<h2 class="icon-import">'.$this->title.'</h2>'
            ]);
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'salary'));
            $div = $section->add('div', [
                'class' => 'content_bg'
            ]);
            // แสดงฟอร์ม
            $div->appendChild(\Salary\Importusers\View::create()->render());
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
