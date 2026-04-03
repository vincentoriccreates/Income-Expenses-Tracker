<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$_currentUser = currentUser();
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
            <?php if (isAdmin()): ?>
            <a href="users.php" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Users
            </a>
            <?php endif; ?>
        </div>

        <div class="p-3 border-top border-secondary">
            <div class="d-flex align-items-center gap-2 mb-1">
                <div style="width:24px;height:24px;border-radius:50%;background:#4f6ef7;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;flex-shrink:0">
                    <?= strtoupper(substr($_currentUser['name'],0,1)) ?>
                </div>
                <div style="min-width:0">
                    <div class="text-white small fw-semibold text-truncate" style="font-size:12px"><?= htmlspecialchars($_currentUser['name']) ?></div>
                    <div class="text-secondary" style="font-size:10px"><?= ucfirst($_currentUser['role']) ?></div>
                </div>
                <a href="logout.php" class="ms-auto text-secondary" title="Sign Out" style="font-size:15px">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
            <small class="text-secondary" style="font-size:10px">v1.0.0 &copy; <?= date('Y') ?> WVR</small>
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
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-muted small d-none d-md-inline"><?= date('M d, Y') ?></span>

                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm d-flex align-items-center gap-2 border" data-bs-toggle="dropdown">
                        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#4f6ef7,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0">
                            <?= strtoupper(substr($_currentUser['name'],0,1)) ?>
                        </div>
                        <span class="d-none d-md-inline small fw-semibold"><?= htmlspecialchars($_currentUser['name']) ?></span>
                        <i class="bi bi-chevron-down" style="font-size:10px"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width:200px;border-radius:12px;margin-top:8px">
                        <li>
                            <div class="px-3 py-2 border-bottom">
                                <div class="fw-semibold small"><?= htmlspecialchars($_currentUser['name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($_currentUser['email']) ?></div>
                                <span class="badge <?= $_currentUser['role']==='admin'?'bg-primary':'bg-success' ?> mt-1" style="font-size:10px">
                                    <?= ucfirst($_currentUser['role']) ?>
                                </span>
                            </div>
                        </li>
                        <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2 text-muted"></i>My Profile</a></li>
                        <li><a class="dropdown-item py-2" href="profile.php#password" onclick="setTimeout(()=>document.querySelector('[onclick*=password]')?.click(),200)"><i class="bi bi-lock me-2 text-muted"></i>Change Password</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a class="dropdown-item py-2" href="users.php"><i class="bi bi-people me-2 text-muted"></i>Manage Users</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow-1 p-4" style="background:#f8f9fb">
            <?= renderFlash() ?>
