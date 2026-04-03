<?php
// ============================================================
// categories.php — Category Management
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Categories';
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $type  = $_POST['type']  ?? 'income';
        $color = $_POST['color'] ?? '#6c757d';

        if (!$name) $errors[] = 'Category name is required.';
        if (!in_array($type, ['income','expense'])) $errors[] = 'Invalid type.';

        if (!$errors) {
            Database::execute("INSERT INTO categories (name, type, color) VALUES (?,?,?)", [$name, $type, $color]);
            setFlash('success', "Category '$name' added.");
            header('Location: categories.php');
            exit;
        }
    } elseif ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $type  = $_POST['type']  ?? 'income';
        $color = $_POST['color'] ?? '#6c757d';

        if (!$id || !$name) { setFlash('danger','Invalid data.'); header('Location: categories.php'); exit; }
        Database::execute("UPDATE categories SET name=?, type=?, color=? WHERE id=?", [$name, $type, $color, $id]);
        setFlash('success', 'Category updated.');
        header('Location: categories.php');
        exit;
    }
}

$categories = getAllCategories();
include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-tags-fill me-2 text-info"></i>Categories</h1>
        <p class="text-muted mb-0">Manage income and expense categories</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-plus-circle me-1"></i>Add Category
    </button>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Income Categories -->
    <div class="col-md-6">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold"><i class="bi bi-arrow-up-circle text-success me-1"></i>Income Categories</span>
                <span class="badge bg-success-subtle text-success"><?= count(array_filter($categories, fn($c)=>$c['type']==='income')) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Color</th><th>Name</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_filter($categories, fn($c)=>$c['type']==='income') as $cat): ?>
                        <tr>
                            <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($cat['color']) ?>"></span></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                    onclick="openEdit(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'],ENT_QUOTES) ?>', '<?= $cat['type'] ?>', '<?= $cat['color'] ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="delete.php?table=categories&id=<?= $cat['id'] ?>&redirect=categories.php"
                                   class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expense Categories -->
    <div class="col-md-6">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold"><i class="bi bi-arrow-down-circle text-danger me-1"></i>Expense Categories</span>
                <span class="badge bg-danger-subtle text-danger"><?= count(array_filter($categories, fn($c)=>$c['type']==='expense')) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Color</th><th>Name</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_filter($categories, fn($c)=>$c['type']==='expense') as $cat): ?>
                        <tr>
                            <td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?= htmlspecialchars($cat['color']) ?>"></span></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                    onclick="openEdit(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'],ENT_QUOTES) ?>', '<?= $cat['type'] ?>', '<?= $cat['color'] ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="delete.php?table=categories&id=<?= $cat['id'] ?>&redirect=categories.php"
                                   class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Resto Income">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#4f6ef7">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="editType" class="form-select">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="editColor" class="form-control form-control-color">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<JS
<script>
function openEdit(id, name, type, color) {
    document.getElementById('editId').value    = id;
    document.getElementById('editName').value  = name;
    document.getElementById('editType').value  = type;
    document.getElementById('editColor').value = color;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>
JS;
include 'includes/footer.php'; ?>
