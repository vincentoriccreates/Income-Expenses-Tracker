<?php
// ============================================================
// users.php — User Management (Admin Only)
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireAdmin();

$pageTitle = 'User Management';
$errors    = [];

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add user ──────────────────────────────────────────────
    if ($action === 'add') {
        $name     = trim($_POST['name']     ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';
        $role     = $_POST['role']          ?? 'staff';

        if (!$name)     $errors[] = 'Full name is required.';
        if (!$username) $errors[] = 'Username is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        // Check uniqueness
        if (!$errors) {
            $exists = Database::fetch("SELECT id FROM users WHERE username=? OR email=?", [$username, $email]);
            if ($exists) $errors[] = 'Username or email already exists.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::execute(
                "INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)",
                [$name, $username, $email, $hash, $role]
            );
            setFlash('success', "User '$name' created successfully.");
            header('Location: users.php'); exit;
        }
    }

    // ── Edit user ─────────────────────────────────────────────
    elseif ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name']     ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $role     = $_POST['role']          ?? 'staff';
        $active   = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        if (!$id)       $errors[] = 'Invalid user.';
        if (!$name)     $errors[] = 'Full name is required.';
        if (!$username) $errors[] = 'Username is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if ($password && strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($password && $password !== $confirm) $errors[] = 'New passwords do not match.';

        // Prevent removing last admin
        if ($role !== 'admin') {
            $adminCount = (int)Database::fetch("SELECT COUNT(*) AS c FROM users WHERE role='admin' AND is_active=1 AND id!=?", [$id])['c'];
            if ($adminCount < 1) $errors[] = 'Cannot demote — at least one active admin is required.';
        }

        if (!$errors) {
            $exists = Database::fetch("SELECT id FROM users WHERE (username=? OR email=?) AND id!=?", [$username, $email, $id]);
            if ($exists) $errors[] = 'Username or email already taken by another user.';
        }

        if (!$errors) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                Database::execute(
                    "UPDATE users SET name=?, username=?, email=?, password=?, role=?, is_active=? WHERE id=?",
                    [$name, $username, $email, $hash, $role, $active, $id]
                );
            } else {
                Database::execute(
                    "UPDATE users SET name=?, username=?, email=?, role=?, is_active=? WHERE id=?",
                    [$name, $username, $email, $role, $active, $id]
                );
            }
            setFlash('success', "User '$name' updated successfully.");
            header('Location: users.php'); exit;
        }
    }

    // ── Delete user ───────────────────────────────────────────
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $me = currentUser();
        if ($id === (int)$me['id']) {
            setFlash('danger', 'You cannot delete your own account.');
        } else {
            $adminCount = (int)Database::fetch("SELECT COUNT(*) AS c FROM users WHERE role='admin' AND is_active=1 AND id!=?", [$id])['c'];
            if ($adminCount < 1) {
                setFlash('danger', 'Cannot delete — at least one active admin is required.');
            } else {
                $user = Database::fetch("SELECT name FROM users WHERE id=?", [$id]);
                Database::execute("DELETE FROM users WHERE id=?", [$id]);
                setFlash('success', "User '{$user['name']}' deleted.");
            }
        }
        header('Location: users.php'); exit;
    }
}

$users = Database::fetchAll("SELECT * FROM users ORDER BY role ASC, name ASC");
include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-people-fill me-2 text-primary"></i>User Management</h1>
        <p class="text-muted mb-0">Manage system accounts and access roles</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill me-1"></i>Add User
    </button>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <strong>Error:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="table-responsive">
        <table class="table table-hover datatable mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $isMe = $u['id'] === currentUser()['id'];
            ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:34px;height:34px;border-radius:50%;background:<?= $u['role']==='admin'?'#4f6ef7':'#22c55e'?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                <?php if ($isMe): ?><small class="text-primary">You</small><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="font-monospace small"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge" style="background:#4f6ef7"><i class="bi bi-shield-fill-check me-1"></i>Admin</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="bi bi-person-fill me-1"></i>Staff</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($u)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (!$isMe): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Maria Santos" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. maria.santos" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. maria@wvr.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="editUsername" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" id="editRole" class="form-select">
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editActive" value="1">
                                <label class="form-check-label fw-semibold" for="editActive">Account Active</label>
                            </div>
                        </div>
                        <div class="col-12"><hr class="my-1"><small class="text-muted">Leave password fields blank to keep current password</small></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" name="confirm" class="form-control" placeholder="Repeat new password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<JS
<script>
function openEdit(u) {
    document.getElementById('editUserId').value   = u.id;
    document.getElementById('editName').value     = u.name;
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editEmail').value    = u.email;
    document.getElementById('editRole').value     = u.role;
    document.getElementById('editActive').checked = u.is_active == 1;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
JS;
include 'includes/footer.php';
?>
