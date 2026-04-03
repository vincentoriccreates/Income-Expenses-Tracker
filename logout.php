<?php
// ============================================================
// logout.php — Logout Handler
// ============================================================
session_start();
session_unset();
session_destroy();

// Clear remember-me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: login.php?msg=logout');
exit;
