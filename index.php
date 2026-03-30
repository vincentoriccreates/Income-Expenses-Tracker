<?php
// ============================================================
// index.php — Dashboard (FIXED: accurate totals + filters)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Dashboard';

// ── Filter parameters ────────────────────────────────────────
$filterMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$filterYear  = isset($_GET['year'])  && $_GET['year']  !== '' ? (int)$_GET['year']  : null;
$filterWeek  = isset($_GET['week'])  && $_GET['week']  !== '' ? (int)$_GET['week']  : null;

// Default: current month & year if nothing selected
if ($filterMonth === null && $filterYear === null && $filterWeek === null) {
    $filterMonth = (int)date('n');
    $filterYear  = (int)date('Y');
}

$totals = getDashboardTotals($filterMonth, $filterYear, $filterWeek);

// ── Filter label for display ─────────────────────────────────
if ($filterWeek && $filterYear) {
    $filterLabel = "Week $filterWeek, $filterYear";
} elseif ($filterMonth && $filterYear) {
    $filterLabel = date('F Y', mktime(0,0,0,$filterMonth,1,$filterYear));
} elseif ($filterYear) {
    $filterLabel = "Year $filterYear";
} else {
    $filterLabel = 'All Time';
}

// ── Chart: last 6 months income vs expenses ──────────────────
$expChart = Database::fetchAll(
    "SELECT DATE_FORMAT(date,'%b %Y') AS label, YEAR(date) AS yr, MONTH(date) AS mo, SUM(amount) AS total
     FROM (SELECT date, amount FROM petty_expenses UNION ALL SELECT date, amount FROM hl_expenses) e
     GROUP BY yr, mo ORDER BY yr DESC, mo DESC LIMIT 6"
);
$incChart = Database::fetchAll(
    "SELECT DATE_FORMAT(date,'%b %Y') AS label, YEAR(date) AS yr, MONTH(date) AS mo, SUM(amount) AS total
     FROM (SELECT date, amount FROM income_cash UNION ALL SELECT date, amount FROM income_card UNION ALL SELECT date, amount FROM income_roomcharged) i
     GROUP BY yr, mo ORDER BY yr DESC, mo DESC LIMIT 6"
);
$expChart = array_reverse($expChart);
$incChart = array_reverse($incChart);

// Merge labels preserving order
$chartLabelMap = [];
foreach ($incChart as $r) $chartLabelMap[$r['yr'].'-'.$r['mo']] = $r['label'];
foreach ($expChart as $r) $chartLabelMap[$r['yr'].'-'.$r['mo']] = $r['label'];
ksort($chartLabelMap);
$chartLabels = array_values($chartLabelMap);
$incomeMap   = array_column($incChart, 'total', 'label');
$expenseMap  = array_column($expChart, 'total', 'label');
$incomeData  = array_map(fn($l) => round((float)($incomeMap[$l]  ?? 0), 2), $chartLabels);
$expenseData = array_map(fn($l) => round((float)($expenseMap[$l] ?? 0), 2), $chartLabels);

// ── Category summary ──────────────────────────────────────────
$catSummary = getCategorySummary();

// ── Recent records ────────────────────────────────────────────
$recentPetty = Database::fetchAll("SELECT date, description, amount FROM petty_expenses ORDER BY date DESC, id DESC LIMIT 6");
$recentInc   = Database::fetchAll(
    "SELECT date, category AS description, amount, 'cash' AS src FROM income_cash
     UNION ALL SELECT date, category, amount, 'card' FROM income_card
     ORDER BY date DESC LIMIT 6"
);

// ── Available weeks for filter ────────────────────────────────
$availWeeks = Database::fetchAll(
    "SELECT DISTINCT week_number, YEAR(date) AS yr FROM (
        SELECT week_number, date FROM petty_expenses UNION SELECT week_number, date FROM hl_expenses
        UNION SELECT week_number, date FROM income_cash UNION SELECT week_number, date FROM income_card
        UNION SELECT week_number, date FROM income_roomcharged
    ) w ORDER BY yr DESC, week_number DESC LIMIT 20"
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
    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
        <div>
            <label class="form-label mb-1 fw-semibold small">Year</label>
            <select name="year" class="form-select form-select-sm" style="width:auto" id="filterYear">
                <option value="">All Years</option>
                <?php foreach ([2023,2024,2025,2026] as $y): ?>
                    <option value="<?=$y?>" <?= $filterYear===$y?'selected':'' ?>><?=$y?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 fw-semibold small">Month</label>
            <select name="month" class="form-select form-select-sm" style="width:auto" id="filterMonth">
                <option value="">All Months</option>
                <?php foreach (range(1,12) as $m): ?>
                    <option value="<?=$m?>" <?= $filterMonth===$m?'selected':'' ?>><?=date('F',mktime(0,0,0,$m,1))?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 fw-semibold small">Week #</label>
            <select name="week" class="form-select form-select-sm" style="width:auto">
                <option value="">All Weeks</option>
                <?php foreach ($availWeeks as $w): ?>
                    <option value="<?=$w['week_number']?>" <?= $filterWeek===$w['week_number']?'selected':'' ?>>
                        Wk <?=$w['week_number']?> (<?=$w['yr']?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2 align-items-end">
            <button class="btn btn-primary btn-sm px-3">Apply</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- ── KPI Row 1: Main totals ────────────────────────────────── -->
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
                    <?= ($bal >= 0 ? '' : '-') . formatCurrency(abs($bal)) ?>
                </div>
                <small class="text-muted">Income − Expenses</small>
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

<!-- ── Charts ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Monthly Income vs Expenses (Last 6 Months)</span>
            </div>
            <div class="data-card-body">
                <div class="chart-container" style="height:280px">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="data-card">
            <div class="data-card-header"><span class="fw-semibold">Income Breakdown — <?= $filterLabel ?></span></div>
            <div class="data-card-body">
                <div class="chart-container" style="height:200px">
                    <canvas id="doughnutChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php
                    $incRows = [
                        ['Cash',  $totals['cash'],  '#22c55e'],
                        ['Card',  $totals['card'],  '#7c3aed'],
                        ['Rooms', $totals['room'],  '#0891b2'],
                    ];
                    foreach ($incRows as [$lbl, $val, $col]):
                        $pct = $totals['totalIncome'] > 0 ? round($val/$totals['totalIncome']*100,1) : 0;
                    ?>
                    <div class="d-flex justify-content-between align-items-center small mb-1">
                        <span><span class="badge me-1" style="background:<?=$col?>">&nbsp;</span><?=$lbl?></span>
                        <span class="fw-semibold"><?= formatCurrency($val) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Transactions & Category Summary ─────────────── -->
<div class="row g-3">
    <div class="col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Recent Petty Expenses</span>
                <a href="expense.php?type=petty" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
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
        </div>
    </div>
    <div class="col-lg-5">
        <div class="data-card">
            <div class="data-card-header"><span class="fw-semibold">Cash Income by Category — <?= $filterLabel ?></span></div>
            <div class="data-card-body">
                <?php
                $cats     = $catSummary['cash'];
                $catTotal = array_sum(array_column($cats, 'total'));
                $colors   = ['#22c55e','#3b82f6','#f59e0b','#8b5cf6','#0891b2','#ef4444','#14b8a6'];
                foreach ($cats as $i => $cat):
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
                <?php endforeach;
                if (!$cats): ?><p class="text-muted text-center small py-3">No income data for selected period.</p><?php endif; ?>
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

$extraJS = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function(){
    buildBarChart('barChart', $jsLabels, $jsIncome, $jsExpense);
    buildDoughnutChart('doughnutChart',
        ['Cash','Card','Room Charged'],
        [$incCash, $incCard, $incRoom],
        ['#22c55e','#7c3aed','#0891b2']
    );
});
</script>
JS;

include 'includes/footer.php';
?>
