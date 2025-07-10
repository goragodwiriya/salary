<?php
/* settings/database.php */

return [
    'mysql' => [
        'dbdriver' => 'mysql',
        'username' => 'root',
        'password' => '',
        'dbname' => 'salary',
        'prefix' => 'app'
    ],
    'tables' => [
        'category' => 'category',
        'employees' => 'employees',
        'language' => 'language',
        'leave' => 'leave',
        'leave_items' => 'leave_items',
        'logs' => 'logs',
        'recruitments' => 'recruitments',
        'terminations' => 'terminations',
        'user' => 'user',
        'user_meta' => 'user_meta'
    ]
];
