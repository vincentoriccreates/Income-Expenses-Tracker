<?php
// ============================================================
// login.php — Login Page
// ============================================================
session_start();

// Already logged in — redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$error    = '';
$username = '';
$redirect = $_GET['redirect'] ?? 'index.php';
$msg      = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $user = Database::fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1",
            [$username, $username]
        );

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['last_activity'] = time();

            // Update last login timestamp
            Database::execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );

            // Remember me cookie (7 days)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 604800, '/', '', false, true);
                Database::execute(
                    "UPDATE users SET password = password WHERE id = ?", [$user['id']]
                );
            }

            $dest = urldecode($redirect);
            // Safety: only allow local redirects
            if (!$dest || str_starts_with($dest, 'http') || str_starts_with($dest, '//')) {
                $dest = 'index.php';
            }
            header('Location: ' . $dest);
            exit;

        } else {
            $error = 'Invalid username or password. Please try again.';
            // Small delay to slow brute force
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — WVR Income & Expenses Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root { --accent: #4f6ef7; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1d27 0%, #2d3555 50%, #1a2744 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #4f6ef7, #2d3555);
            padding: 36px 32px 28px;
            text-align: center;
            color: white;
        }
        .brand-icon {
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 14px;
            backdrop-filter: blur(4px);
        }
        .login-body { padding: 32px; }
        .form-control {
            border-radius: 10px;
            padding: 11px 16px;
            border-color: #d1d5db;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,110,247,0.12);
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #f8f9fb;
            border-color: #d1d5db;
            color: #6b7280;
        }
        .input-group .form-control { border-radius: 0 10px 10px 0; }
        .btn-login {
            background: linear-gradient(135deg, #4f6ef7, #3b5bd6);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79,110,247,0.4);
        }
        .alert { border-radius: 10px; font-size: 14px; }
        .toggle-pw { cursor: pointer; border-radius: 0 10px 10px 0 !important; }
        .login-footer {
            text-align: center;
            padding: 16px 32px 24px;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #f1f3f5;
        }
        .floating-shapes { position: fixed; width: 100%; height: 100%; pointer-events: none; overflow: hidden; z-index: 0; }
        .shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.06;
            background: white;
        }
        .shape-1 { width: 300px; height: 300px; top: -80px; right: -80px; }
        .shape-2 { width: 200px; height: 200px; bottom: -60px; left: -60px; }
        .shape-3 { width: 150px; height: 150px; top: 50%; left: 10%; }
        .login-wrap { position: relative; z-index: 1; width: 100%; padding: 20px; }
    </style>
</head>
<body>
<div class="floating-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
</div>

<div class="login-wrap d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="brand-icon"><i class="bi bi-building"></i></div>
            <h4 class="fw-bold mb-1">WVR Tracker</h4>
            <p class="mb-0 opacity-75" style="font-size:13px">White Villas Resort — Income & Expenses</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if ($msg === 'timeout'): ?>
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-clock me-1"></i>Your session expired. Please log in again.
            </div>
            <?php endif; ?>

            <?php if ($msg === 'logout'): ?>
            <div class="alert alert-success py-2 small">
                <i class="bi bi-check-circle me-1"></i>You have been logged out successfully.
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control"
                               placeholder="Enter username or email"
                               value="<?= htmlspecialchars($username) ?>"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control"
                               placeholder="Enter password"
                               autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary toggle-pw" id="togglePw" tabindex="-1">
                            <i class="bi bi-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="small text-decoration-none" style="color:var(--accent)">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100 text-white">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
        </div>

        <div class="login-footer">
            &copy; <?= date('Y') ?> White Villas Resort &nbsp;·&nbsp; v1.0.0
        </div>
    </div>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function() {
    const inp  = document.getElementById('passwordInput');
    const icon = document.getElementById('togglePwIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
