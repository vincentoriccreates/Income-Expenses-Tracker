<?php
// ============================================================
// income.php — All Income Records
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Income Records';
$type   = $_GET['type']     ?? 'all';
$search = trim($_GET['search'] ?? '');
$month  = $_GET['filter_month'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// ── Build query based on type ─────────────────────────────────
function buildIncomeQuery(string $type, string $search, string $month, string $dateFrom, string $dateTo): array {
    $params = [];
    $conditions = [];

    if ($month) {
        $conditions[] = "DATE_FORMAT(date,'%Y-%m') = ?";
        $params[] = $month;
    }
    if ($dateFrom) { $conditions[] = "date >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $conditions[] = "date <= ?"; $params[] = $dateTo; }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    if ($type === 'cash') {
        if ($search) { $where .= ($where ? ' AND' : 'WHERE') . " (category LIKE ? OR notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        return [
            "SELECT id,'cash' AS src, date, category AS description, amount, week_number, month FROM income_cash $where ORDER BY date DESC, id DESC",
            $params, 'Paid by Cash'
        ];
    } elseif ($type === 'card') {
        if ($search) { $where .= ($where ? ' AND' : 'WHERE') . " (category LIKE ? OR notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        return [
            "SELECT id,'card' AS src, date, category AS description, amount, week_number, month FROM income_card $where ORDER BY date DESC, id DESC",
            $params, 'Paid by Card'
        ];
    } elseif ($type === 'room') {
        if ($search) { $where .= ($where ? ' AND' : 'WHERE') . " (room_reference LIKE ? OR notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        return [
            "SELECT id,'room' AS src, date, COALESCE(room_reference,'—') AS description, amount, week_number, month FROM income_roomcharged $where ORDER BY date DESC, id DESC",
            $params, 'Room Charged'
        ];
    } else {
        // All — union query
        $cashParams = $cardParams = $roomParams = $params;
        if ($search) {
            $cashParams[] = "%$search%"; $cashParams[] = "%$search%";
            $cardParams[] = "%$search%"; $cardParams[] = "%$search%";
            $roomParams[] = "%$search%"; $roomParams[] = "%$search%";
            $swhere1 = $where ? "$where AND (category LIKE ? OR notes LIKE ?)" : "WHERE category LIKE ? OR notes LIKE ?";
            $swhere2 = $where ? "$where AND (room_reference LIKE ? OR notes LIKE ?)" : "WHERE room_reference LIKE ? OR notes LIKE ?";
        } else {
            $swhere1 = $where; $swhere2 = $where;
            $cashParams = $cardParams = $roomParams = $params;
        }
        // Flatten params for union
        $allParams = array_merge($cashParams, $cardParams, $roomParams);
        $sql = "(SELECT id,'cash' AS src, date, category AS description, amount, week_number, month FROM income_cash $swhere1)
                UNION ALL
                (SELECT id,'card' AS src, date, category AS description, amount, week_number, month FROM income_card $swhere1)
                UNION ALL
                (SELECT id,'room' AS src, date, COALESCE(room_reference,'—') AS description, amount, week_number, month FROM income_roomcharged $swhere2)
                ORDER BY date DESC, id DESC";
        return [$sql, $allParams, 'All Income'];
    }
}

[$sql, $params, $typeLabel] = buildIncomeQuery($type, $search, $month, $dateFrom, $dateTo);
$records = Database::fetchAll($sql, $params);
$total   = array_sum(array_column($records, 'amount'));
$months  = getAvailableMonths();

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-cash-stack me-2 text-success"></i><?= $typeLabel ?></h1>
        <p class="text-muted mb-0"><?= count($records) ?> records &bull; Total: <strong class="amount-income"><?= formatCurrency($total) ?></strong></p>
    </div>
    <a href="add_income.php?type=<?= htmlspecialchars($type) ?>" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i>Add Income
    </a>
</div>

<!-- Filter Bar -->
<div class="filter-bar mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <div class="col-md-3">
            <label class="form-label mb-1">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Description..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1">Month</label>
            <select name="filter_month" class="form-select form-select-sm">
                <option value="">All Months</option>
                <?php foreach ($months as $m): ?>
                    <option value="<?= $m['ym'] ?>" <?= $month === $m['ym'] ? 'selected' : '' ?>><?= $m['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm">Filter</button>
            <a href="income.php?type=<?= $type ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            <a href="exports/export.php?table=income_<?= $type ?>&<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-sm ms-auto">
                <i class="bi bi-download"></i> CSV
            </a>
        </div>
    </form>
</div>

<!-- Type Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php foreach (['all'=>'All Income','cash'=>'Cash','card'=>'Card','room'=>'Room Charged'] as $k=>$v): ?>
    <li class="nav-item">
        <a class="nav-link <?= $type===$k?'active':'' ?>" href="income.php?type=<?= $k ?>">
            <?= $v ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="data-card">
    <div class="table-responsive">
        <table class="table table-hover datatable mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description / Category</th>
                    <th>Week</th>
                    <th>Month</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= formatDate($r['date']) ?></td>
                    <td>
                        <?php if ($r['src']==='cash'): ?>
                            <span class="badge bg-warning text-dark">Cash</span>
                        <?php elseif ($r['src']==='card'): ?>
                            <span class="badge bg-purple" style="background:#7c3aed">Card</span>
                        <?php else: ?>
                            <span class="badge bg-info text-dark">Room</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['description']) ?></td>
                    <td class="text-muted">Wk <?= $r['week_number'] ?></td>
                    <td class="text-muted"><?= $r['month'] ?></td>
                    <td class="text-end amount-income"><?= formatCurrency($r['amount']) ?></td>
                    <td class="text-center">
                        <a href="edit_income.php?id=<?= $r['id'] ?>&type=<?= $r['src'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="delete.php?table=income_<?= $r['src'] ?>&id=<?= $r['id'] ?>&redirect=income.php?type=<?= $type ?>"
                           class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="5" class="text-end">Grand Total</td>
                    <td class="text-end amount-income"><?= formatCurrency($total) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
