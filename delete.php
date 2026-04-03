<?php
// ============================================================
// delete.php — Delete Record Handler
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$tableAlias = $_GET['table']    ?? '';
$id         = (int)($_GET['id'] ?? 0);
$redirect   = $_GET['redirect'] ?? 'index.php';

$allowedTables = [
    'income_cash'         => 'income_cash',
    'income_card'         => 'income_card',
    'income_room'         => 'income_roomcharged',
    'expense_petty'       => 'petty_expenses',
    'expense_hl'          => 'hl_expenses',
    'categories'          => 'categories',
];

if (!isset($allowedTables[$tableAlias]) || !$id) {
    setFlash('danger', 'Invalid delete request.');
    header('Location: ' . ($redirect ?: 'index.php'));
    exit;
}

$table = $allowedTables[$tableAlias];

try {
    Database::execute("DELETE FROM $table WHERE id = ?", [$id]);
    setFlash('success', 'Record deleted successfully.');
} catch (Exception $e) {
    setFlash('danger', 'Error deleting record: ' . $e->getMessage());
}

header('Location: ' . urldecode($redirect));
exit;
