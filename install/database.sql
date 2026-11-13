-- -------------------------------------------------------------------------
-- install/database.sql — ข้อมูลตั้งต้นของ salary/adminframework
--
-- ตารางแกนของ Gcms (user, category, logs, login_attempt, number, migration,
-- user_meta, user_session, language) อยู่ใน install/core.sql
-- ตารางของโมดูล salary อยู่ใน modules/salary/install/database.sql
-- ไฟล์นี้จึงเหลือเฉพาะข้อมูลตั้งต้นที่ลงในตารางแกน ซึ่งไม่มีโมดูลไหนเป็นเจ้าของ
-- -------------------------------------------------------------------------

-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 24, 2026 at 12:27 PM
-- Server version: 10.4.34-MariaDB
-- PHP Version: 7.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
--
-- Dumping data for table `{prefix}_category`
--

INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('department', '1', 'บริหาร', NULL, 1),
('department', '2', 'จัดซื้อจัดจ้าง', NULL, 1),
('department', '3', 'บุคคล', NULL, 1);
