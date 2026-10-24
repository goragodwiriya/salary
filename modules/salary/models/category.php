<?php
/**
 * @filesource modules/salary/models/category.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Category;

/**
 * หมวดหมู่ที่โมดูลเงินเดือนใช้งาน
 *
 * `\Gcms\Category` ของระบบกลางรู้จักเฉพาะ department แต่ข้อมูลพนักงานของโมดูลนี้
 * ใช้ position ด้วย (มาจากการนำเข้าข้อมูลพนักงาน) จึงประกาศเพิ่มไว้ที่โมดูล
 * โดยไม่ต้องแก้ไขคลาสของระบบกลาง ทั้งสองชนิดเก็บอยู่ในตาราง category เดียวกัน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Gcms\Category
{
    /**
     * ชนิดของหมวดหมู่ที่โมดูลนี้อ่าน/เขียน
     *
     * @var array
     */
    protected $categories = [
        'department' => '{LNG_Department}',
        'position' => '{LNG_Position}'
    ];

    /**
     * อ่านค่า meta ของสมาชิกตามชนิดหมวดหมู่ที่โมดูลรู้จัก
     * คืนค่าเป็น [member_id => ['department' => 'บัญชี', 'position' => 'พนักงาน']]
     *
     * @param array|int $member_ids
     *
     * @return array
     */
    public static function metaOf($member_ids)
    {
        $member_ids = array_values(array_filter(array_map('intval', (array) $member_ids)));
        if (empty($member_ids)) {
            return [];
        }

        $category = self::init();
        $types = $category->typies();

        $query = \Kotchasan\Model::createQuery()
            ->select('member_id', 'name', 'value')
            ->from('user_meta')
            ->where([
                ['member_id', $member_ids],
                ['name', $types]
            ]);

        $result = [];
        foreach ($query->fetchAll() as $item) {
            $topic = $category->get($item->name, $item->value);
            if ($topic === '') {
                continue;
            }
            // สมาชิก 1 คนอาจมีหลายค่าในหมวดหมู่เดียวกัน แสดงต่อกันด้วยจุลภาค
            if (isset($result[$item->member_id][$item->name])) {
                $result[$item->member_id][$item->name] .= ', '.$topic;
            } else {
                $result[$item->member_id][$item->name] = $topic;
            }
        }

        return $result;
    }
}
