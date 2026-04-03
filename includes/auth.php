<?php
// ============================================================
// includes/auth.php — Authentication Guard
// Include at the top of every protected page
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /wvr_tracker/login.php' . ($redirect ? "?redirect=$redirect" : ''));
        exit;
    }
    // Refresh session activity timestamp
    $_SESSION['last_activity'] = time();
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: /wvr_tracker/index.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']       ?? 0,
        'name'     => $_SESSION['user_name']      ?? 'Guest',
        'username' => $_SESSION['user_username']  ?? '',
        'email'    => $_SESSION['user_email']     ?? '',
        'role'     => $_SESSION['user_role']      ?? 'staff',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

// Auto-logout after 2 hours of inactivity
if (isLoggedIn()) {
    $timeout = 7200; // 2 hours
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: /wvr_tracker/login.php?msg=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
