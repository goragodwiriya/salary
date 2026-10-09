<?php
/**
 * @filesource modules/salary/models/base.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Base;

use Kotchasan\Language;

/**
 * ตัวช่วยที่ใช้ร่วมกันของทุกหน้าในโมดูลเงินเดือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * รหัสของรายการเงินเดือน = member_id + ปี 4 หลัก + เดือน 2 หลัก
     * รูปแบบนี้ทำให้ 1 คนมีได้เดือนละ 1 รายการเท่านั้น (ตรงกับระบบเดิม)
     *
     * @param int $member_id
     * @param int|string $year
     * @param int|string $month
     *
     * @return string
     */
    public static function recordId($member_id, $year, $month)
    {
        return ((int) $member_id).sprintf('%04d', (int) $year).sprintf('%02d', (int) $month);
    }

    /**
     * เดือนเก็บเป็น 2 หลักเสมอ ('01'..'12')
     *
     * @param int|string $month
     *
     * @return string
     */
    public static function monthValue($month)
    {
        return sprintf('%02d', (int) $month);
    }

    /**
     * ข้อความ เดือน ปี ตามภาษาที่ใช้งาน (ปี พ.ศ. เมื่อเป็นภาษาไทย)
     *
     * @param int|string $year
     * @param int|string $month
     * @param bool $short ใช้ชื่อเดือนแบบย่อ
     *
     * @return string
     */
    public static function periodText($year, $month, $short = false)
    {
        $months = (array) Language::get($short ? 'MONTH_SHORT' : 'MONTH_LONG');
        $month = (int) $month;
        $text = isset($months[$month]) ? $months[$month] : (string) $month;

        return trim($text.' '.self::displayYear($year));
    }

    /**
     * ข้อความค่าล่วงเวลา พร้อมจำนวนชั่วโมงถ้าคิดจากชั่วโมง เช่น "ค่าล่วงเวลา (7.5 ชั่วโมง)"
     *
     * @param float $hours
     *
     * @return string
     */
    public static function overtimeLabel($hours)
    {
        $label = Language::get('Overtime');
        if ((float) $hours <= 0) {
            return $label;
        }
        $hours = rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');

        return $label.' ('.$hours.' '.Language::get('hours').')';
    }

    /**
     * ปีที่แสดงผล (บวก YEAR_OFFSET ของภาษา)
     *
     * @param int|string $year
     *
     * @return int
     */
    public static function displayYear($year)
    {
        return (int) $year + (int) Language::get('YEAR_OFFSET');
    }

    /**
     * ตัวเลือกสถานะของรายการเงินเดือน
     *
     * @return array
     */
    public static function statusOptions()
    {
        $result = [];
        foreach ((array) Language::get('SALARY_STATUS') as $key => $text) {
            $result[] = [
                'value' => (string) $key,
                'text' => $text
            ];
        }

        return $result;
    }

    /**
     * ข้อความของสถานะ
     *
     * @param int $status
     *
     * @return string
     */
    public static function statusText($status)
    {
        $statuses = (array) Language::get('SALARY_STATUS');
        $status = (int) $status;

        return isset($statuses[$status]) ? $statuses[$status] : (string) $status;
    }

    /**
     * ตัวเลือกเดือน
     *
     * @return array
     */
    public static function monthOptions()
    {
        $result = [];
        foreach ((array) Language::get('MONTH_LONG') as $key => $text) {
            $result[] = [
                'value' => self::monthValue($key),
                'text' => $text
            ];
        }

        return $result;
    }

    /**
     * ตัวเลือกปี ย้อนหลังตามจำนวนที่กำหนดจนถึงปีถัดไป
     *
     * @param int $back จำนวนปีย้อนหลัง
     * @param int $forward จำนวนปีข้างหน้า
     *
     * @return array
     */
    public static function yearOptions($back = 5, $forward = 1)
    {
        $result = [];
        $currentYear = (int) date('Y');
        for ($i = $currentYear + $forward; $i >= $currentYear - $back; $i--) {
            $result[] = [
                'value' => (string) $i,
                'text' => (string) self::displayYear($i)
            ];
        }

        return $result;
    }

    /**
     * ชื่อคอลัมน์ที่ยอมรับได้ของหัวข้อ CSV 1 ช่อง
     *
     * ภาษาของ API มาจาก Accept-Language ของผู้เรียก (`initLanguage`) หัวตารางของไฟล์
     * จึงอาจเป็นคนละภาษากับตอนที่ผู้ใช้ส่งออกไฟล์ไว้ ที่นี่จึงรวบรวมคำแปลของทุกภาษา
     * ที่ติดตั้งไว้ บวกคีย์ภาษาอังกฤษ และคำที่ระบบเดิมใช้ มาเป็นชื่อที่ยอมรับได้ทั้งหมด
     *
     * @param string $key คีย์ภาษาของหัวข้อ
     * @param array $extra ชื่ออื่นที่ต้องการรับเพิ่ม (เช่น คำแปลของระบบเดิม)
     *
     * @return array
     */
    public static function labelsOf($key, array $extra = [])
    {
        static $languages = null;
        if ($languages === null) {
            $languages = [];
            foreach (Language::installedLanguage() as $lang) {
                $file = ROOT_PATH.'language/'.$lang.'.php';
                if (is_file($file)) {
                    $datas = include $file;
                    if (is_array($datas)) {
                        $languages[$lang] = $datas;
                    }
                }
            }
        }

        $labels = [Language::get($key)];
        foreach ($languages as $datas) {
            if (isset($datas[$key]) && is_string($datas[$key])) {
                $labels[] = $datas[$key];
            }
        }
        foreach ($extra as $label) {
            $labels[] = $label;
        }
        // คีย์ภาษาอังกฤษ ใช้เมื่อไฟล์ถูกส่งออกตอนที่ยังไม่มีคำแปล
        $labels[] = $key;

        return array_values(array_unique(array_filter($labels)));
    }

    /**
     * จับคู่ชื่อคอลัมน์ที่อ่านได้จากไฟล์ CSV กับช่องข้อมูลที่โมดูลต้องการ
     *
     * แต่ละช่องรับได้หลายชื่อ (คำแปลปัจจุบัน คำแปลของระบบเดิม และคีย์ภาษาอังกฤษ)
     * เพื่อให้ไฟล์ที่ผู้ใช้ส่งออกไว้จากระบบเดิมยังนำเข้าได้
     *
     * @param array $columns ชื่อคอลัมน์ที่อ่านได้จากไฟล์
     * @param array $definitions ชื่อที่ยอมรับได้ของแต่ละช่อง
     * @param array $optional ลำดับของช่องที่ไม่บังคับ
     *
     * @throws \Exception ถ้าไฟล์ขาดคอลัมน์ที่จำเป็น
     *
     * @return array ลำดับช่อง => ชื่อคอลัมน์จริงในไฟล์
     */
    public static function matchColumns(array $columns, array $definitions, array $optional = [])
    {
        // เทียบแบบไม่สนใจช่องว่างหัวท้ายและตัวพิมพ์
        $available = [];
        foreach ($columns as $name) {
            $available[mb_strtolower(trim((string) $name))] = $name;
        }

        $result = [];
        foreach ($definitions as $index => $labels) {
            foreach ((array) $labels as $label) {
                $key = mb_strtolower(trim((string) $label));
                if (isset($available[$key])) {
                    $result[$index] = $available[$key];
                    break;
                }
            }
            if (!isset($result[$index]) && !in_array($index, $optional, true)) {
                throw new \Exception(Language::replace('Column not found : :name', [':name' => $labels[0]]));
            }
        }

        return $result;
    }

    /**
     * ตัวเลือกพนักงานที่ยังทำงานอยู่ สำหรับฟอร์มเพิ่มรายการเงินเดือน
     *
     * @return array
     */
    public static function memberOptions()
    {
        $query = static::createQuery()
            ->select('id', 'name', 'id_card')
            ->from('user')
            ->where([['active', 1]])
            ->orderBy('name');

        $result = [];
        foreach ($query->fetchAll() as $item) {
            $result[] = [
                'value' => (string) $item->id,
                'text' => empty($item->id_card) ? $item->name : $item->name.' ('.$item->id_card.')'
            ];
        }

        return $result;
    }
}
