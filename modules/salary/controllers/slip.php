<?php
/**
 * @filesource modules/salary/controllers/slip.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Slip;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * export.php?module=salary-export
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\Controller
{
    /**
     * สร้างสลิปเงินเดือนสำหรับพิมพ์
     *
     * @param Request $request
     */
    public function export(Request $request)
    {
        $id = $request->request('id')->toInt();
        $login = Login::isMember();
        if ($id > 0 && $login) {
            // หน้าพิมพ์สลิปเงินเดือน
            $content = \Salary\Slip\View::create()->render($request, $id, $login);
            if ($content !== '') {
                $template = file_get_contents(ROOT_PATH.'modules/salary/views/print.html');
                $template = preg_replace('/{CONTENT}/', $content, $template);
                return Language::trans($template);
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
