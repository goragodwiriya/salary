<?php
/**
 * @filesource modules/salary/controllers/export.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Export;

use Kotchasan\Http\Request;

/**
 * module=salary-export
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\Controller
{
    /**
     * ส่งออกไฟล์ตัวอย่าง salary.csv หรือรายการเงินเดือน
     *
     * @param Request $request
     */
    public function export(Request $request)
    {
        // ตรวจสอบว่าเป็นการส่งออกรายการเงินเดือนหรือไฟล์ตัวอย่าง
        $export_type = $request->get('type')->toString();
        if ($export_type === 'list') {
            // ส่งออกรายการเงินเดือน
            $params = [
                'year' => $request->get('year')->toInt(),
                'month' => $request->get('month')->toInt()
            ];
            return \Salary\Setup\Model::export($params);
        } elseif ($export_type === 'salary') {
            // ส่งออกไฟล์ตัวอย่าง
            $header = \Salary\Import\Model::headers();
            $data = [
                ['นายสมชาย โนนกระโทก', '1234567890123', '25000.00', '3000.00', '2500.00', '5000.00', '500.00', 'โบนัสผลงาน Q1'],
                ['นางสมศรี รักงานดี', '9876543210987', '20000.00', '2500.00', '1800.00', '3000.00', '300.00', 'ค่าล่วงเวลาพิเศษ'],
                ['นายสมปอง ทำงานดี', '3456789012345', '30000.00', '3500.00', '3000.00', '7000.00', '400.00', 'โบนัสการขาย']
            ];
            // ดาวน์โหลดไฟล์ salary.csv
            return \Kotchasan\Csv::send('salary', $header, $data, self::$cfg->csv_language);
        } elseif ($export_type === 'empoloyee') {
            // ส่งออกไฟล์ตัวอย่าง
            $header = \Salary\Importusers\Model::headers();
            $data = [
                ['นายสมชาย โนนกระโทก', '1234567890123', 'somchai.jaidee001', 'm', 'บริหาร', 'พนักงาน'],
                ['นางสมศรี รักงานดี', '9876543210987', 'malee.rakgaan002', 'f', 'บุคคล', 'หัวหน้าแผนก'],
                ['นายสมปอง ทำงานดี', '3456789012345', 'sompong.tamngan003', 'm', 'การเงิน', 'ผู้จัดการ']
            ];

            // ดาวน์โหลดไฟล์ empoloyee.csv
            return \Kotchasan\Csv::send('empoloyee', $header, $data, self::$cfg->csv_language);
        }
    }
}
