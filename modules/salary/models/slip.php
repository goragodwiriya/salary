<?php
/**
 * @filesource modules/salary/models/slip.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Slip;

use Gcms\Api as ApiController;
use Kotchasan\Language;
use Kotchasan\Number;
use Salary\Base\Model as Base;

/**
 * ข้อมูลสลิปเงินเดือน ใช้ร่วมกันระหว่างหน้าต่างแสดงผลและหน้าสั่งพิมพ์
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านสลิปเงินเดือน พร้อมตรวจสอบสิทธิ์การเข้าถึง
     *
     * ผู้ดูแลเงินเดือนดูได้ทุกคน สมาชิกทั่วไปดูได้เฉพาะของตัวเอง
     * และต้องเป็นรายการที่อนุมัติแล้วเท่านั้น (ตรงกับรายการที่แสดงในหน้าประวัติ)
     *
     * @param string $id
     * @param object $login
     *
     * @return object|null
     */
    public static function get($id, $login)
    {
        $id = preg_replace('/[^0-9]/', '', (string) $id);
        if ($id === '' || !$login) {
            return null;
        }

        $index = static::createQuery()
            ->select('S.*', 'U.name', 'U.id_card', 'U.phone')
            ->from('salary S')
            ->join('user U', [['U.id', 'S.member_id']], 'LEFT')
            ->where([['S.id', $id]])
            ->first();

        if (!$index) {
            return null;
        }

        $canManage = ApiController::hasPermission($login, ['can_manage_salary', 'can_approve_salary']);
        if (!$canManage) {
            if ((int) $index->member_id !== (int) $login->id) {
                return null;
            }
            // พนักงานเห็นเฉพาะรายการที่อนุมัติแล้ว
            if ((int) $index->status !== 1) {
                return null;
            }
        }

        $meta = \Salary\Category\Model::metaOf($index->member_id);
        $index->department = $meta[$index->member_id]['department'] ?? '';
        $index->position = $meta[$index->member_id]['position'] ?? '';

        return $index;
    }

    /**
     * แปลงข้อมูลเงินเดือนเป็นรูปแบบที่หน้าสลิปใช้แสดงผล
     * รายการที่เป็น 0 จะไม่ถูกแสดง เหมือนระบบเดิม
     *
     * @param object $index
     *
     * @return array
     */
    public static function toSlip($index)
    {
        $incomes = [];
        foreach (['basic_salary' => 'Basic Salary', 'allowance' => 'Allowance', 'overtime' => 'Overtime', 'bonus' => 'Bonus'] as $key => $label) {
            if ((float) $index->$key > 0) {
                $incomes[] = [
                    'label' => $key === 'overtime' ? Base::overtimeLabel($index->overtime_hours) : Language::get($label),
                    'amount' => Number::format($index->$key)
                ];
            }
        }

        $deductions = [];
        foreach (['deduction' => 'Deduction', 'social_security' => 'Social Security', 'tax' => 'Income Tax'] as $key => $label) {
            if ((float) $index->$key > 0) {
                $deductions[] = [
                    'label' => Language::get($label),
                    'amount' => Number::format($index->$key)
                ];
            }
        }

        $total_income = (float) $index->basic_salary + (float) $index->allowance + (float) $index->overtime + (float) $index->bonus;
        $total_deduction = (float) $index->deduction + (float) $index->social_security + (float) $index->tax;

        return [
            'id' => $index->id,
            'name' => $index->name,
            'id_card' => $index->id_card,
            'department' => $index->department,
            'position' => $index->position,
            'web_title' => self::$cfg->web_title,
            'period_text' => Base::periodText($index->year, $index->month),
            'status_text' => Base::statusText($index->status),
            'incomes' => $incomes,
            'deductions' => $deductions,
            'total_income' => Number::format($total_income),
            'total_deduction' => Number::format($total_deduction),
            'net_salary' => Number::format($index->net_salary),
            'remark' => (string) $index->remark,
            'print_url' => WEB_URL.'export.php?module=salary&typ=slip&id='.rawurlencode($index->id)
        ];
    }

    /**
     * ข้อมูลสำหรับเปิดสลิปใน modal
     *
     * @param string $id
     * @param object $login
     *
     * @return array|null
     */
    public static function modalPayload($id, $login)
    {
        $index = self::get($id, $login);
        if ($index === null) {
            return null;
        }

        return [
            'data' => self::toSlip($index),
            'actions' => [
                [
                    'type' => 'modal',
                    'action' => 'open',
                    'template' => 'salary/slip.html',
                    'title' => Language::get('Salary slip').' : '.$index->name
                ]
            ]
        ];
    }
}
