<?php
// ============================================================
// edit_income.php — Edit Income Record
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$id   = (int)($_GET['id']   ?? 0);
$type = $_GET['type'] ?? 'cash';
$pageTitle = 'Edit Income';

$tableMap = ['cash'=>'income_cash','card'=>'income_card','room'=>'income_roomcharged'];
$table = $tableMap[$type] ?? 'income_cash';

$record = Database::fetch("SELECT * FROM $table WHERE id = ?", [$id]);
if (!$record) {
    setFlash('danger', 'Record not found.');
    header('Location: income.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date   = trim($_POST['date']   ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $week   = (int)($_POST['week_number'] ?? getWeekNumber($date));
    $month  = trim($_POST['month_display'] ?? getMonth($date));
    $notes  = trim($_POST['notes'] ?? '');

    if (!$date)     $errors[] = 'Date is required.';
    if ($amount < 0) $errors[] = 'Amount must be 0 or greater.';

    if (!$errors) {
        if ($type === 'cash' || $type === 'card') {
            $category = trim($_POST['category'] ?? '');
            if (!$category) $errors[] = 'Category is required.';
            else {
                Database::execute(
                    "UPDATE $table SET date=?, category=?, amount=?, week_number=?, month=?, notes=? WHERE id=?",
                    [$date, $category, $amount, $week, $month, $notes ?: null, $id]
                );
                setFlash('success', 'Income record updated successfully!');
                header('Location: income.php?type=' . $type);
                exit;
            }
        } else {
            $ref = trim($_POST['room_reference'] ?? '');
            Database::execute(
                "UPDATE $table SET date=?, room_reference=?, amount=?, week_number=?, month=?, notes=? WHERE id=?",
                [$date, $ref ?: null, $amount, $week, $month, $notes ?: null, $id]
            );
            setFlash('success', 'Room charged record updated!');
            header('Location: income.php?type=room');
            exit;
        }
    }
} else {
    $_POST = $record;
    $_POST['month_display'] = $record['month'] ?? getMonth($record['date']);
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-pencil me-2 text-primary"></i>Edit Income Record</h1>
    <p class="text-muted mb-0"><a href="income.php?type=<?= $type ?>">Income</a> / Edit #<?= $id ?></p>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="data-card">
    <div class="data-card-header">
        <span class="fw-semibold">Edit Entry — <?= ucfirst($type) ?></span>
        <span class="badge bg-secondary">ID #<?= $id ?></span>
    </div>
    <div class="data-card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars($_POST['date'] ?? $record['date']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Week #</label>
                    <input type="number" name="week_number" id="week_number" class="form-control" value="<?= htmlspecialchars($_POST['week_number'] ?? $record['week_number']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Month</label>
                    <input type="text" name="month_display" id="month_display" class="form-control" value="<?= htmlspecialchars($_POST['month_display'] ?? $record['month']) ?>" readonly>
                </div>

                <?php if ($type === 'cash' || $type === 'card'): ?>
                <div class="col-12">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="">— Select Category —</option>
                        <?php $selCat = $_POST['category'] ?? $record['category'] ?? '';
                        foreach (['Resto Income','Drinks Income','Rooms Income','Motor Income','Other Income','MotorBike Income'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $selCat===$cat?'selected':'' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-12">
                    <label class="form-label">Room Reference</label>
                    <input type="text" name="room_reference" class="form-control" placeholder="e.g. B3, B4, P2"
                           value="<?= htmlspecialchars($_POST['room_reference'] ?? $record['room_reference'] ?? '') ?>">
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0"
                               value="<?= htmlspecialchars($_POST['amount'] ?? $record['amount']) ?>" required>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? $record['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle me-1"></i>Update Record
                </button>
                <a href="income.php?type=<?= $type ?>" class="btn btn-outline-secondary">Cancel</a>
                <a href="delete.php?table=income_<?= $type ?>&id=<?= $id ?>&redirect=income.php?type=<?= $type ?>"
                   class="btn btn-outline-danger ms-auto btn-delete">
                    <i class="bi bi-trash me-1"></i>Delete
                </a>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
