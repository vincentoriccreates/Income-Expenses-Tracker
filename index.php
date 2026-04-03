<?php
// ============================================================
// index.php — Dashboard (fully filter-aware)
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';

// ── Filter parameters ────────────────────────────────────────
$filterMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$filterYear  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : null;
$filterWeek  = isset($_GET['week'])  && $_GET['week']  !== '' ? (int)$_GET['week']  : null;

// Default: current year only (no month/week) so chart shows all 12 months of this year
if ($filterMonth === null && $filterYear === null && $filterWeek === null) {
    $filterYear = (int)date('Y');
}

// ── KPI totals (respect all filters) ─────────────────────────
$totals = getDashboardTotals($filterMonth, $filterYear, $filterWeek);

// ── Filter label ──────────────────────────────────────────────
if ($filterWeek && $filterYear) {
    $filterLabel = "Week $filterWeek · " . $filterYear;
} elseif ($filterMonth && $filterYear) {
    $filterLabel = date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear));
} elseif ($filterYear) {
    $filterLabel = "Year $filterYear";
} else {
    $filterLabel = 'All Time';
}

// ── Build chart data based on active filter ───────────────────
// Determine chart grouping: by month (if year selected) or by year (all time)
// Also respect month filter (show daily) and week filter (show daily)

$chartTitle  = '';
$chartLabels = [];
$incomeData  = [];
$expenseData = [];

if ($filterWeek && $filterYear) {
    // ── Week selected: show daily breakdown for that week ─────
    $chartTitle = "Daily — Week $filterWeek, $filterYear";

    $expRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %d') AS label, date, SUM(amount) AS total
         FROM (SELECT date,amount FROM petty_expenses WHERE YEAR(date)=? AND week_number=?
               UNION ALL SELECT date,amount FROM hl_expenses WHERE YEAR(date)=? AND week_number=?) e
         GROUP BY date ORDER BY date",
        [$filterYear,$filterWeek,$filterYear,$filterWeek]
    );
    $incRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %d') AS label, date, SUM(amount) AS total
         FROM (SELECT date,amount FROM income_cash        WHERE YEAR(date)=? AND week_number=?
               UNION ALL SELECT date,amount FROM income_card        WHERE YEAR(date)=? AND week_number=?
               UNION ALL SELECT date,amount FROM income_roomcharged WHERE YEAR(date)=? AND week_number=?) i
         GROUP BY date ORDER BY date",
        [$filterYear,$filterWeek,$filterYear,$filterWeek,$filterYear,$filterWeek]
    );

} elseif ($filterMonth && $filterYear) {
    // ── Month selected: show daily breakdown ──────────────────
    $chartTitle = date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear)) . " — Daily";

    $expRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %d') AS label, date, SUM(amount) AS total
         FROM (SELECT date,amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?
               UNION ALL SELECT date,amount FROM hl_expenses WHERE YEAR(date)=? AND MONTH(date)=?) e
         GROUP BY date ORDER BY date",
        [$filterYear,$filterMonth,$filterYear,$filterMonth]
    );
    $incRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %d') AS label, date, SUM(amount) AS total
         FROM (SELECT date,amount FROM income_cash        WHERE YEAR(date)=? AND MONTH(date)=?
               UNION ALL SELECT date,amount FROM income_card        WHERE YEAR(date)=? AND MONTH(date)=?
               UNION ALL SELECT date,amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=?) i
         GROUP BY date ORDER BY date",
        [$filterYear,$filterMonth,$filterYear,$filterMonth,$filterYear,$filterMonth]
    );

} elseif ($filterYear) {
    // ── Year selected: show monthly breakdown for that year ───
    $chartTitle = "Monthly — $filterYear";

    $expRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %Y') AS label, MONTH(date) AS mo, SUM(amount) AS total
         FROM (SELECT date,amount FROM petty_expenses WHERE YEAR(date)=?
               UNION ALL SELECT date,amount FROM hl_expenses WHERE YEAR(date)=?) e
         GROUP BY mo ORDER BY mo",
        [$filterYear,$filterYear]
    );
    $incRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %Y') AS label, MONTH(date) AS mo, SUM(amount) AS total
         FROM (SELECT date,amount FROM income_cash        WHERE YEAR(date)=?
               UNION ALL SELECT date,amount FROM income_card        WHERE YEAR(date)=?
               UNION ALL SELECT date,amount FROM income_roomcharged WHERE YEAR(date)=?) i
         GROUP BY mo ORDER BY mo",
        [$filterYear,$filterYear,$filterYear]
    );

} else {
    // ── All time: show last 12 months ─────────────────────────
    $chartTitle = "Last 12 Months";

    $expRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %Y') AS label, YEAR(date) AS yr, MONTH(date) AS mo, SUM(amount) AS total
         FROM (SELECT date,amount FROM petty_expenses UNION ALL SELECT date,amount FROM hl_expenses) e
         GROUP BY yr,mo ORDER BY yr DESC, mo DESC LIMIT 12"
    );
    $incRows = Database::fetchAll(
        "SELECT DATE_FORMAT(date,'%b %Y') AS label, YEAR(date) AS yr, MONTH(date) AS mo, SUM(amount) AS total
         FROM (SELECT date,amount FROM income_cash UNION ALL SELECT date,amount FROM income_card
               UNION ALL SELECT date,amount FROM income_roomcharged) i
         GROUP BY yr,mo ORDER BY yr DESC, mo DESC LIMIT 12"
    );
    $expRows = array_reverse($expRows);
    $incRows = array_reverse($incRows);
}

// ── Merge labels in chronological order ───────────────────────
$labelMap = [];
foreach ($incRows as $r) $labelMap[$r['label']] = $r['label'];
foreach ($expRows as $r) $labelMap[$r['label']] = $r['label'];

// For date-keyed rows keep insertion order (already sorted by query)
$chartLabels = array_values($labelMap);
$incMap  = array_column($incRows, 'total', 'label');
$expMap  = array_column($expRows, 'total', 'label');
$incomeData  = array_map(fn($l) => round((float)($incMap[$l]  ?? 0), 2), $chartLabels);
$expenseData = array_map(fn($l) => round((float)($expMap[$l] ?? 0), 2), $chartLabels);

// ── Category summary (filter-aware) ──────────────────────────
$catWhere  = [];
$catParams = [];
if ($filterYear)  { $catWhere[] = 'YEAR(date)=?';    $catParams[] = $filterYear;  }
if ($filterMonth) { $catWhere[] = 'MONTH(date)=?';   $catParams[] = $filterMonth; }
if ($filterWeek)  { $catWhere[] = 'week_number=?';   $catParams[] = $filterWeek;  }
$catSQL = $catWhere ? ('WHERE ' . implode(' AND ', $catWhere)) : '';

$cashCats = Database::fetchAll(
    "SELECT category AS name, SUM(amount) AS total FROM income_cash $catSQL GROUP BY category ORDER BY total DESC LIMIT 7",
    $catParams
);

// ── Recent petty expenses (filter-aware) ──────────────────────
$recentPetty = Database::fetchAll(
    "SELECT date, description, amount FROM petty_expenses $catSQL ORDER BY date DESC, id DESC LIMIT 8",
    $catParams
);

// ── Available years/months/weeks for dropdowns ────────────────
$availYears = Database::fetchAll(
    "SELECT DISTINCT YEAR(date) AS yr FROM (
        SELECT date FROM petty_expenses UNION SELECT date FROM hl_expenses
        UNION SELECT date FROM income_cash UNION SELECT date FROM income_card
        UNION SELECT date FROM income_roomcharged
    ) d ORDER BY yr DESC"
);

$availWeeks = Database::fetchAll(
    "SELECT DISTINCT week_number, YEAR(date) AS yr FROM (
        SELECT week_number, date FROM petty_expenses UNION SELECT week_number, date FROM hl_expenses
        UNION SELECT week_number, date FROM income_cash UNION SELECT week_number, date FROM income_card
        UNION SELECT week_number, date FROM income_roomcharged
    ) w " . ($filterYear ? "WHERE YEAR(date)=?" : "") . " ORDER BY yr DESC, week_number DESC LIMIT 60",
    $filterYear ? [$filterYear] : []
);

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Dashboard</h1>
        <p class="text-muted mb-0">White Villas Resort — showing <strong><?= $filterLabel ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="add_income.php"  class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Income</a>
        <a href="add_expense.php" class="btn btn-danger  btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Expense</a>
    </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────── -->
<div class="filter-bar mb-4">
    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap" id="filterForm">
        <div>
            <label class="form-label mb-1 fw-semibold small">Year</label>
            <select name="year" class="form-select form-select-sm" style="width:auto" id="selYear">
                <option value="">All Years</option>
                <?php foreach ($availYears as $y): ?>
                    <option value="<?= $y['yr'] ?>" <?= $filterYear===$y['yr']?'selected':'' ?>><?= $y['yr'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 fw-semibold small">Month</label>
            <select name="month" class="form-select form-select-sm" style="width:auto" id="selMonth">
                <option value="">All Months</option>
                <?php foreach (range(1,12) as $m): ?>
                    <option value="<?= $m ?>" <?= $filterMonth===$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 fw-semibold small">Week #</label>
            <select name="week" class="form-select form-select-sm" style="width:auto" id="selWeek">
                <option value="">All Weeks</option>
                <?php foreach ($availWeeks as $w): ?>
                    <option value="<?= $w['week_number'] ?>" <?= $filterWeek===$w['week_number']?'selected':'' ?>>
                        Wk <?= $w['week_number'] ?> (<?= $w['yr'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2 align-items-end">
            <button class="btn btn-primary btn-sm px-4">Apply</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- ── KPI Row 1 ──────────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon income"><i class="bi bi-arrow-up-circle-fill"></i></div>
            <div>
                <div class="stat-label">Total Income</div>
                <div class="stat-value amount-income"><?= formatCurrency($totals['totalIncome']) ?></div>
                <small class="text-muted">Cash + Card + Rooms</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon expense"><i class="bi bi-arrow-down-circle-fill"></i></div>
            <div>
                <div class="stat-label">Total Expenses</div>
                <div class="stat-value amount-expense"><?= formatCurrency($totals['totalExpenses']) ?></div>
                <small class="text-muted">Petty + H/L</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <?php $bal = $totals['balance']; ?>
            <div class="stat-icon <?= $bal >= 0 ? 'balance' : 'expense' ?>">
                <i class="bi <?= $bal >= 0 ? 'bi-wallet-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
            </div>
            <div>
                <div class="stat-label">Net Balance</div>
                <div class="stat-value <?= $bal >= 0 ? 'balance-positive' : 'balance-negative' ?>">
                    <?= formatCurrency(abs($bal)) ?>
                </div>
                <small class="text-muted"><?= $bal >= 0 ? 'Profit' : 'Deficit' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- ── KPI Row 2: Income breakdown ──────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon cash"><i class="bi bi-cash"></i></div>
            <div>
                <div class="stat-label">Cash Income</div>
                <div class="stat-value fs-5 amount-income"><?= formatCurrency($totals['cash']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon card"><i class="bi bi-credit-card"></i></div>
            <div>
                <div class="stat-label">Card Income</div>
                <div class="stat-value fs-5 amount-income"><?= formatCurrency($totals['card']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon room"><i class="bi bi-door-open"></i></div>
            <div>
                <div class="stat-label">Room Charged</div>
                <div class="stat-value fs-5 amount-income"><?= formatCurrency($totals['room']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold"><?= htmlspecialchars($chartTitle) ?> — Income vs Expenses</span>
                <span class="badge bg-light text-secondary border"><?= count($chartLabels) ?> period<?= count($chartLabels)!==1?'s':'' ?></span>
            </div>
            <div class="data-card-body">
                <?php if (empty($chartLabels)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart fs-1 d-block mb-2 opacity-25"></i>
                        No data found for <strong><?= $filterLabel ?></strong>
                    </div>
                <?php else: ?>
                    <div class="chart-container" style="height:280px">
                        <canvas id="barChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="data-card h-100">
            <div class="data-card-header"><span class="fw-semibold">Income Breakdown — <?= $filterLabel ?></span></div>
            <div class="data-card-body">
                <?php if ($totals['totalIncome'] > 0): ?>
                <div class="chart-container mb-3" style="height:180px">
                    <canvas id="doughnutChart"></canvas>
                </div>
                <?php endif; ?>
                <?php
                $incRows2 = [
                    ['Cash',  $totals['cash'],  '#22c55e'],
                    ['Card',  $totals['card'],  '#7c3aed'],
                    ['Rooms', $totals['room'],  '#0891b2'],
                ];
                foreach ($incRows2 as [$lbl, $val, $col]):
                    $pct = $totals['totalIncome'] > 0 ? round($val/$totals['totalIncome']*100,1) : 0;
                ?>
                <div class="d-flex justify-content-between align-items-center mb-1 small">
                    <span><span class="badge me-1" style="background:<?=$col?>">&nbsp;</span><?=$lbl?></span>
                    <span class="fw-semibold"><?= formatCurrency($val) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                </div>
                <?php endforeach; ?>
                <hr class="my-2">
                <?php
                $expRows2 = [
                    ['Petty', $totals['petty'], '#f97316'],
                    ['H/L',   $totals['hl'],    '#ef4444'],
                ];
                foreach ($expRows2 as [$lbl, $val, $col]):
                    $pct = $totals['totalExpenses'] > 0 ? round($val/$totals['totalExpenses']*100,1) : 0;
                ?>
                <div class="d-flex justify-content-between align-items-center mb-1 small">
                    <span><span class="badge me-1" style="background:<?=$col?>">&nbsp;</span><?=$lbl?> Expenses</span>
                    <span class="fw-semibold"><?= formatCurrency($val) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Transactions & Category Summary ─────────────── -->
<div class="row g-3">
    <div class="col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Recent Petty Expenses — <?= $filterLabel ?></span>
                <a href="expense.php?type=petty" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <?php if (empty($recentPetty)): ?>
                <div class="text-center text-muted py-4 small">No petty expenses for <?= $filterLabel ?></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Description</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentPetty as $r): ?>
                        <tr>
                            <td class="text-muted small"><?= formatDate($r['date']) ?></td>
                            <td><?= htmlspecialchars($r['description']) ?></td>
                            <td class="text-end amount-expense"><?= formatCurrency($r['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="data-card">
            <div class="data-card-header"><span class="fw-semibold">Cash Income by Category — <?= $filterLabel ?></span></div>
            <div class="data-card-body">
                <?php
                $catTotal = array_sum(array_column($cashCats, 'total'));
                $colors   = ['#22c55e','#3b82f6','#f59e0b','#8b5cf6','#0891b2','#ef4444','#14b8a6'];
                if (empty($cashCats)): ?>
                    <p class="text-muted text-center small py-3">No income data for <?= $filterLabel ?></p>
                <?php else:
                foreach ($cashCats as $i => $cat):
                    $pct   = $catTotal > 0 ? round($cat['total']/$catTotal*100,1) : 0;
                    $color = $colors[$i % count($colors)];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small">
                        <span><?= htmlspecialchars($cat['name']) ?></span>
                        <span class="fw-semibold"><?= formatCurrency($cat['total']) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                    </div>
                    <div class="progress category-bar">
                        <div class="progress-bar" style="width:<?=$pct?>%;background:<?=$color?>"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$jsLabels  = json_encode($chartLabels);
$jsIncome  = json_encode($incomeData);
$jsExpense = json_encode($expenseData);
$incCash   = (float)$totals['cash'];
$incCard   = (float)$totals['card'];
$incRoom   = (float)$totals['room'];
$hasChart  = !empty($chartLabels) ? 'true' : 'false';

$extraJS = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function(){
    if ($hasChart) {
        buildBarChart('barChart', $jsLabels, $jsIncome, $jsExpense);
    }
    if ($incCash + $incCard + $incRoom > 0) {
        buildDoughnutChart('doughnutChart',
            ['Cash','Card','Room Charged'],
            [$incCash, $incCard, $incRoom],
            ['#22c55e','#7c3aed','#0891b2']
        );
    }
});
</script>
JS;

include 'includes/footer.php';
?>
