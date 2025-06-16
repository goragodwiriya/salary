<?php
/**
 * @filesource modules/salary/models/importusers.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Importusers;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Text;

/**
 * นำเข้าข้อมูลพนักงานจาก CSV
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * @var int
     */
    private $row = 0;
    /**
     * @var array
     */
    private $header = [];
    /**
     * @var string
     */
    private $table_user;
    /**
     * @var string
     */
    private $table_meta;
    /**
     * @var \Index\Category\Model
     */
    private $category;

    /**
     * ชื่อหัวข้อของตาราง
     *
     * @return array
     */
    public static function headers()
    {
        return [
            Language::get('Name'),
            Language::get('Identification No.'),
            Language::get('Email'),
            Language::get('Sex'),
            Language::get('Department'),
            Language::get('Position')
        ];
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (importusers.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_manage_salary
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_manage_salary')) {
                try {
                    $this->header = self::headers();
                    $this->table_user = $this->getTableName('user');
                    $this->table_meta = $this->getTableName('user_meta');
                    $this->category = \Index\Category\Model::init();
                    // อัปโหลดไฟล์
                    foreach ($request->getUploadedFiles() as $item => $file) {
                        /* @var $file \Kotchasan\Http\UploadedFile */
                        if ($file->hasUploadFile()) {
                            if (!$file->validFileExt(['csv'])) {
                                // ชนิดของไฟล์ไม่ถูกต้อง
                                $ret['ret_'.$item] = Language::get('The type of file is invalid');
                            } else {
                                try {
                                    // import ข้อมูล
                                    \Kotchasan\Csv::read(
                                        $file->getTempFileName(),
                                        [$this, 'importEmployee'],
                                        $this->header,
                                        self::$cfg->csv_language
                                    );
                                    // ส่งค่ากลับ
                                    $ret['alert'] = Language::replace('Successfully imported :count items', [':count' => $this->row]);
                                    $ret['location'] = 'reload';
                                    // log
                                    \Index\Log\Model::add(0, 'salary', 'Import Users', $ret['alert'], $login['id']);
                                } catch (\Throwable $th) {
                                    $ret['ret_'.$item] = $th->getMessage();
                                }
                            }
                        } elseif ($file->hasError()) {
                            // upload error
                            $ret['ret_'.$item] = $file->getErrorMessage();
                        } else {
                            // ไม่ได้เลือกไฟล์
                            $ret['ret_'.$item] = 'Please browse file';
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * ฟังก์ชั่นรับค่าจากการอ่าน CSV
     *
     * @param array $data
     */
    public function importEmployee($data)
    {
        $name = Text::topic($data[$this->header[0]]);
        $id_card = preg_replace('/[^0-9]+/', '', $data[$this->header[1]]);
        $username = trim($data[$this->header[2]]);
        $sex = $this->mapSex($data[$this->header[3]]);
        $department = Text::topic($data[$this->header[4]]);
        $position = Text::topic($data[$this->header[5]]);

        if ($name != '' && $id_card != '' && $username != '') {
            // ตรวจสอบว่ามีพนักงานคนนี้อยู่แล้วหรือไม่ (ตาม id_card, username)
            $existing = static::createQuery()
                ->from('user')
                ->where([
                    ['id_card', $id_card],
                    ['username', $username]
                ], 'OR')
                ->first('id');

            // เตรียมข้อมูลสำหรับบันทึก
            $save = [
                'name' => $name,
                'id_card' => $id_card,
                'username' => $username,
                'sex' => $sex
            ];

            if ($existing) {
                // อัปเดตข้อมูล
                $this->db()->update($this->table_user, $existing->id, $save);
                $user_id = $existing->id;
            } else {
                // ไม่มีอยู่ - เพิ่มใหม่
                $save['status'] = self::$cfg->empoyees_status; // สถานะพนักงาน (ค่าจากการตั้งค่า)
                $save['active'] = 1; // พนักงานปัจจุบัน
                $save['permission'] = '';
                $save['country'] = 'TH';
                $save['create_date'] = date('Y-m-d H:i:s');
                // เพิ่มรหัสผ่านเริ่มต้น
                $salt = uniqid();
                $password_key = uniqid();
                $default_password = '123456';
                $save['salt'] = $salt;
                $save['password'] = sha1($password_key.$default_password.$salt);
                // เพิ่มข้อมูลใหม่
                $user_id = $this->db()->insert($this->table_user, $save);
            }

            if ($department !== '') {
                $department_id = $this->category->save('department', $department);
                if (!empty($department_id)) {
                    $this->db()->insert($this->table_meta, [
                        'value' => $department_id,
                        'name' => 'department',
                        'member_id' => $user_id
                    ]);
                }
            }
            if ($position !== '') {
                $position_id = $this->category->save('position', $position);
                if (!empty($position_id)) {
                    $this->db()->insert($this->table_meta, [
                        'value' => $position_id,
                        'name' => 'position',
                        'member_id' => $user_id
                    ]);
                }
            }

            // นำเข้าข้อมูลสำเร็จ
            ++$this->row;
        }
    }

    /**
     * แปลงข้อมูลเพศ
     *
     * @param string $gender
     * @return string
     */
    private function mapSex($gender)
    {
        $gender = strtolower(trim($gender));

        switch ($gender) {
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
