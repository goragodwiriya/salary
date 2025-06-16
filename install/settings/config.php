<?php
/* config.php */
return [
    'version' => '6.9.0',
    'web_title' => 'Salary',
    'web_description' => 'ระบบเงินเดือนออนไลน์',
    'timezone' => 'Asia/Bangkok',
    'member_status' => [
        0 => 'พนักงาน',
        1 => 'ผู้ดูแลระบบ',
        2 => 'ผู้อนุมัติ'
    ],
    'color_status' => [
        0 => '#259B24',
        1 => '#FF0000',
        2 => '#0E0EDA'
    ],
    'default_icon' => 'icon-verfied',
    'salary_social_employer' => 5.0,
    'salary_social_max' => 15000.0,
    'salary_overtime_rate' => 1.5,
    'salary_working_days' => 22,
    'salary_working_hours' => 8.0,
    'salary_require_approval' => 1,
    'salary_auto_calculate' => 1,
    'salary_notification' => 1
];
