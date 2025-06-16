<?php
/**
 * @filesource modules/salary/models/index.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Index;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * โมเดลสำหรับอ่านข้อมูลรายการประวัติเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query หน้าเพจ
     *
     * @param array $login
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($login)
    {
        return static::createQuery()
            ->select('S.id', 'S.year', 'S.month', 'S.basic_salary', 'S.allowance',
                'S.overtime', 'S.bonus', 'S.deduction', 'S.social_security',
                'S.tax', 'S.net_salary', 'S.create_date')
            ->from('salary S')
            ->where([
                ['S.member_id', $login['id']],
                ['S.status', 1] // เฉพาะเงินเดือนที่อนุมัติแล้ว
            ])
            ->order(['S.year DESC', 'S.month DESC']);
    }

    /**
     * รับค่าจาก action
     *
     * @param int $id
     *
     * @return object|null คืนค่าข้อมูล object ไม่พบคืนค่า null
     */
    public static function get($id)
    {
        $select = ['S.*', 'U.name', 'U.id_card', 'U.phone'];
        $category = \Index\Category\Model::init(false);
        foreach ($category->items() as $k => $label) {
            $q = static::createQuery()
                ->select(Sql::GROUP_CONCAT("D.value", $k, ',', true))
                ->from('user_meta D')
                ->where([['D.member_id', 'U.id'], ['D.name', $k]]);
            $select[] = [$q, $k];
        }

        return static::createQuery()
            ->from('salary S')
            ->join('user U', 'LEFT', [['U.id', 'S.member_id']])
            ->where(['S.id', $id])
            ->first($select);
    }

    /**
     * คืนค่าปีที่มีข้อมูลเงินเดือน
     *
     * @param int $member_id
     *
     * @return array
     */
    public static function getYears($member_id)
    {
        $year_offset = Language::get('YEAR_OFFSET');
        $query = static::createQuery()
            ->select(Sql::DISTINCT('year', 'year'))
            ->from('salary')
            ->where(['member_id', $member_id])
            ->order('year DESC')
            ->cacheOn()
            ->toArray();

        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item['year']] = $item['year'] + $year_offset;
        }
        return $result;
    }

    /**
     * คืนค่าเดือนที่มีข้อมูลเงินเดือนในปีที่เลือก
     *
     * @param int $member_id
     * @param string $year
     *
     * @return array
     */
    public static function getMonths($member_id, $year)
    {
        $query = static::createQuery()
            ->select(Sql::DISTINCT('month', 'month'))
            ->from('salary')
            ->where([
                ['member_id', $member_id],
                ['year', $year]
            ])
            ->order('month DESC')
            ->cacheOn()
            ->toArray();

        $months = Language::get('MONTH_LONG');

        $result = [];
        foreach ($query->execute() as $item) {
            $result[$item['month']] = $months[(int) $item['month']];
        }
        return $result;
    }

    /**
     * รับค่าจาก action (index.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            // รับค่าจากการ POST
            $action = $request->post('action')->toString();
            if (preg_match_all('/,?([a-zA-Z0-9]+),?/', $request->post('id', '')->toString(), $match)) {
                if ($action == 'view') {
                    // ดูรายละเอียดเงินเดือน
                    $ret['modal'] = Language::trans(\Salary\Slip\View::create()->render($request, (int) $match[1][0], $login));
                }

            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
