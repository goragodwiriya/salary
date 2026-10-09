<?php
/**
 * @filesource modules/salary/controllers/slips.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Slips;

use Kotchasan\Http\Request;
use Salary\Base\Model as Base;

/**
 * API ตารางประวัติเงินเดือนของสมาชิกที่ล็อกอิน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * คอลัมน์ที่เรียงลำดับได้ (ป้องกัน SQL injection)
     *
     * @var array
     */
    protected $allowedSortColumns = ['year', 'month', 'basic_salary', 'net_salary', 'create_date'];

    /**
     * สมาชิกทุกคนดูข้อมูลของตัวเองได้ ไม่ต้องมีสิทธิ์ของโมดูล
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        return true;
    }

    /**
     * บังคับให้ทุก query ผูกกับสมาชิกที่ล็อกอินเสมอ
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'member_id' => (int) $login->id,
            'year' => $request->get('year')->number()
        ];
    }

    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * ตัวเลือกของ filter
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return [
            'year' => Model::years($params['member_id'])
        ];
    }

    /**
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->period_text = Base::periodText($item->year, $item->month, true);
            $item->print_url = WEB_URL.'export.php?module=salary&typ=slip&id='.rawurlencode($item->id);
        }

        return $datas;
    }

    /**
     * เปิดสลิปเงินเดือนใน modal
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleViewAction(Request $request, $login)
    {
        $payload = \Salary\Slip\Model::modalPayload($request->post('id')->toString(), $login);
        if ($payload === null) {
            return $this->errorResponse('No data available', 404);
        }

        return $this->successResponse($payload, 'Salary slip');
    }
}
