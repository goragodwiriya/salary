-- ---------------------------------------------------------------------------
-- modules/salary/install/database.sql — ตารางที่โมดูล salary เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- และห้ามเขียน CREATE TABLE ซ้ำไว้ในตัวปรับรุ่นอีกชุด
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_salary`
-- ข้อมูลเงินเดือนรายเดือนของพนักงาน 1 แถว = 1 คน/เดือน
-- `id` ประกอบขึ้นจาก member_id + ปี (4 หลัก) + เดือน (2 หลัก) จึงไม่ใช่ AUTO_INCREMENT
-- และ UNIQUE (member_id, year, month) กันข้อมูลซ้ำเดือนเดียวกันอีกชั้นหนึ่ง
-- `overtime_hours` > 0 = `overtime` คำนวณจากชั่วโมง, 0 = `overtime` เป็นจำนวนเงินที่กรอกมา
--

CREATE TABLE `{prefix}_salary` (
  `id` varchar(15) NOT NULL,
  `member_id` int(11) NOT NULL,
  `year` varchar(4) NOT NULL,
  `month` varchar(2) NOT NULL,
  `basic_salary` double(10,2) NOT NULL DEFAULT 0.00,
  `allowance` double(10,2) NOT NULL DEFAULT 0.00,
  `overtime` double(10,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` double(10,2) NOT NULL DEFAULT 0.00,
  `bonus` double(10,2) NOT NULL DEFAULT 0.00,
  `deduction` double(10,2) NOT NULL DEFAULT 0.00,
  `social_security` double(10,2) NOT NULL DEFAULT 0.00,
  `tax` double(10,2) NOT NULL DEFAULT 0.00,
  `net_salary` double(10,2) NOT NULL DEFAULT 0.00,
  `remark` text DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_period` (`member_id`,`year`,`month`),
  KEY `member_id` (`member_id`),
  KEY `year_month` (`year`,`month`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
