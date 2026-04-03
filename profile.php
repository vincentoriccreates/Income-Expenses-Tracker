<?php
// ============================================================
// profile.php — My Profile & Change Password
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'My Profile';
$me        = currentUser();
$errors    = [];
$tabActive = 'profile';

// Load full user record
$user = Database::fetch("SELECT * FROM users WHERE id = ?", [$me['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update profile ────────────────────────────────────────
    if ($action === 'profile') {
        $tabActive = 'profile';
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$name)  $errors[] = 'Full name is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

        if (!$errors) {
            $exists = Database::fetch("SELECT id FROM users WHERE email=? AND id!=?", [$email, $me['id']]);
            if ($exists) $errors[] = 'That email is already used by another account.';
        }

        if (!$errors) {
            Database::execute("UPDATE users SET name=?, email=? WHERE id=?", [$name, $email, $me['id']]);
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            setFlash('success', 'Profile updated successfully.');
            header('Location: profile.php'); exit;
        }
    }

    // ── Change password ───────────────────────────────────────
    elseif ($action === 'password') {
        $tabActive   = 'password';
        $current     = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password']     ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($newPass) < 8)   $errors[] = 'New password must be at least 8 characters.';
        if ($newPass !== $confirm)  $errors[] = 'New passwords do not match.';
        if ($current === $newPass)  $errors[] = 'New password must be different from current password.';

        if (!$errors) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute("UPDATE users SET password=? WHERE id=?", [$hash, $me['id']]);
            setFlash('success', 'Password changed successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h1>
    <p class="text-muted mb-0">Manage your account details and password</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-7">

    <!-- Avatar card -->
    <div class="data-card mb-4">
        <div class="data-card-body d-flex align-items-center gap-4 py-4">
            <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#4f6ef7,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($user['name'],0,1)) ?>
            </div>
            <div>
                <div class="fw-bold fs-5"><?= htmlspecialchars($user['name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                <div class="mt-1">
                    <span class="badge <?= $user['role']==='admin'?'bg-primary':'bg-success' ?>">
                        <i class="bi bi-<?= $user['role']==='admin'?'shield-fill-check':'person-fill' ?> me-1"></i>
                        <?= ucfirst($user['role']) ?>
                    </span>
                    <span class="badge bg-light text-muted border ms-1 small">
                        Last login: <?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'N/A' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tabActive==='profile'?'active':'' ?>" href="#" onclick="showTab('profile');return false">
                <i class="bi bi-person me-1"></i>Profile Info
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tabActive==='password'?'active':'' ?>" href="#" onclick="showTab('password');return false">
                <i class="bi bi-lock me-1"></i>Change Password
            </a>
        </li>
    </ul>

    <!-- Profile form -->
    <div id="tab-profile" class="data-card <?= $tabActive==='password'?'d-none':'' ?>">
        <div class="data-card-header"><span class="fw-semibold">Profile Information</span></div>
        <div class="data-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="profile">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                    <div class="form-text">Username cannot be changed. Contact an admin if needed.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle me-1"></i>Save Profile
                </button>
            </form>
        </div>
    </div>

    <!-- Password form -->
    <div id="tab-password" class="data-card <?= $tabActive==='profile'?'d-none':'' ?>">
        <div class="data-card-header"><span class="fw-semibold">Change Password</span></div>
        <div class="data-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control" required autocomplete="new-password" placeholder="Min. 8 characters">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-lock me-1"></i>Update Password
                </button>
            </form>
        </div>
    </div>

</div>
</div>

<?php
$extraJS = <<<JS
<script>
function showTab(tab) {
    document.getElementById('tab-profile').classList.toggle('d-none', tab !== 'profile');
    document.getElementById('tab-password').classList.toggle('d-none', tab !== 'password');
    document.querySelectorAll('.nav-link').forEach((el,i) => {
        el.classList.toggle('active', (i===0&&tab==='profile')||(i===1&&tab==='password'));
    });
}
</script>
JS;
include 'includes/footer.php';
?>
