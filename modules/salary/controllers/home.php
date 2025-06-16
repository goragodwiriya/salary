<?php
/**
 * @filesource modules/salary/controllers/home.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Home;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * Controller สำหรับการแสดงผลหน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card สำหรับระบบเงินเดือน
     *
     * @param Request               $request
     * @param \Kotchasan\Collection $card
     * @param array                 $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        if ($login) {
            // ข้อมูลเงินเดือนสำหรับ card
            $salaryData = \Salary\Home\Model::getCardData($login);

            // การแสดงผลแบ่งตามสิทธิ์
            if (Login::checkPermission($login, 'can_manage_salary')) {
                // สำหรับแอดมิน - แสดงข้อมูลภาพรวม
                \Index\Home\Controller::renderCard(
                    $card,
                    'icon-group',
                    '{LNG_Total Employees}',
                    number_format(floor($salaryData['total_employees'])),
                    '{LNG_Active employees}',
                    'index.php?module=salary-setup'
                );

                \Index\Home\Controller::renderCard(
                    $card,
                    'icon-money',
                    '{LNG_Total Salary This Month}',
                    number_format(floor($salaryData['total_salary_this_month']), 0),
                    '{LNG_All departments}',
                    'index.php?module=salary-setup'
                );

                if ($salaryData['average_salary'] > 0) {
                    \Index\Home\Controller::renderCard(
                        $card,
                        'icon-chart',
                        '{LNG_Average Salary}',
                        number_format(floor($salaryData['average_salary']), 0),
                        '{LNG_This month}',
                        'index.php?module=salary-report'
                    );
                }

                if ($salaryData['pending_records'] > 0) {
                    \Index\Home\Controller::renderCard(
                        $card,
                        'icon-warning',
                        '{LNG_Pending Records}',
                        $salaryData['pending_records'],
                        '{LNG_Need review}',
                        'index.php?module=salary-setup'
                    );
                }

            } else {
                // สำหรับสมาชิกทั่วไป - แสดงข้อมูลส่วนตัว
                if ($salaryData['my_salary_this_month'] > 0) {
                    \Index\Home\Controller::renderCard(
                        $card,
                        'icon-money',
                        '{LNG_My Slip}',
                        number_format(floor($salaryData['my_salary_this_month']), 0),
                        '{LNG_Current month}',
                        WEB_URL.'export.php?module=salary-slip&id='.$salaryData['my_salary_id'],
                        '_blank'
                    );
                }

                \Index\Home\Controller::renderCard(
                    $card,
                    'icon-report',
                    '{LNG_Salary Records}',
                    number_format($salaryData['my_total_records'], 0),
                    '{LNG_View history}',
                    'index.php?module=salary',
                    '',
                    '#007bff'
                );

                if ($salaryData['last_salary'] > 0) {
                    \Index\Home\Controller::renderCard(
                        $card,
                        'icon-clock',
                        '{LNG_Last Salary}',
                        number_format($salaryData['last_salary'], 0),
                        $salaryData['last_salary_date'],
                        WEB_URL.'export.php?module=salary-slip&id='.$salaryData['last_salary_id'],
                        '_blank'
                    );
                }
            }
        }
    }

    /**
     * ฟังก์ชั่นสร้าง block
     *
     * @param Request $request
     * @param Collection $block
     * @param array $login
     */
    public static function addBlock(Request $request, $block, $login)
    {
        if ($login) {
            // เพิ่มบล็อกกราฟแนวโน้มเงินเดือน
            $content = \Salary\Home\View::render($request, $login);
            if (!empty($content)) {
                if (Login::checkPermission($login, 'can_manage_salary')) {
                    $block->set('{LNG_Salary Trend by Department}', $content);
                } else {
                    $block->set('{LNG_My Salary Trend}', $content);
                }
            }
        }
    }
}
