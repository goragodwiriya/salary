<?php
/**
 * modules/salary/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล salary
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 * * ⚠️ ก่อนมีไฟล์นี้ **ไม่มีอะไรแตะตารางของโมดูลนี้เลย** และ install/database.sql
 * ยังประกาศตารางแกนซ้ำอีก 7 ตาราง ทำให้ **ติดตั้งใหม่ไม่ได้เลย**
 *
 * นิยามตารางอยู่ที่ modules/salary/install/database.sql ที่เดียว — ไฟล์นี้อ่าน
 * นิยามจากที่นั่นผ่าน ensureTable() และรายการคอลัมน์ด้านล่างถูกสร้างจากไฟล์
 * เดียวกัน จึงไม่มีนิยามชุดที่สองให้ค่อย ๆ ต่างกัน
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

foreach ([
    'salary'
] as $_name) {
    $_table = $prefix.'_'.$_name;
    if (ensureTable($db, $prefix, $_table)) {
        $content[] = '<li class="correct">salary: สร้างตาราง '.$_name.'</li>';
    }
    // ⚠️ ต้องแปลงก่อนปรับคอลัมน์เสมอ — CONVERT TO CHARACTER SET เลื่อนชนิด TEXT
    // เป็น MEDIUMTEXT ถ้าแปลงทีหลังชนิดจะไม่ตรงกับที่ติดตั้งใหม่
    if (convertToInnoDB($db, $_table)) {
        $content[] = '<li class="correct">'.$_name.': แปลงเป็น InnoDB</li>';
    }
    if (convertToUtf8mb4($db, $_table)) {
        $content[] = '<li class="correct">'.$_name.': แปลงเป็น utf8mb4</li>';
    }
}

// salary — PRIMARY KEY และ AUTO_INCREMENT
//
// ⚠️ ตัวติดตั้งรุ่นเก่าประกาศคอลัมน์ id เป็น NOT NULL เฉย ๆ แล้วค่อยเติม
// PRIMARY KEY / AUTO_INCREMENT ด้วย ALTER TABLE ท้าย database.sql ซึ่ง
// ตัวปรับรุ่นไม่เคยรัน ไซต์ที่อัปเกรดจึงเพิ่มข้อมูลใหม่ไม่ได้เลย
if (!$db->indexExists($prefix.'_salary', 'PRIMARY')) {
    // ⚠️ ถ้าไซต์มีค่าซ้ำอยู่ก่อน (เช่น id เป็น 0 ทุกแถวเพราะไม่เคยมี AUTO_INCREMENT)
    // การเพิ่ม PRIMARY KEY จะล้มกลางคัน ต้องตรวจแล้วบอกให้ผู้ดูแลแก้เอง
    $_dup = $db->customQuery(
        "SELECT COUNT(*) AS `c` FROM (SELECT 1 FROM `".$prefix."_salary`
         GROUP BY `id` HAVING COUNT(*) > 1) `x`"
    );
    if (!empty($_dup) && (int) $_dup[0]->c > 0) {
        $content[] = '<li class="warning">salary: มีค่าซ้ำใน (id) อยู่ '
            .number_format((int) $_dup[0]->c).' ชุด จึงยังเพิ่ม PRIMARY KEY ไม่ได้ '
            .'กรุณาแก้ให้ไม่ซ้ำแล้วกดปรับรุ่นอีกครั้ง — ตัวปรับรุ่นจะไม่ลบให้เอง</li>';
    } else {
        $db->query("ALTER TABLE `".$prefix."_salary` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">salary: เพิ่ม PRIMARY KEY</li>';
    }
}
// salary
foreach ([
    'id' => ['varchar(15)', false, null, '', ''],
    'member_id' => ['int(11)', false, null, 'id', ''],
    'year' => ['varchar(4)', false, null, 'member_id', ''],
    'month' => ['varchar(2)', false, null, 'year', ''],
    'basic_salary' => ['double(10,2)', false, '0.00', 'month', ''],
    'allowance' => ['double(10,2)', false, '0.00', 'basic_salary', ''],
    'overtime' => ['double(10,2)', false, '0.00', 'allowance', ''],
    'overtime_hours' => ['double(10,2)', false, '0.00', 'overtime', ''],
    'bonus' => ['double(10,2)', false, '0.00', 'overtime_hours', ''],
    'deduction' => ['double(10,2)', false, '0.00', 'bonus', ''],
    'social_security' => ['double(10,2)', false, '0.00', 'deduction', ''],
    'tax' => ['double(10,2)', false, '0.00', 'social_security', ''],
    'net_salary' => ['double(10,2)', false, '0.00', 'tax', ''],
    'remark' => ['text', true, null, 'net_salary', ''],
    'create_date' => ['datetime', false, null, 'remark', ''],
    'status' => ['tinyint(1)', false, '0', 'create_date', '']
] as $_col => $_def) {
    if (ensureColumn($db, $prefix.'_salary', $_col, $_def[0], $_def[1], $_def[2], $_def[4], $_def[3])) {
        $content[] = '<li class="correct">salary: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $prefix.'_salary', [
    'member_id' => '`member_id`',
    'year_month' => '`year`, `month`',
    'status' => '`status`'
])) {
    $content[] = '<li class="correct">salary: ปรับดัชนี</li>';
}
// salary — ดัชนี UNIQUE
//
// ⚠️ ถ้าไซต์มีค่าซ้ำอยู่ก่อน คำสั่งจะล้ม ตัวปรับรุ่นต้องตรวจแล้วบอกให้ผู้ดูแล
// แก้เอง ห้ามลบแถวที่ซ้ำให้เอง — ข้อมูลของผู้ใช้ไม่ใช่ของเราที่จะทิ้ง
if (!$db->indexExists($prefix.'_salary', 'member_period')) {
    $_dup = $db->customQuery(
        "SELECT COUNT(*) AS `c` FROM (SELECT 1 FROM `".$prefix."_salary`
         GROUP BY `member_id`, `year`, `month` HAVING COUNT(*) > 1) `x`"
    );
    if (!empty($_dup) && (int) $_dup[0]->c > 0) {
        $content[] = '<li class="warning">salary: มีค่าซ้ำใน (`member_id`, `year`, `month`) อยู่ '
            .number_format((int) $_dup[0]->c).' ชุด จึงยังสร้างดัชนี member_period แบบ UNIQUE ไม่ได้ '
            .'กรุณาแก้ให้เหลือชุดเดียวแล้วกดปรับรุ่นอีกครั้ง</li>';
    } else {
        $db->query("ALTER TABLE `".$prefix."_salary` ADD UNIQUE KEY `member_period` (`member_id`, `year`, `month`)");
        $content[] = '<li class="correct">salary: เพิ่มดัชนี UNIQUE member_period</li>';
    }
}

$content[] = '<li class="correct">salary อัปเกรดสำเร็จ</li>';
