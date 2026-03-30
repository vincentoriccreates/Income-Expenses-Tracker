<?php
// ============================================================
// add_income.php — Add Income (all 3 types in one form)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Add Income';
$errors  = [];
$success = [];

// Default values
$def = [
    'date'           => date('Y-m-d'),
    'week_number'    => (int)date('W'),
    'month_display'  => date('F'),
    // Cash
    'cash_category'  => '', 'cash_amount' => '', 'cash_notes' => '',
    // Card
    'card_category'  => '', 'card_amount' => '', 'card_notes' => '',
    // Room
    'room_reference' => '', 'room_amount' => '', 'room_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date  = trim($_POST['date']          ?? '');
    $week  = (int)($_POST['week_number']  ?? getWeekNumber($date));
    $month = trim($_POST['month_display'] ?? getMonth($date));

    if (!$date) {
        $errors[] = 'Date is required.';
    }

    // Preserve posted values for re-display on error
    $def = array_merge($def, $_POST, ['date' => $date, 'week_number' => $week, 'month_display' => $month]);

    // ── Cash ──────────────────────────────────────────────────
    $cashAmt = trim($_POST['cash_amount'] ?? '');
    if ($cashAmt !== '') {
        $cashAmt = (float)$cashAmt;
        $cashCat = trim($_POST['cash_category'] ?? '');
        if (!$cashCat) {
            $errors[] = 'Cash Income: Category is required.';
        } elseif ($cashAmt < 0) {
            $errors[] = 'Cash Income: Amount cannot be negative.';
        }
    }

    // ── Card ──────────────────────────────────────────────────
    $cardAmt = trim($_POST['card_amount'] ?? '');
    if ($cardAmt !== '') {
        $cardAmt = (float)$cardAmt;
        $cardCat = trim($_POST['card_category'] ?? '');
        if (!$cardCat) {
            $errors[] = 'Card Income: Category is required.';
        } elseif ($cardAmt < 0) {
            $errors[] = 'Card Income: Amount cannot be negative.';
        }
    }

    // ── Room ──────────────────────────────────────────────────
    $roomAmt = trim($_POST['room_amount'] ?? '');
    if ($roomAmt !== '') {
        $roomAmt = (float)$roomAmt;
        if ($roomAmt < 0) {
            $errors[] = 'Room Charged: Amount cannot be negative.';
        }
    }

    // At least one entry required
    $hasCash = $cashAmt !== '' && $cashAmt !== false;
    $hasCard = $cardAmt !== '' && $cardAmt !== false;
    $hasRoom = $roomAmt !== '' && $roomAmt !== false;

    if (!$hasCash && !$hasCard && !$hasRoom) {
        $errors[] = 'Please fill in at least one income section (Cash, Card, or Room Charged).';
    }

    if (!$errors && $date) {
        $inserted = 0;

        if ($hasCash) {
            Database::execute(
                "INSERT INTO income_cash (date, category, amount, week_number, month, notes) VALUES (?,?,?,?,?,?)",
                [$date, $cashCat, $cashAmt, $week, $month, trim($_POST['cash_notes'] ?? '') ?: null]
            );
            $success[] = "Cash income (₱" . number_format($cashAmt,2) . ") saved.";
            $inserted++;
        }
        if ($hasCard) {
            Database::execute(
                "INSERT INTO income_card (date, category, amount, week_number, month, notes) VALUES (?,?,?,?,?,?)",
                [$date, $cardCat, $cardAmt, $week, $month, trim($_POST['card_notes'] ?? '') ?: null]
            );
            $success[] = "Card income (₱" . number_format($cardAmt,2) . ") saved.";
            $inserted++;
        }
        if ($hasRoom) {
            $roomRef = trim($_POST['room_reference'] ?? '') ?: null;
            Database::execute(
                "INSERT INTO income_roomcharged (date, room_reference, amount, week_number, month, notes) VALUES (?,?,?,?,?,?)",
                [$date, $roomRef, $roomAmt, $week, $month, trim($_POST['room_notes'] ?? '') ?: null]
            );
            $success[] = "Room charged income (₱" . number_format($roomAmt,2) . ") saved.";
            $inserted++;
        }

        setFlash('success', implode(' | ', $success));
        header('Location: income.php');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-plus-circle me-2 text-success"></i>Add Income</h1>
    <p class="text-muted mb-0"><a href="income.php">Income</a> / Add New Entry — fill any or all sections, then submit once</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" id="incomeForm">
    <!-- ── Date / Week row ────────────────────────────────── -->
    <div class="data-card mb-4">
        <div class="data-card-header" style="background:#f0f9f4">
            <span class="fw-semibold"><i class="bi bi-calendar3 me-2 text-success"></i>Entry Date</span>
            <span class="text-muted small">This date applies to all sections below</span>
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

        <!-- ── CASH INCOME ──────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="data-card h-100 border-success" style="border-top:3px solid #22c55e">
                <div class="data-card-header" style="background:#f0fdf4">
                    <span class="fw-semibold text-success"><i class="bi bi-cash me-2"></i>Paid by Cash</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Optional</span>
                </div>
                <div class="data-card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="cash_category" class="form-select" id="cashCategory">
                            <option value="">— Select Category —</option>
                            <?php foreach (['Resto Income','Drinks Income','Rooms Income','Motor Income','MotorBike Income','Other Income'] as $cat): ?>
                                <option value="<?=$cat?>" <?= ($def['cash_category']===$cat)?'selected':'' ?>><?=$cat?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success-subtle text-success border-success-subtle">₱</span>
                            <input type="number" name="cash_amount" id="cashAmount" class="form-control"
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?= htmlspecialchars($def['cash_amount']) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="cash_notes" class="form-control" rows="2"
                                  placeholder="Optional..."><?= htmlspecialchars($def['cash_notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── CARD INCOME ──────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="data-card h-100" style="border-top:3px solid #7c3aed">
                <div class="data-card-header" style="background:#f5f3ff">
                    <span class="fw-semibold" style="color:#7c3aed"><i class="bi bi-credit-card me-2"></i>Paid by Card</span>
                    <span class="badge" style="background:#ede9fe;color:#7c3aed;border:1px solid #c4b5fd">Optional</span>
                </div>
                <div class="data-card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="card_category" class="form-select" id="cardCategory">
                            <option value="">— Select Category —</option>
                            <?php foreach (['Resto Income','Drinks Income','Rooms Income','Motor Income','MotorBike Income','Other Income'] as $cat): ?>
                                <option value="<?=$cat?>" <?= ($def['card_category']===$cat)?'selected':'' ?>><?=$cat?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#ede9fe;color:#7c3aed;border-color:#c4b5fd">₱</span>
                            <input type="number" name="card_amount" id="cardAmount" class="form-control"
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?= htmlspecialchars($def['card_amount']) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="card_notes" class="form-control" rows="2"
                                  placeholder="Optional..."><?= htmlspecialchars($def['card_notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── ROOM CHARGED ─────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="data-card h-100" style="border-top:3px solid #0891b2">
                <div class="data-card-header" style="background:#ecfeff">
                    <span class="fw-semibold" style="color:#0891b2"><i class="bi bi-door-open me-2"></i>Room Charged</span>
                    <span class="badge" style="background:#cffafe;color:#0891b2;border:1px solid #a5f3fc">Optional</span>
                </div>
                <div class="data-card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Room Reference</label>
                        <input type="text" name="room_reference" class="form-control"
                               placeholder="e.g. B3, B4, P2"
                               value="<?= htmlspecialchars($def['room_reference']) ?>">
                        <div class="form-text">Room numbers or codes (optional)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#cffafe;color:#0891b2;border-color:#a5f3fc">₱</span>
                            <input type="number" name="room_amount" id="roomAmount" class="form-control"
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?= htmlspecialchars($def['room_amount']) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="room_notes" class="form-control" rows="2"
                                  placeholder="Optional..."><?= htmlspecialchars($def['room_notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Live Total Preview ────────────────────────────────── -->
    <div class="data-card mb-4" style="border:2px solid #e9ecef">
        <div class="data-card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex gap-4 flex-wrap">
                    <div class="text-center">
                        <div class="small text-muted">Cash</div>
                        <div class="fw-bold amount-income" id="previewCash">₱0.00</div>
                    </div>
                    <div class="text-center text-muted fs-4">+</div>
                    <div class="text-center">
                        <div class="small text-muted">Card</div>
                        <div class="fw-bold amount-income" id="previewCard">₱0.00</div>
                    </div>
                    <div class="text-center text-muted fs-4">+</div>
                    <div class="text-center">
                        <div class="small text-muted">Room</div>
                        <div class="fw-bold amount-income" id="previewRoom">₱0.00</div>
                    </div>
                    <div class="text-center text-muted fs-4">=</div>
                    <div class="text-center">
                        <div class="small text-muted fw-semibold">Total This Entry</div>
                        <div class="fw-bold fs-5 amount-income" id="previewTotal">₱0.00</div>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="bi bi-check-circle me-1"></i>Save All Income
                    </button>
                    <a href="income.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$extraJS = <<<JS
<script>
function fmt(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function updatePreview() {
    const c = parseFloat(document.getElementById('cashAmount').value) || 0;
    const k = parseFloat(document.getElementById('cardAmount').value) || 0;
    const r = parseFloat(document.getElementById('roomAmount').value) || 0;
    document.getElementById('previewCash').textContent  = fmt(c);
    document.getElementById('previewCard').textContent  = fmt(k);
    document.getElementById('previewRoom').textContent  = fmt(r);
    document.getElementById('previewTotal').textContent = fmt(c + k + r);
}
document.getElementById('cashAmount').addEventListener('input', updatePreview);
document.getElementById('cardAmount').addEventListener('input', updatePreview);
document.getElementById('roomAmount').addEventListener('input', updatePreview);
updatePreview();
</script>
JS;

include 'includes/footer.php';
?>
