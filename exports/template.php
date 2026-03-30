<?php
// exports/template.php — Download blank CSV templates
require_once '../config.php';
require_once '../functions.php';

$type = $_GET['type'] ?? 'petty';

$templates = [
    'petty' => [
        'headers' => ['DATE','DESCRIPTION','AMOUNT'],
        'sample'  => [['2025-09-01','GASOLINE','442.00'],['2025-09-01','Guard Salary','10615.20']],
        'fname'   => 'template_petty_expenses.csv',
    ],
    'hl' => [
        'headers' => ['DATE','DESCRIPTION','AMOUNT'],
        'sample'  => [['2025-09-01','Resto Expenses','1221.00'],['2025-09-01','Drinks Expenses (BAR)','10311.00']],
        'fname'   => 'template_hl_expenses.csv',
    ],
    'cash' => [
        'headers' => ['DATE','CATEGORY','AMOUNT'],
        'sample'  => [['2025-09-01','Resto Income','7955.00'],['2025-09-01','Drinks Income','1035.00']],
        'fname'   => 'template_income_cash.csv',
    ],
    'card' => [
        'headers' => ['DATE','CATEGORY','AMOUNT'],
        'sample'  => [['2025-09-01','Resto Income','830.00'],['2025-09-01','Motor Income','1500.00']],
        'fname'   => 'template_income_card.csv',
    ],
    'room' => [
        'headers' => ['DATE','ROOM_REFERENCE','AMOUNT'],
        'sample'  => [['2025-09-01','B3 B4 B5','2525.00'],['2025-09-02','','3795.00']],
        'fname'   => 'template_income_roomcharged.csv',
    ],
];

if (!isset($templates[$type])) die('Invalid template type.');

$tpl = $templates[$type];
exportCSV($tpl['sample'], $tpl['headers'], $tpl['fname']);
