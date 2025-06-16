<?php
/**
 * @filesource modules/salary/controllers/index.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Index;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * แสดงรายการประวัติเงินเดือน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('My Slip');
        // เลือกเมนู
        $this->menu = 'salary';
        // ตรวจสอบการ login
        if ($login = Login::isMember()) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', [
                'class' => 'breadcrumbs'
            ]);
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
            $ul->appendChild('<li><span>{LNG_My Slip}</span></li>');
            $section->add('header', [
                'innerHTML' => '<h2 class="icon-money">'.$this->title.'</h2>'
            ]);
            // แสดงตาราง
            $div = $section->add('div', [
                'class' => 'content_bg'
            ]);
            // แสดงผล
            $div->appendChild(\Salary\Index\View::create()->render($request, $login));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
