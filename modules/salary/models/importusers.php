<?php
/**
 * @filesource modules/salary/models/importusers.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Importusers;

use Kotchasan\Language;
use Kotchasan\Text;
use Salary\Base\Model as Base;

/**
 * นำเข้าข้อมูลพนักงานจากไฟล์ CSV
 *
 * จับคู่พนักงานเดิมด้วยเลขประจำตัวประชาชนหรืออีเมล ถ้าไม่พบจะสร้างบัญชีใหม่
 * พร้อมรหัสผ่านเริ่มต้น และบันทึกแผนก/ตำแหน่งลงในหมวดหมู่ให้อัตโนมัติ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รหัสผ่านเริ่มต้นของพนักงานที่ถูกสร้างจากการนำเข้าข้อมูล
     */
    const DEFAULT_PASSWORD = '123456';

    /**
     * จำนวนแถวที่นำเข้าสำเร็จ
     *
     * @var int
     */
    private $row = 0;

    /**
     * ชื่อคอลัมน์จริงในไฟล์ที่อ่านได้ เรียงตามลำดับของ headers()
     *
     * @var array
     */
    private $header = [];

    /**
     * รหัสผ่านเริ่มต้นที่ hash แล้ว ใช้ร่วมกันทุกบัญชีที่สร้างใหม่ในการนำเข้าครั้งนี้
     *
     * bcrypt (cost 12) ใช้เวลาราว 0.3 วินาทีต่อครั้ง ถ้า hash ทุกแถว ไฟล์ 500 แถว
     * จะเกิน max_execution_time และ timeout ของเบราว์เซอร์ จนไม่ได้รับการตอบกลับ
     *
     * @var array|null
     */
    private $password = null;

    /**
     * category_id ที่บันทึกไปแล้ว [type][topic] => category_id
     *
     * @var array
     */
    private $categoryIds = [];

    /**
     * หัวข้อของไฟล์ CSV (ใช้ตอนสร้างไฟล์ตัวอย่าง)
     *
     * @return array
     */
    public static function headers()
    {
        return array_map(function ($labels) {
            return $labels[0];
        }, self::columns());
    }

    /**
     * ชื่อคอลัมน์ที่ยอมรับได้ของแต่ละช่อง ตัวแรกคือชื่อที่ใช้สร้างไฟล์ตัวอย่าง
     *
     * ระบบเดิมแปล Name และ Identification No. ไว้คนละคำกับ adminframework
     * จึงรับคำเดิมไว้ด้วย ไฟล์ CSV ที่ผู้ใช้มีอยู่แล้วจะยังนำเข้าได้เหมือนเดิม
     *
     * @return array
     */
    public static function columns()
    {
        return [
            Base::labelsOf('Name', ['ชื่อ นามสกุล']),
            Base::labelsOf('Identification No.', ['รหัสพนักงาน']),
            Base::labelsOf('Email'),
            Base::labelsOf('Sex'),
            Base::labelsOf('Department'),
            Base::labelsOf('Position')
        ];
    }

    /**
     * อ่านไฟล์ CSV แล้วบันทึกลงฐานข้อมูล
     *
     * @param string $filename ไฟล์ที่อัปโหลดมา
     *
     * @return int จำนวนรายการที่นำเข้า
     */
    public static function import($filename)
    {
        $model = new static();

        \Kotchasan\Csv::read(
            $filename,
            [$model, 'importRow'],
            null,
            self::$cfg->salary_csv_language,
            [$model, 'mapColumns']
        );

        return $model->row;
    }

    /**
     * จับคู่คอลัมน์ของไฟล์กับช่องข้อมูลที่ต้องการ (เรียกครั้งเดียวตอนอ่านบรรทัดหัวตาราง)
     *
     * @param array $columns ชื่อคอลัมน์ที่อ่านได้จากไฟล์
     *
     * @throws \Exception ถ้าไฟล์ขาดคอลัมน์ที่จำเป็น
     */
    public function mapColumns($columns)
    {
        $this->header = Base::matchColumns($columns, self::columns(), [3, 4, 5]);
    }

    /**
     * บันทึกข้อมูล 1 แถวจากไฟล์ CSV
     *
     * @param array $data
     */
    public function importRow($data)
    {
        $name = Text::topic($data[$this->header[0]] ?? '');
        $id_card = preg_replace('/[^0-9]+/', '', $data[$this->header[1]] ?? '');
        $username = trim($data[$this->header[2]] ?? '');
        if ($name === '' || $id_card === '' || $username === '') {
            return;
        }

        $save = [
            'name' => $name,
            'id_card' => $id_card,
            'username' => $username,
            'sex' => $this->mapSex($this->column($data, 3))
        ];

        $db = \Kotchasan\DB::create();
        $existing = static::createQuery()
            ->select('id')
            ->from('user')
            ->where([
                ['id_card', $id_card],
                ['username', $username]
            ], 'OR')
            ->first();

        if ($existing) {
            $db->update('user', [['id', $existing->id]], $save);
            $user_id = (int) $existing->id;
        } else {
            if ($this->password === null) {
                $this->password = \Index\Auth\Model::hashPassword(self::DEFAULT_PASSWORD);
            }
            $password = $this->password;
            $save['status'] = self::$cfg->salary_employee_status;
            $save['active'] = 1;
            $save['permission'] = '';
            $save['country'] = 'TH';
            $save['created_at'] = date('Y-m-d H:i:s');
            $save['salt'] = $password['salt'];
            $save['password'] = $password['hash'];
            $user_id = (int) $db->insert('user', $save);
        }

        if (empty($user_id)) {
            return;
        }

        $this->saveMeta($db, $user_id, 'department', Text::topic($this->column($data, 4)));
        $this->saveMeta($db, $user_id, 'position', Text::topic($this->column($data, 5)));

        ++$this->row;
    }

    /**
     * บันทึกแผนก/ตำแหน่งของพนักงาน สร้างหมวดหมู่ใหม่ให้ถ้ายังไม่มี
     *
     * ระบบเดิมเพิ่มแถวใหม่ทุกครั้งที่นำเข้า ทำให้พนักงานคนเดิมมีแผนกซ้ำหลายแถว
     * จึงลบค่าเดิมของหมวดหมู่นั้นก่อนเสมอ (ไม่แตะ meta ชนิดอื่นของสมาชิก)
     *
     * @param \Kotchasan\DB $db
     * @param int $user_id
     * @param string $type
     * @param string $topic
     */
    private function saveMeta($db, $user_id, $type, $topic)
    {
        if ($topic === '') {
            return;
        }

        if (!isset($this->categoryIds[$type][$topic])) {
            $this->categoryIds[$type][$topic] = \Salary\Category\Model::save($type, $topic);
        }
        $category_id = $this->categoryIds[$type][$topic];
        if (empty($category_id)) {
            return;
        }

        $db->delete('user_meta', [['member_id', $user_id], ['name', $type]], 0);
        $db->insert('user_meta', [
            'member_id' => $user_id,
            'name' => $type,
            'value' => $category_id
        ]);
    }

    /**
     * อ่านค่าของคอลัมน์ตามลำดับที่จับคู่ไว้ ไม่มีคอลัมน์นั้นคืนค่าว่าง
     *
     * @param array $data
     * @param int $index
     *
     * @return string
     */
    private function column($data, $index)
    {
        return isset($this->header[$index]) ? (string) ($data[$this->header[$index]] ?? '') : '';
    }

    /**
     * แปลงข้อมูลเพศจากไฟล์ CSV
     *
     * @param string $gender
     *
     * @return string
     */
    private function mapSex($gender)
    {
        switch (strtolower(trim($gender))) {
            case 'm':
            case 'male':
            case 'ชาย':
                return 'm';
            case 'f':
            case 'female':
            case 'หญิง':
                return 'f';
            default:
                return 'u';
        }
    }
}
