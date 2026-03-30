<?php
// ============================================================
// exports/export.php — CSV Export Handler
// ============================================================
require_once '../config.php';
require_once '../functions.php';

$table  = $_GET['table']  ?? '';
$year   = (int)($_GET['year']  ?? 0);
$month  = (int)($_GET['month'] ?? 0);
$type   = $_GET['type']   ?? 'all';
$search = $_GET['search'] ?? '';
$filterMonth = $_GET['filter_month'] ?? '';
$dateFrom    = $_GET['date_from']    ?? '';
$dateTo      = $_GET['date_to']      ?? '';

$conditions = [];
$params = [];

if ($filterMonth) { $conditions[] = "DATE_FORMAT(date,'%Y-%m') = ?"; $params[] = $filterMonth; }
if ($dateFrom)    { $conditions[] = "date >= ?"; $params[] = $dateFrom; }
if ($dateTo)      { $conditions[] = "date <= ?"; $params[] = $dateTo; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

switch ($table) {
    case 'income_cash':
    case 'income_cash':
        if ($search) { $where .= ($where?' AND':'WHERE')." (category LIKE ? OR notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $rows    = Database::fetchAll("SELECT date, category, amount, week_number, month, notes FROM income_cash $where ORDER BY date DESC", $params);
        $headers = ['Date','Category','Amount','Week','Month','Notes'];
        $fname   = 'income_cash_' . date('Ymd') . '.csv';
        break;

    case 'income_card':
        if ($search) { $where .= ($where?' AND':'WHERE')." (category LIKE ? OR notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $rows    = Database::fetchAll("SELECT date, category, amount, week_number, month, notes FROM income_card $where ORDER BY date DESC", $params);
        $headers = ['Date','Category','Amount','Week','Month','Notes'];
        $fname   = 'income_card_' . date('Ymd') . '.csv';
        break;

    case 'income_room':
        if ($search) { $where .= ($where?' AND':'WHERE')." room_reference LIKE ?"; $params[] = "%$search%"; }
        $rows    = Database::fetchAll("SELECT date, room_reference, amount, week_number, month, notes FROM income_roomcharged $where ORDER BY date DESC", $params);
        $headers = ['Date','Room Reference','Amount','Week','Month','Notes'];
        $fname   = 'income_roomcharged_' . date('Ymd') . '.csv';
        break;

    case 'income_all':
        $rows = Database::fetchAll("SELECT date,'Cash' AS type, category AS description, amount, week_number, month FROM income_cash
            UNION ALL SELECT date,'Card', category, amount, week_number, month FROM income_card
            UNION ALL SELECT date,'Room', COALESCE(room_reference,'—'), amount, week_number, month FROM income_roomcharged
            ORDER BY date DESC");
        $headers = ['Date','Type','Description','Amount','Week','Month'];
        $fname   = 'income_all_' . date('Ymd') . '.csv';
        break;

    case 'expense_petty':
        if ($search) { $where .= ($where?' AND':'WHERE')." description LIKE ?"; $params[] = "%$search%"; }
        $rows    = Database::fetchAll("SELECT date, description, amount, week_number, month, notes FROM petty_expenses $where ORDER BY date DESC", $params);
        $headers = ['Date','Description','Amount','Week','Month','Notes'];
        $fname   = 'petty_expenses_' . date('Ymd') . '.csv';
        break;

    case 'expense_hl':
        if ($search) { $where .= ($where?' AND':'WHERE')." description LIKE ?"; $params[] = "%$search%"; }
        $rows    = Database::fetchAll("SELECT date, description, amount, week_number, month, notes FROM hl_expenses $where ORDER BY date DESC", $params);
        $headers = ['Date','Description','Amount','Week','Month','Notes'];
        $fname   = 'hl_expenses_' . date('Ymd') . '.csv';
        break;

    case 'expense_all':
        $rows = Database::fetchAll("SELECT date,'Petty' AS type, description, amount, week_number, month FROM petty_expenses
            UNION ALL SELECT date,'H/L', description, amount, week_number, month FROM hl_expenses
            ORDER BY date DESC");
        $headers = ['Date','Type','Description','Amount','Week','Month'];
        $fname   = 'expenses_all_' . date('Ymd') . '.csv';
        break;

    case 'report':
        // Monthly report export
        $p  = [$year, $month];
        $rows = [];
        $petty = Database::fetchAll("SELECT date,'Petty' AS type, description, amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date", $p);
        $hl    = Database::fetchAll("SELECT date,'H/L'  AS type, description, amount FROM hl_expenses    WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date", $p);
        $cash  = Database::fetchAll("SELECT date,'Cash Income' AS type, category AS description, amount FROM income_cash WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date", $p);
        $card  = Database::fetchAll("SELECT date,'Card Income' AS type, category AS description, amount FROM income_card WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date", $p);
        $room  = Database::fetchAll("SELECT date,'Room Charged' AS type, COALESCE(room_reference,'—') AS description, amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date", $p);
        $rows  = array_merge($petty, $hl, $cash, $card, $room);
        usort($rows, fn($a,$b) => strcmp($a['date'],$b['date']));
        $headers = ['Date','Type','Description','Amount'];
        $fname   = 'report_' . date('F_Y', mktime(0,0,0,$month,1,$year)) . '.csv';
        break;

    default:
        die('Invalid export request.');
}

exportCSV($rows, $headers, $fname);
