<?php
/**
 * @filesource modules/salary/controllers/write.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Write;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟอร์มเพิ่ม/แก้ไขข้อมูลเงินเดือน
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Salary');
        // เลือกเมนู
        $this->menu = 'setup';
        // ข้อมูลที่จะแก้ไข
        $salary = \Salary\Setup\Model::get($request->request('id')->toInt());
        // Login
        $login = Login::isMember();
        if ($salary && $login) {
            // สามารถจัดการเงินเดือนได้
            if (Login::checkPermission($login, 'can_manage_salary')) {
                $title = $salary->id === 0 ? Language::get('Add') : Language::get('Edit');
                $this->title = $title.' '.$this->title;
                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', [
                    'class' => 'breadcrumbs'
                ]);
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-money">{LNG_Module}</span></li>');
                $ul->appendChild('<li><span>{LNG_Salary}</span></li>');
                $ul->appendChild('<li><span>'.$title.'</span></li>');
                $section->add('header', [
                    'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
                ]);
                // menu
                $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'salary'));
                $div = $section->add('div', [
                    'class' => 'content_bg'
                ]);
                // ฟอร์ม
                $div->appendChild(\Salary\Write\View::create()->render($request, $salary, $login));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
