<?php
// ============================================================
// setup.php — First-Time Setup: Creates Admin Account
// DELETE this file after running it once!
// ============================================================
require_once 'config.php';

$message = '';
$error   = '';
$done    = false;

// Check if users table exists
try {
    Database::fetch("SELECT 1 FROM users LIMIT 1");
} catch (Exception $e) {
    $error = 'The <strong>users</strong> table does not exist yet. Please import <code>users_table.sql</code> into phpMyAdmin first, then refresh this page.';
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? 'Administrator');
    $username = trim($_POST['username'] ?? 'admin');
    $email    = trim($_POST['email']    ?? 'admin@wvr.com');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!$name || !$username || !$email)    $error = 'All fields are required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email.';
    elseif (strlen($password) < 6)          $error = 'Password must be at least 6 characters.';
    elseif ($password !== $confirm)         $error = 'Passwords do not match.';
    else {
        // Check if user already exists
        $exists = Database::fetch("SELECT id FROM users WHERE username=? OR email=?", [$username, $email]);
        if ($exists) {
            // Update existing user's password
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute("UPDATE users SET name=?, password=?, role='admin', is_active=1 WHERE username=? OR email=?",
                [$name, $hash, $username, $email]);
            $message = "Existing user updated with new password.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute(
                "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?,'admin',1)",
                [$name, $username, $email, $hash]
            );
            $message = "Admin account created successfully!";
        }
        $done = true;
    }
}

// If no error and GET request, check if admin exists
$adminExists = false;
if (!$error) {
    try {
        $adminExists = (bool) Database::fetch("SELECT id FROM users WHERE role='admin' LIMIT 1");
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WVR Setup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg,#1a1d27,#2d3555); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .setup-card { background:#fff; border-radius:20px; padding:40px; max-width:460px; width:100%; box-shadow:0 25px 60px rgba(0,0,0,.4); }
        .setup-header { text-align:center; margin-bottom:28px; }
        .icon { width:60px;height:60px;background:linear-gradient(135deg,#4f6ef7,#2d3555);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;margin:0 auto 14px; }
        .form-control { border-radius:10px; }
        .btn-setup { background:linear-gradient(135deg,#4f6ef7,#3b5bd6);border:none;border-radius:10px;padding:12px;font-weight:600; }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="setup-header">
        <div class="icon">🏨</div>
        <h4 class="fw-bold mb-1">WVR Tracker Setup</h4>
        <p class="text-muted small mb-0">Create your admin account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($done): ?>
    <div class="alert alert-success rounded-3">
        <strong>✓ <?= $message ?></strong><br>
        <small>You can now log in. <strong>Please delete this setup.php file!</strong></small>
    </div>
    <a href="login.php" class="btn btn-success w-100 rounded-3 py-2 fw-semibold">Go to Login →</a>

    <?php elseif (!$error): ?>
    <?php if ($adminExists): ?>
    <div class="alert alert-warning rounded-3 small">
        An admin account already exists. Submit below to <strong>reset its password</strong>.
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="name" class="form-control" value="Administrator" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Username</label>
            <input type="text" name="username" class="form-control" value="admin" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" name="email" class="form-control" value="admin@wvr.com" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-setup text-white w-100">
            <?= $adminExists ? '🔑 Reset Admin Password' : '✓ Create Admin Account' ?>
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
