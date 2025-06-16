<?php
/**
 * @filesource modules/salary/controllers/upload.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Upload;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-upload
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * อัปโหลดข้อมูลเงินเดือนจากไฟล์ CSV
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Upload Salary Data');
        // เลือกเมนู
        $this->menu = 'salary';
        // ตรวจสอบสิทธิ์
        if ($login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_config')) {

                // แสดงผล
                $section = Html::create('section');
                // breadcrumbs
                $breadcrumbs = $section->add('nav', [
                    'class' => 'breadcrumbs'
                ]);
                $ul = $breadcrumbs->add('ul');
                $ul->appendChild('<li><span class="icon-payroll">{LNG_Module}</span></li>');
                $ul->appendChild('<li><span>{LNG_Salary Management}</span></li>');
                $ul->appendChild('<li><span>{LNG_Upload Salary Data}</span></li>');
                $section->add('header', [
                    'innerHTML' => '<h2 class="icon-upload">'.$this->title.'</h2>'
                ]);
                // แสดงฟอร์ม
                $div = $section->add('div', [
                    'class' => 'content_bg'
                ]);
                $div->appendChild(\Salary\Upload\View::create()->render($request));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
