<?php
/**
 * @filesource Gcms/Category.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

/**
 * คลาสสำหรับจัดการข้อมูลหมวดหมู่
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Category
{
    /**
     * ชื่อตารางหมวดหมู่
     *
     * @var string
     */
    protected $table = 'category';

    /**
     * อาร์เรย์สำหรับเก็บข้อมูลหมวดหมู่
     *
     * @var array
     */
    private $datas = [];

    /**
     * อาร์เรย์สำหรับเก็บข้อมูลหมวดหมู่
     *
     * @var array
     */
    protected $categories = [];

    /**
     * คืนค่ารายการประเภทของหมวดหมู่ที่มีอยู่
     *
     * @return array
     */
    public function typies()
    {
        return empty($this->categories) ? [] : array_keys($this->categories);
    }

    /**
     * คืนค่าหมวดหมู่ทั้งหมด
     *
     * @return array
     */
    public function items()
    {
        return $this->categories;
    }

    /**
     * คืนชื่อของหมวดหมู่ตามประเภท
     * หากไม่พบจะคืนค่าว่าง
     *
     * @param string $type
     *
     * @return string
     */
    public function name($type)
    {
        return isset($this->categories[$type]) ? $this->categories[$type] : '';
    }

    /**
     * โหลดข้อมูลหมวดหมู่จากฐานข้อมูล
     *
     * @param bool $sortById เรียงลำดับตาม category_id ถ้า true, ตามหัวเรื่องถ้า false
     * @param bool $all รวมหมวดหมู่ทั้งหมดถ้า true, เฉพาะที่เผยแพร่ถ้า false
     * @param bool $cache ใช้แคชถ้า true
     * @param bool $multiple_language รวมหลายภาษา
     *
     * @return static
     */
    public static function init($sortById = true, $all = true, $cache = true, $multiple_language = false)
    {
        // สร้างออบเจ็กต์ใหม่
        $obj = new static();
        $types = $obj->typies();

        if (!empty($types)) {
            $where = [['type', $types]];

            if ($multiple_language) {
                $where[] = ['language', \Kotchasan\Language::name()];
            }

            if (!$all) {
                $where[] = ['published', 1];
            }

            // สร้างและเรียกใช้คำสั่ง query
            $query = \Kotchasan\Model::createQuery()
                ->select('category_id', 'topic', 'type')
                ->from($obj->table)
                ->where($where)
                ->order($sortById ? 'category_id' : 'topic');

            if ($cache) {
                $query->cacheOn();
            }

            foreach ($query->execute() as $item) {
                $obj->datas[$item->type][$item->category_id] = $item->topic;
            }
        }

        return $obj;
    }

    /**
     * คืนค่าหมวดหมู่สำหรับใช้ใน select dropdown
     *
     * @param string $type ประเภทของหมวดหมู่
     * @param bool $by_id คืนค่าเป็น id ถ้า true, เป็นหัวเรื่องถ้า false
     * @param array|null $include กรองเฉพาะรายการที่ต้องการ
     *
     * @return array
     */
    public function toSelect($type, $by_id = true, $include = null)
    {
        if (empty($this->datas[$type])) {
            return [];
        }

        if (is_array($include)) {
            $filteredData = [];
            foreach ($this->datas[$type] as $key => $value) {
                $check = $by_id ? $key : $value;
                if (in_array($check, $include)) {
                    $filteredData[$check] = $value;
                }
            }
            return $filteredData;
        }

        // คืนค่าเป็นหัวเรื่องถ้า $by_id เป็น false
        if (!$by_id) {
            return array_combine($this->datas[$type], $this->datas[$type]);
        }

        return $this->datas[$type];
    }

    /**
     * ตรวจสอบว่าหมวดหมู่ไม่มีรายการหรือไม่
     *
     * @param string $type
     *
     * @return bool
     */
    public function isEmpty($type)
    {
        return empty($this->datas[$type]);
    }

    /**
     * คืนค่าหัวเรื่องของหมวดหมู่โดย category_id
     * คืนค่า default ถ้าไม่พบ
     *
     * @param string $type
     * @param string|int $category_id
     * @param string $default ค่าเริ่มต้นถ้าไม่พบ
     *
     * @return string
     */
    public function get($type, $category_id, $default = '')
    {
        return empty($this->datas[$type][$category_id]) ? $default : $this->datas[$type][$category_id];
    }

    /**
     * คืนค่า key แรกของหมวดหมู่
     * คืนค่า null ถ้าไม่มีรายการ
     *
     * @param string $type
     *
     * @return int|null
     */
    public function getFirstKey($type)
    {
        if (isset($this->datas[$type])) {
            reset($this->datas[$type]);
            return key($this->datas[$type]);
        }
        return null;
    }

    /**
     * ตรวจสอบว่าหมวดหมู่ที่กำหนดมีอยู่หรือไม่
     *
     * @param string $type
     * @param string|int $category_id
     *
     * @return bool
     */
    public function exists($type, $category_id)
    {
        return isset($this->datas[$type][$category_id]);
    }

    /**
     * บันทึกหมวดหมู่หรือเรียกคืน ID ถ้ามีอยู่แล้ว
     *
     * @param string $type
     * @param string $topic
     * @param string $language
     *
     * @return int หมวดหมู่ ID
     */
    public static function save($type, $topic, $language = '')
    {
        $topic = trim($topic);
        if ($topic === '') {
            return 0;
        }

        $obj = new static();
        $model = new \Kotchasan\Model();
        $db = $model->db();
        $table = $model->getTableName($obj->table);

        // ตรวจสอบว่าหมวดหมู่มีอยู่แล้วหรือไม่
        $existingCategory = $db->first($table, [
            ['type', $type],
            ['language', $language],
            ['topic', $topic]
        ]);

        if ($existingCategory) {
            return $existingCategory->category_id;
        }

        // รับ ID หมวดหมู่ใหม่
        $maxCategoryId = $model->createQuery()
            ->from($obj->table)
            ->where(['type', $type])
            ->first('SQL(MAX(CAST(`category_id` AS INT)) AS `category_id`)');

        $category_id = empty($maxCategoryId->category_id) ? 1 : (1 + (int) $maxCategoryId->category_id);

        // บันทึกหมวดหมู่ใหม่
        $db->insert($table, [
            'type' => $type,
            'category_id' => $category_id,
            'language' => $language,
            'topic' => $topic
        ]);

        return $category_id;
    }
}
