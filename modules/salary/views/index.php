<?php
/**
 * @filesource modules/salary/views/index.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Index;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Number;

/**
 * module=salary
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * รายการประวัติเงินเดือน
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        // URL สำหรับส่งค่า
        $uri = self::$request->getUri();
        // ตาราง
        $table = new DataTable([
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Salary\Index\Model::toDataTable($login),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('salaryIndex_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => 'year DESC,month DESC',
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => [$this, 'onRow'],
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => ['id', 'year'],
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/salary/model/index/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => [
                'month' => [
                    'text' => '{LNG_Month} {LNG_Year}'
                ],
                'basic_salary' => [
                    'text' => '{LNG_Basic Salary}',
                    'class' => 'center'
                ],
                'allowance' => [
                    'text' => '{LNG_Allowance}',
                    'class' => 'center'
                ],
                'overtime' => [
                    'text' => '{LNG_Overtime}',
                    'class' => 'center'
                ],
                'bonus' => [
                    'text' => '{LNG_Bonus}',
                    'class' => 'center'
                ],
                'deduction' => [
                    'text' => '{LNG_Deduction}',
                    'class' => 'center'
                ],
                'social_security' => [
                    'text' => '{LNG_Social Security}',
                    'class' => 'center'
                ],
                'tax' => [
                    'text' => '{LNG_Tax}',
                    'class' => 'center'
                ],
                'net_salary' => [
                    'text' => '{LNG_Net Salary}',
                    'class' => 'center'
                ],
                'create_date' => [
                    'text' => '{LNG_Date}',
                    'class' => 'center'
                ]
            ],
            /* รูปแบบการแสดงผลของส่วนหัว 0 (thead), 1 (tbody) */
            'cols' => [
                'basic_salary' => [
                    'class' => 'right'
                ],
                'allowance' => [
                    'class' => 'right'
                ],
                'overtime' => [
                    'class' => 'right'
                ],
                'bonus' => [
                    'class' => 'right'
                ],
                'deduction' => [
                    'class' => 'right'
                ],
                'social_security' => [
                    'class' => 'right'
                ],
                'tax' => [
                    'class' => 'right'
                ],
                'net_salary' => [
                    'class' => 'right'
                ],
                'create_date' => [
                    'class' => 'center'
                ]
            ],
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => [
                'view' => [
                    'class' => 'icon-print button brown notext',
                    'target' => '_blank',
                    'href' => 'export.php?module=salary-slip&id=:id',
                    'title' => '{LNG_Print}'
                ]
            ]
        ]);

        // save cookie
        setcookie('salaryIndex_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('salaryIndex_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);

        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array คืนค่า $item กลับไป
     */
    public function onRow($item, $o, $prop)
    {
        $item['month'] = Date::format($item['year'].'-'.$item['month'].'-01', 'M Y');
        $item['basic_salary'] = Number::format($item['basic_salary']);
        $item['allowance'] = Number::format($item['allowance']);
        $item['overtime'] = Number::format($item['overtime']);
        $item['bonus'] = Number::format($item['bonus']);
        $item['deduction'] = Number::format($item['deduction']);
        $item['social_security'] = Number::format($item['social_security']);
        $item['tax'] = Number::format($item['tax']);
        $item['net_salary'] = Number::format($item['net_salary']);
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');

        return $item;
    }
}
