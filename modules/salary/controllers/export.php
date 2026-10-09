<?php
/**
 * @filesource modules/salary/controllers/export.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Export;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * หน้าสั่งพิมพ์และไฟล์ตัวอย่างของโมดูลเงินเดือน
 * เรียกผ่าน export.php?module=salary&typ=<slip|sample>
 *
 * ระบบเดิม (modules/salary/controllers/export.php) ไม่ตรวจสอบสิทธิ์เลย
 * ใครก็ดาวน์โหลดรายการเงินเดือนของพนักงานทุกคนได้ถ้ารู้ URL
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * export.php?module=salary&typ=slip&id=xxx
     * หน้าสลิปเงินเดือนสำหรับสั่งพิมพ์
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function slip(Request $request)
    {
        try {
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Model::get() ตรวจความเป็นเจ้าของและสถานะการอนุมัติให้แล้ว
            $index = \Salary\Slip\Model::get($request->get('id')->filter('0-9'), $login);
            if ($index === null) {
                return $this->errorResponse('Not Found', 404);
            }

            $slip = \Salary\Slip\Model::toSlip($index);

            return \Export\Export\Controller::printHtml([
                '/%TITLE%/' => Language::get('Salary slip').' '.$slip['period_text'],
                '/%CONTENT%/' => self::slipHtml($slip)
            ], [
                'paper' => 'A4',
                'orientation' => 'portrait',
                'extra_head' => '<link rel="stylesheet" href="'.WEB_URL.'modules/salary/views/print.css">'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * export.php?module=salary&typ=sample&kind=<salary|employee>
     * ไฟล์ CSV ตัวอย่างสำหรับใช้เป็นแบบในการนำเข้าข้อมูล
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response|void
     */
    public function sample(Request $request)
    {
        try {
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            $kind = $request->get('kind')->filter('a-z');
            // ไฟล์ตัวอย่างพนักงานใช้สิทธิ์เดียวกับหน้านำเข้าพนักงาน (แอดมินเท่านั้น)
            $allowed = $kind === 'employee'
                ? \Index\Users\Model::canManage($login)
                : ApiController::hasPermission($login, 'can_manage_salary');
            if (!$allowed) {
                return $this->errorResponse('Permission required', 403);
            }

            if ($kind === 'employee') {
                $headers = \Salary\Importusers\Model::headers();
                $rows = [
                    ['นายสมชาย โนนกระโทก', '1234567890123', 'somchai.jaidee001', 'm', 'บริหาร', 'พนักงาน'],
                    ['นางสมศรี รักงานดี', '9876543210987', 'malee.rakgaan002', 'f', 'บุคคล', 'หัวหน้าแผนก'],
                    ['นายสมปอง ทำงานดี', '3456789012345', 'sompong.tamngan003', 'm', 'การเงิน', 'ผู้จัดการ']
                ];
                $filename = 'employee';
            } elseif ($kind === 'salary') {
                // คอลัมน์ขึ้นกับการตั้งค่า (ประกันสังคมและภาษีมีเฉพาะเมื่อปิดการคำนวณอัตโนมัติ)
                // จึงเขียนตัวอย่างตามชื่อช่อง แล้วเรียงตามหัวข้อที่ใช้จริง
                $samples = [
                    ['name' => 'นายสมชาย โนนกระโทก', 'id_card' => '1234567890123', 'basic_salary' => '25000.00', 'allowance' => '3000.00',
                        'overtime' => '2500.00', 'bonus' => '5000.00', 'deduction' => '500.00',
                        'social_security' => '750.00', 'tax' => '420.83', 'remark' => 'โบนัสผลงาน Q1'],
                    ['name' => 'นางสมศรี รักงานดี', 'id_card' => '9876543210987', 'basic_salary' => '20000.00', 'allowance' => '2500.00',
                        'overtime_hours' => '12', 'bonus' => '3000.00', 'deduction' => '300.00',
                        'social_security' => '750.00', 'tax' => '0.00', 'remark' => 'ค่าล่วงเวลาคิดจาก 12 ชั่วโมง'],
                    ['name' => 'นายสมปอง ทำงานดี', 'id_card' => '3456789012345', 'basic_salary' => '30000.00', 'allowance' => '3500.00',
                        'overtime' => '3000.00', 'bonus' => '7000.00', 'deduction' => '400.00',
                        'social_security' => '750.00', 'tax' => '825.83', 'remark' => 'โบนัสการขาย']
                ];
                $headers = \Salary\Import\Model::headers();
                $rows = [];
                foreach ($samples as $sample) {
                    $row = [];
                    foreach (array_keys($headers) as $key) {
                        $row[] = $sample[$key] ?? '';
                    }
                    $rows[] = $row;
                }
                $headers = array_values($headers);
                $filename = 'salary';
            } else {
                return $this->errorResponse('Not Found', 404);
            }

            \Kotchasan\Csv::send($filename, $headers, $rows, self::$cfg->salary_csv_language);
            exit;
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * สร้าง HTML ของสลิปเงินเดือน
     *
     * @param array $slip
     *
     * @return string
     */
    protected static function slipHtml(array $slip)
    {
        $html = '<div class="salary-slip">';
        $html .= '<div class="slip-header">';
        $html .= '<h1>'.self::e(Language::get('Salary slip')).'</h1>';
        $html .= '<h2>'.self::e($slip['web_title']).'</h2>';
        $html .= '</div>';

        $html .= '<div class="employee-info">';
        $html .= '<div><strong>'.self::e(Language::get('Name')).' :</strong> '.self::e($slip['name']).'</div>';
        foreach (['id_card' => 'Identification No.', 'position' => 'Position', 'department' => 'Department'] as $key => $label) {
            if ($slip[$key] !== '') {
                $html .= '<div><strong>'.self::e(Language::get($label)).' :</strong> '.self::e($slip[$key]).'</div>';
            }
        }
        $html .= '</div>';

        $html .= '<div class="salary-info">';
        $html .= '<h3>'.self::e(Language::replace('Monthly salary :period', [':period' => $slip['period_text']])).'</h3>';
        $html .= self::slipTable(Language::get('Income'), $slip['incomes'], Language::get('Total income'), $slip['total_income']);
        if (!empty($slip['deductions'])) {
            $html .= self::slipTable(Language::get('Deduction'), $slip['deductions'], Language::get('Total deductions'), $slip['total_deduction']);
        }
        $html .= '<table class="salary-table net-salary"><tbody><tr>';
        $html .= '<td><strong>'.self::e(Language::get('Net Salary')).'</strong></td>';
        $html .= '<td class="amount net"><strong>'.self::e($slip['net_salary']).' '.self::e(Language::get('THB')).'</strong></td>';
        $html .= '</tr></tbody></table>';
        $html .= '</div>';

        if ($slip['remark'] !== '') {
            $html .= '<div class="remark"><strong>'.self::e(Language::get('Remark')).' :</strong> '.self::e($slip['remark']).'</div>';
        }

        return $html.'</div>';
    }

    /**
     * ตารางรายได้/รายหักของสลิป
     *
     * @param string $title
     * @param array $rows
     * @param string $totalLabel
     * @param string $totalAmount
     *
     * @return string
     */
    protected static function slipTable($title, array $rows, $totalLabel, $totalAmount)
    {
        $html = '<table class="salary-table"><thead><tr><th colspan="2">'.self::e($title).'</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>'.self::e($row['label']).'</td><td class="amount">'.self::e($row['amount']).'</td></tr>';
        }
        $html .= '<tr><td><strong>'.self::e($totalLabel).'</strong></td>';
        $html .= '<td class="amount total"><strong>'.self::e($totalAmount).'</strong></td></tr>';

        return $html.'</tbody></table>';
    }

    /**
     * แปลงข้อความให้ปลอดภัยก่อนใส่ลงใน HTML
     *
     * @param string $text
     *
     * @return string
     */
    protected static function e($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
