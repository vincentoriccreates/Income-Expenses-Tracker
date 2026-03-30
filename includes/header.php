<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> <?= isset($pageTitle) ? "- $pageTitle" : '' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<div class="d-flex" id="wrapper">
    <nav id="sidebar" class="bg-dark text-white d-flex flex-column">
        <div class="sidebar-brand p-3 border-bottom border-secondary d-flex align-items-center gap-2">
            <div class="brand-icon">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <div class="fw-bold fs-6">WVR Tracker</div>
                <small class="text-secondary" style="font-size:11px">White Villas Resort</small>
            </div>
        </div>

        <div class="sidebar-menu flex-grow-1 p-2 pt-3">
            <div class="nav-section-label">OVERVIEW</div>
            <a href="index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>

            <div class="nav-section-label mt-3">INCOME</div>
            <a href="income.php" class="nav-item <?= $currentPage === 'income' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> All Income
            </a>
            <a href="income.php?type=cash" class="nav-item sub <?= ($currentPage === 'income' && ($_GET['type'] ?? '') === 'cash') ? 'active' : '' ?>">
                <i class="bi bi-cash"></i> Paid by Cash
            </a>
            <a href="income.php?type=card" class="nav-item sub <?= ($currentPage === 'income' && ($_GET['type'] ?? '') === 'card') ? 'active' : '' ?>">
                <i class="bi bi-credit-card"></i> Paid by Card
            </a>
            <a href="income.php?type=room" class="nav-item sub <?= ($currentPage === 'income' && ($_GET['type'] ?? '') === 'room') ? 'active' : '' ?>">
                <i class="bi bi-door-open"></i> Room Charged
            </a>

            <div class="nav-section-label mt-3">EXPENSES</div>
            <a href="expense.php" class="nav-item <?= $currentPage === 'expense' ? 'active' : '' ?>">
                <i class="bi bi-receipt-cutoff"></i> All Expenses
            </a>
            <a href="expense.php?type=petty" class="nav-item sub <?= ($currentPage === 'expense' && ($_GET['type'] ?? '') === 'petty') ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Petty Expenses
            </a>
            <a href="expense.php?type=hl" class="nav-item sub <?= ($currentPage === 'expense' && ($_GET['type'] ?? '') === 'hl') ? 'active' : '' ?>">
                <i class="bi bi-bank"></i> H/L Expenses
            </a>

            <div class="nav-section-label mt-3">MANAGEMENT</div>
            <a href="categories.php" class="nav-item <?= $currentPage === 'categories' ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i> Categories
            </a>
            <a href="reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-fill"></i> Reports
            </a>
            <a href="import.php" class="nav-item <?= $currentPage === 'import' ? 'active' : '' ?>">
                <i class="bi bi-upload"></i> Import Data
            </a>
        </div>

        <div class="p-3 border-top border-secondary">
            <small class="text-secondary">v1.0.0 &copy; <?= date('Y') ?> WVR</small>
        </div>
    </nav>

    <!-- Page Content -->
    <div id="page-content" class="flex-grow-1 d-flex flex-column">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2 sticky-top">
            <button class="btn btn-sm btn-outline-secondary me-3" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <span class="navbar-brand mb-0 h6 text-secondary fw-normal">
                <?= $pageTitle ?? APP_NAME ?>
            </span>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                    <i class="bi bi-circle-fill me-1" style="font-size:8px"></i>Live
                </span>
                <span class="text-muted small"><?= date('M d, Y') ?></span>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow-1 p-4" style="background:#f8f9fb">
            <?= renderFlash() ?>
