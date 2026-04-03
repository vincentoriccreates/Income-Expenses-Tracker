<?php
// ============================================================
// setup.php — One-time Setup: Creates Admin Account
// DELETE or RENAME this file after running it once!
// ============================================================
require_once 'config.php';

// Prevent running if admin already exists
$existing = Database::fetch("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($existing) {
    die('<div style="font-family:sans-serif;padding:30px;max-width:500px;margin:40px auto;background:#d1fae5;border:2px solid #059669;border-radius:12px">
        <h2 style="color:#059669">✓ Admin Already Exists</h2>
        <p>The admin account has already been created. Please <strong>delete this setup.php file</strong> for security.</p>
        <a href="login.php" style="background:#059669;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block">Go to Login</a>
    </div>');
}

$hash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);

Database::execute(
    "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?,?,1)",
    ['Administrator', 'admin', 'admin@wvr.com', $hash, 'admin']
);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WVR Setup</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .box { background:#fff; border-radius:16px; padding:40px; max-width:480px; box-shadow:0 8px 32px rgba(0,0,0,0.1); }
        h2 { color: #4f6ef7; }
        .cred { background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px; margin:16px 0; font-family:monospace; }
        .warn { background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:12px; font-size:13px; margin-top:16px; }
        a.btn { display:inline-block; background:#4f6ef7; color:#fff; padding:12px 24px; border-radius:8px; text-decoration:none; margin-top:16px; font-weight:600; }
    </style>
</head>
<body>
<div class="box">
    <h2>✓ Setup Complete!</h2>
    <p>Admin account has been created. Use these credentials to log in:</p>
    <div class="cred">
        <strong>Username:</strong> admin<br>
        <strong>Password:</strong> Admin@1234
    </div>
    <div class="warn">
        ⚠ <strong>Important:</strong> Change your password immediately after first login, then <strong>delete this setup.php file</strong> from your server for security!
    </div>
    <a href="login.php" class="btn">Go to Login →</a>
</div>
</body>
</html>
