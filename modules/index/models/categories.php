<?php
/**
 * @filesource modules/index/models/categories.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Categories;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * คลาส Model สำหรับจัดการเกี่ยวกับหมวดหมู่
 * ใช้เป็นต้นแบบสำหรับหมวดหมู่อื่นๆ รองรับหมวดหมู่หลายภาษา
 * และให้ฟังก์ชันในการอ่านหมวดหมู่เข้าไปใน DataTable และบันทึกข้อมูลหมวดหมู่จากการป้อนข้อมูลของผู้ใช้
 */
class Model extends \Kotchasan\Model
{
    /**
     * @var string คีย์ภาษาสำหรับหมวดหมู่
     */
    protected $categories = 'CATEGORIES';

    /**
     * @var string ชื่อโมดูลสำหรับใช้ในบันทึกการทำงาน
     */
    protected $module = 'index';

    /**
     * @var string ชื่อของตารางหมวดหมู่
     */
    protected $table = 'category';

    /**
     * @var bool ระบุว่ารองรับหลายภาษาหรือไม่
     */
    protected $multiple_language = false;

    /**
     * อ่านหมวดหมู่สำหรับใช้ใน DataTable
     * หากไม่พบหมวดหมู่ จะคืนค่าหมวดหมูว่างๆ 1 รายการ
     *
     * @param string $type ประเภทของหมวดหมู่ที่ต้องการอ่าน
     *
     * @return array หมวดหมู่ที่จัดรูปแบบสำหรับใช้ใน DataTable
     */
    public static function toDataTable($type)
    {
        $obj = new static();

        $datas = ['category_id' => 1];
        foreach (Language::installedLanguage() as $lng) {
            $datas[$lng] = '';
        }

        // คิวรีตารางหมวดหมู่ตามประเภทที่ระบุ
        $query = static::createQuery()
            ->select('category_id', 'language', 'topic')
            ->from($obj->table)
            ->where(['type', $type])
            ->order('category_id');

        $result = [];
        foreach ($query->execute() as $item) {
            if ($obj->multiple_language) {
                if (isset($result[$item->category_id][$item->language])) {
                    $result[$item->category_id][$item->language] = $item->topic;
                } elseif (!isset($result[$item->category_id])) {
                    $datas['category_id'] = $item->category_id;
                    if (isset($datas[$item->language])) {
                        $datas[$item->language] = $item->topic;
                    }
                    $result[$item->category_id] = $datas;
                }
            } else {
                $result[$item->category_id] = [
                    'category_id' => $item->category_id,
                    'topic' => $item->topic
                ];
            }
        }

        if (empty($result)) {
            $result[1]['category_id'] = 1;
            if ($obj->multiple_language) {
                foreach (Language::installedLanguage() as $lng => $label) {
                    $result[1][$lng] = '';
                }
            } else {
                $result[1]['topic'] = '';
            }
        }

        return $result;
    }

    /**
     * บันทึกข้อมูลหมวดหมู่
     *
     * เมธอดนี้ประมวลผลข้อมูลหมวดหมู่ที่ส่งผ่าน HTTP request,
     * รวมถึงการตรวจสอบเซสชันและสิทธิ์ของผู้ใช้ และบันทึกข้อมูล
     *
     * @param Request $request คำขอ HTTP ที่มีข้อมูลหมวดหมู่
     *
     * @return void แสดงผล JSON response
     */
    public function submit(Request $request)
    {
        $ret = [];

        // ตรวจสอบเซสชัน, โทเค็น และสิทธิ์ของผู้ใช้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_config')) {
                try {
                    // รวบรวมประเภทของหมวดหมู่ที่จะบันทึก
                    $type = $request->post('type')->topic();
                    $save = [];
                    $category_exists = [];

                    // รวบรวม ID หมวดหมู่จากคำขอ
                    foreach ($request->post('category_id', [])->topic() as $key => $value) {
                        if (isset($category_exists[$value])) {
                            $ret['ret_category_id_'.$key] = Language::replace('This :name already exists', [':name' => 'ID']);
                        } elseif ($value != '') {
                            $category_exists[$value] = $value;
                            $save[$key]['category_id'] = $value;
                        }
                    }

                    // รวบรวมหัวข้อหมวดหมู่จากคำขอ
                    if ($request->post('topic')->exists()) {
                        foreach ($request->post('topic')->topic() as $key => $value) {
                            if (isset($save[$key]) && $value != '') {
                                $save[$key]['topic'][''] = $value;
                            }
                        }
                    }

                    // รวบรวมหัวข้อหมวดหมู่สำหรับแต่ละภาษาที่ติดตั้ง
                    foreach (Language::installedLanguage() as $lng => $label) {
                        if ($request->post($lng)->exists()) {
                            foreach ($request->post($lng, [])->topic() as $key => $value) {
                                if (isset($save[$key]) && $value != '') {
                                    $save[$key]['topic'][$lng] = $value;
                                }
                            }
                        }
                    }

                    // หากไม่มีข้อผิดพลาด ดำเนินการบันทึกข้อมูล
                    if (empty($ret)) {
                        // กำหนดชื่อตารางหมวดหมู่
                        $table_name = $this->getTableName($this->table);

                        // รับอินสแตนซ์ของฐานข้อมูล
                        $db = $this->db();

                        // ลบหมวดหมู่ที่มีอยู่ของประเภทที่ระบุ
                        $db->delete($table_name, ['type', $type], 0);

                        // แทรกข้อมูลหมวดหมู่ใหม่
                        foreach ($save as $item) {
                            foreach ($item['topic'] as $lng => $topic) {
                                $db->insert($table_name, [
                                    'category_id' => $item['category_id'],
                                    'type' => $type,
                                    'language' => $lng,
                                    'topic' => $topic
                                ]);
                            }
                        }

                        // บันทึกการดำเนินการบันทึกหมวดหมู่
                        \Index\Log\Model::add(0, $this->module, 'Save', '{LNG_Save} '.Language::get($this->categories, ucfirst($type), $type), $login['id']);

                        // ตั้งค่าการตอบกลับสำเร็จ
                        $ret['alert'] = Language::get('Saved successfully');
                        $ret['location'] = 'reload';

                        // ลบโทเค็นคำขอ
                        $request->removeToken();
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    // จัดการข้อยกเว้นของ InputItem และตั้งค่าข้อความแจ้งเตือน
                    $ret['alert'] = $e->getMessage();
                }
            }

            // หากมีข้อผิดพลาด ตั้งค่าข้อความแจ้งเตือนเริ่มต้น
            if (empty($ret)) {
                $ret['alert'] = Language::get('Unable to complete the transaction');
            }

            // ส่งการตอบกลับเป็น JSON
            echo json_encode($ret);
        }
    }
}
