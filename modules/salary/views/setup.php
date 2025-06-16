<?php
/**
 * @filesource modules/salary/views/setup.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Setup;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=salary-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงรายการเงินเดือนรายเดือน สำหรับผู้ดูแล
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        // เตรียมข้อมูลสำหรับใส่ลงในตาราง
        $headers = [
            'name' => [
                'text' => '{LNG_Name}',
                'sort' => 'name'
            ],
            'month' => [
                'text' => '{LNG_Month} {LNG_Year}'
            ],
            'basic_salary' => [
                'text' => '{LNG_Basic Salary}',
                'class' => 'number',
                'sort' => 'basic_salary'
            ],
            'deduction' => [
                'text' => '{LNG_Deduction}',
                'class' => 'number',
                'sort' => 'deduction'
            ],
            'social_security' => [
                'text' => '{LNG_Social Security}',
                'class' => 'number',
                'sort' => 'social_security'
            ],
            'tax' => [
                'text' => '{LNG_Income Tax}',
                'class' => 'number',
                'sort' => 'tax'
            ],
            'net_salary' => [
                'text' => '{LNG_Net Salary}',
                'class' => 'number',
                'sort' => 'net_salary'
            ],
            'status' => [
                'text' => '{LNG_Status}',
                'class' => 'center',
                'sort' => 'status'
            ]
        ];
        $cols = [
            'basic_salary' => [
                'class' => 'number'
            ],
            'deduction' => [
                'class' => 'number'
            ],
            'social_security' => [
                'class' => 'number'
            ],
            'tax' => [
                'class' => 'number'
            ],
            'net_salary' => [
                'class' => 'number'
            ],
            'status' => [
                'class' => 'center'
            ]
        ];

        // ตัวกรอง
        $filters = [];

        // กรองปี
        $year = $request->request('year', date('Y'))->toInt();
        $filters['year'] = [
            'name' => 'year',
            'text' => '{LNG_Year}',
            'options' => [],
            'default' => date('Y'),
            'value' => $year
        ];
        // สร้างตัวเลือกปี (ย้อนหลัง 5 ปี)
        for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
            $filters['year']['options'][$i] = $i;
        }

        // กรองเดือน
        $month = $request->request('month', date('m'))->toInt();
        $filters['month'] = [
            'name' => 'month',
            'text' => '{LNG_Month}',
            'options' => [0 => '{LNG_all items}'] + Language::get('MONTH_LONG'),
            'default' => 0,
            'value' => $month
        ];

        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable([
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Salary\Setup\Model::toDataTable(),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('salary_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('salary_sort', 'year DESC, month DESC, name ASC')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => [$this, 'onRow'],
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/salary/model/setup/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => [
                [
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => [
                        'approve' => '{LNG_Approve}',
                        'recalculate' => '{LNG_Re-Calculate}',
                        'send_email' => '{LNG_Send Email}',
                        'delete' => '{LNG_Delete}'
                    ]
                ],
                [
                    'class' => 'button orange icon-excel border',
                    'id' => 'export&type=list&year='.$year.'&month='.$month,
                    'text' => '{LNG_Download} {LNG_Salary}'
                ]
            ],
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => ['name'],
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* คอลัมน์ที่ไม่ต้องการแสดงผล */
            'hideColumns' => ['id', 'year'],
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => $headers,
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => $cols,
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => [
                'view' => [
                    'class' => 'icon-info button brown notext',
                    'id' => ':id',
                    'title' => '{LNG_Details of} {LNG_Salary}'
                ],
                'edit' => [
                    'class' => 'icon-edit button green notext',
                    'href' => $uri->createBackUri(['module' => 'salary-write', 'id' => ':id']),
                    'title' => '{LNG_Edit}'
                ]
            ],
            /* ปุ่มเพิม */
            'addNew' => [
                'class' => 'float_button icon-new',
                'href' => $uri->createBackUri(['module' => 'salary-write', 'id' => 0]),
                'title' => '{LNG_Add new salary record}'
            ]
        ]);
        // save cookie
        setcookie('salary_perPage', $table->perPage, time() + 3600 * 24 * 365, '/');
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array $item
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['month'] = Date::format($item['year'].'-'.$item['month'].'-01', 'M Y');
        $item['basic_salary'] = number_format($item['basic_salary'], 2);
        $item['deduction'] = number_format($item['deduction'], 2);
        $item['social_security'] = number_format($item['social_security'], 2);
        $item['tax'] = number_format($item['tax'], 2);
        $item['net_salary'] = '<strong>'.number_format($item['net_salary'], 2).'</strong>';
        $status = Language::get('SALARY_STATUS', '', $item['status']);
        $item['status'] = '<span class="icon-valid '.($item['status'] === 1 ? 'access' : 'disabled').'" title="'.$status.'"></span>';

        return $item;
    }
}
