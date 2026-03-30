<?php
// ============================================================
// add_expense.php — Add Expense (Petty + H/L in one form)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Add Expense';
$errors  = [];

// Default values
$def = [
    'date'          => date('Y-m-d'),
    'week_number'   => (int)date('W'),
    'month_display' => date('F'),
    'petty_rows'    => [['description'=>'','amount'=>'','notes'=>'']],
    'hl_rows'       => [['description'=>'','amount'=>'','notes'=>'']],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = trim($_POST['date']          ?? '');
    $week  = (int)($_POST['week_number']  ?? getWeekNumber($date));
    $month = trim($_POST['month_display'] ?? getMonth($date));

    if (!$date) $errors[] = 'Date is required.';

    // Collect petty rows
    $pettyDescs   = $_POST['petty_description'] ?? [];
    $pettyAmounts = $_POST['petty_amount']       ?? [];
    $pettyNotes   = $_POST['petty_notes']        ?? [];

    // Collect HL rows
    $hlDescs   = $_POST['hl_description'] ?? [];
    $hlAmounts = $_POST['hl_amount']       ?? [];
    $hlNotes   = $_POST['hl_notes']        ?? [];

    // Filter valid petty rows (non-empty description)
    $validPetty = [];
    foreach ($pettyDescs as $i => $desc) {
        $desc = trim($desc);
        $amt  = trim($pettyAmounts[$i] ?? '');
        if ($desc !== '') {
            if ($amt === '') $errors[] = "Petty row " . ($i+1) . ": amount is required.";
            elseif ((float)$amt < 0) $errors[] = "Petty row " . ($i+1) . ": amount cannot be negative.";
            else $validPetty[] = ['description'=>$desc,'amount'=>(float)$amt,'notes'=>trim($pettyNotes[$i]??'')];
        }
    }

    // Filter valid HL rows
    $validHL = [];
    foreach ($hlDescs as $i => $desc) {
        $desc = trim($desc);
        $amt  = trim($hlAmounts[$i] ?? '');
        if ($desc !== '') {
            if ($amt === '') $errors[] = "H/L row " . ($i+1) . ": amount is required.";
            elseif ((float)$amt < 0) $errors[] = "H/L row " . ($i+1) . ": amount cannot be negative.";
            else $validHL[] = ['description'=>$desc,'amount'=>(float)$amt,'notes'=>trim($hlNotes[$i]??'')];
        }
    }

    if (!$validPetty && !$validHL) {
        $errors[] = 'Please enter at least one expense (Petty or H/L).';
    }

    // Re-populate form values for re-display on error
    $def['date']          = $date;
    $def['week_number']   = $week;
    $def['month_display'] = $month;
    $def['petty_rows']    = [];
    foreach ($pettyDescs as $i => $d) $def['petty_rows'][] = ['description'=>$d,'amount'=>$pettyAmounts[$i]??'','notes'=>$pettyNotes[$i]??''];
    $def['hl_rows']       = [];
    foreach ($hlDescs    as $i => $d) $def['hl_rows'][]    = ['description'=>$d,'amount'=>$hlAmounts[$i]??'','notes'=>$hlNotes[$i]??''];

    if (!$errors) {
        $pettyCount = 0; $hlCount = 0;

        foreach ($validPetty as $row) {
            Database::execute(
                "INSERT INTO petty_expenses (date, description, amount, week_number, month, notes) VALUES (?,?,?,?,?,?)",
                [$date, $row['description'], $row['amount'], $week, $month, $row['notes'] ?: null]
            );
            $pettyCount++;
        }
        foreach ($validHL as $row) {
            Database::execute(
                "INSERT INTO hl_expenses (date, description, amount, week_number, month, notes) VALUES (?,?,?,?,?,?)",
                [$date, $row['description'], $row['amount'], $week, $month, $row['notes'] ?: null]
            );
            $hlCount++;
        }

        $msg = [];
        if ($pettyCount) $msg[] = "$pettyCount petty expense(s)";
        if ($hlCount)    $msg[] = "$hlCount H/L expense(s)";
        setFlash('success', 'Saved: ' . implode(' and ', $msg) . '.');
        header('Location: expense.php');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-plus-circle me-2 text-danger"></i>Add Expense</h1>
    <p class="text-muted mb-0"><a href="expense.php">Expenses</a> / Add New Entry — fill Petty, H/L, or both, then submit once</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" id="expenseForm">

    <!-- ── Date row ────────────────────────────────────────── -->
    <div class="data-card mb-4">
        <div class="data-card-header" style="background:#fff5f5">
            <span class="fw-semibold"><i class="bi bi-calendar3 me-2 text-danger"></i>Entry Date</span>
            <span class="text-muted small">Applies to all entries below</span>
        </div>
        <div class="data-card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" id="date" class="form-control"
                           value="<?= htmlspecialchars($def['date']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Week #</label>
                    <input type="number" name="week_number" id="week_number" class="form-control"
                           value="<?= htmlspecialchars($def['week_number']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Month</label>
                    <input type="text" name="month_display" id="month_display" class="form-control bg-light"
                           value="<?= htmlspecialchars($def['month_display']) ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <!-- ── PETTY EXPENSES ─────────────────────────────── -->
        <div class="col-lg-6">
            <div class="data-card h-100" style="border-top:3px solid #f97316">
                <div class="data-card-header" style="background:#fff7ed">
                    <span class="fw-semibold" style="color:#c2410c"><i class="bi bi-wallet2 me-2"></i>Petty Expenses</span>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="addRow('petty')">
                        <i class="bi bi-plus"></i> Add Row
                    </button>
                </div>
                <div class="data-card-body">
                    <div id="pettyRows">
                    <?php foreach ($def['petty_rows'] as $idx => $row): ?>
                        <div class="petty-row mb-3 p-3 border rounded bg-light position-relative">
                            <?php if ($idx > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 py-0 px-1 remove-row">
                                <i class="bi bi-x"></i>
                            </button>
                            <?php endif; ?>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">Description</label>
                                <input type="text" name="petty_description[]" class="form-control form-control-sm"
                                       placeholder="e.g. GASOLINE, Guard Salary..."
                                       value="<?= htmlspecialchars($row['description']) ?>">
                            </div>
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-semibold mb-1">Amount (₱)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#fed7aa;color:#c2410c;border-color:#fdba74">₱</span>
                                        <input type="number" name="petty_amount[]" class="form-control petty-amount"
                                               step="0.01" min="0" placeholder="0.00"
                                               value="<?= htmlspecialchars($row['amount']) ?>">
                                    </div>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-semibold mb-1">Notes</label>
                                    <input type="text" name="petty_notes[]" class="form-control form-control-sm"
                                           placeholder="Optional"
                                           value="<?= htmlspecialchars($row['notes']) ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="border-top pt-2 mt-1 d-flex justify-content-between align-items-center">
                        <small class="text-muted">Petty Total</small>
                        <strong class="amount-expense" id="pettyTotal">₱0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── H/L EXPENSES ───────────────────────────────── -->
        <div class="col-lg-6">
            <div class="data-card h-100" style="border-top:3px solid #ef4444">
                <div class="data-card-header" style="background:#fef2f2">
                    <span class="fw-semibold text-danger"><i class="bi bi-bank me-2"></i>H/L Expenses</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="addRow('hl')">
                        <i class="bi bi-plus"></i> Add Row
                    </button>
                </div>
                <div class="data-card-body">
                    <div id="hlRows">
                    <?php foreach ($def['hl_rows'] as $idx => $row): ?>
                        <div class="hl-row mb-3 p-3 border rounded bg-light position-relative">
                            <?php if ($idx > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 py-0 px-1 remove-row">
                                <i class="bi bi-x"></i>
                            </button>
                            <?php endif; ?>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">Description</label>
                                <input type="text" name="hl_description[]" class="form-control form-control-sm"
                                       placeholder="e.g. Resto Expenses, Drinks Expenses..."
                                       value="<?= htmlspecialchars($row['description']) ?>">
                            </div>
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label small fw-semibold mb-1">Amount (₱)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-danger-subtle text-danger border-danger-subtle">₱</span>
                                        <input type="number" name="hl_amount[]" class="form-control hl-amount"
                                               step="0.01" min="0" placeholder="0.00"
                                               value="<?= htmlspecialchars($row['amount']) ?>">
                                    </div>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small fw-semibold mb-1">Notes</label>
                                    <input type="text" name="hl_notes[]" class="form-control form-control-sm"
                                           placeholder="Optional"
                                           value="<?= htmlspecialchars($row['notes']) ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="border-top pt-2 mt-1 d-flex justify-content-between align-items-center">
                        <small class="text-muted">H/L Total</small>
                        <strong class="amount-expense" id="hlTotal">₱0.00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Summary bar + Submit ─────────────────────────────── -->
    <div class="data-card mb-4" style="border:2px solid #fee2e2">
        <div class="data-card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex gap-4 flex-wrap align-items-center">
                    <div class="text-center">
                        <div class="small text-muted">Petty</div>
                        <div class="fw-bold amount-expense" id="summPetty">₱0.00</div>
                    </div>
                    <div class="text-muted fs-5">+</div>
                    <div class="text-center">
                        <div class="small text-muted">H/L</div>
                        <div class="fw-bold amount-expense" id="summHL">₱0.00</div>
                    </div>
                    <div class="text-muted fs-5">=</div>
                    <div class="text-center">
                        <div class="small text-muted fw-semibold">Grand Total</div>
                        <div class="fw-bold fs-5 amount-expense" id="summTotal">₱0.00</div>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-check-circle me-1"></i>Save All Expenses
                    </button>
                    <a href="expense.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extraJS = <<<'JS'
<script>
function fmt(n) {
    return '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function recalc() {
    let petty = 0, hl = 0;
    document.querySelectorAll('.petty-amount').forEach(i => petty += parseFloat(i.value)||0);
    document.querySelectorAll('.hl-amount').forEach(i => hl += parseFloat(i.value)||0);
    document.getElementById('pettyTotal').textContent = fmt(petty);
    document.getElementById('hlTotal').textContent    = fmt(hl);
    document.getElementById('summPetty').textContent  = fmt(petty);
    document.getElementById('summHL').textContent     = fmt(hl);
    document.getElementById('summTotal').textContent  = fmt(petty + hl);
}

function buildRow(prefix) {
    const div = document.createElement('div');
    div.className = prefix + '-row mb-3 p-3 border rounded bg-light position-relative';
    const accentCash = prefix === 'petty'
        ? 'style="background:#fed7aa;color:#c2410c;border-color:#fdba74"'
        : 'class="input-group-text bg-danger-subtle text-danger border-danger-subtle"';
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 py-0 px-1 remove-row">
            <i class="bi bi-x"></i>
        </button>
        <div class="mb-2">
            <label class="form-label small fw-semibold mb-1">Description</label>
            <input type="text" name="${prefix}_description[]" class="form-control form-control-sm" placeholder="Description...">
        </div>
        <div class="row g-2">
            <div class="col-7">
                <label class="form-label small fw-semibold mb-1">Amount (₱)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" ${accentCash}>₱</span>
                    <input type="number" name="${prefix}_amount[]" class="form-control ${prefix}-amount" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div class="col-5">
                <label class="form-label small fw-semibold mb-1">Notes</label>
                <input type="text" name="${prefix}_notes[]" class="form-control form-control-sm" placeholder="Optional">
            </div>
        </div>`;
    div.querySelector(`.${prefix}-amount`).addEventListener('input', recalc);
    div.querySelector('.remove-row').addEventListener('click', function() {
        div.remove(); recalc();
    });
    return div;
}

function addRow(prefix) {
    document.getElementById(prefix + 'Rows').appendChild(buildRow(prefix));
}

// Wire up existing rows
document.querySelectorAll('.petty-amount, .hl-amount').forEach(i => i.addEventListener('input', recalc));
document.querySelectorAll('.remove-row').forEach(btn => {
    btn.addEventListener('click', function() { this.closest('[class*="-row"]').remove(); recalc(); });
});

recalc();
</script>
JS;

include 'includes/footer.php';
?>
