<?php
// ============================================================
// reports.php — Monthly & Weekly Reports (FIXED)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Reports';

$selYear  = (int)($_GET['year']  ?? date('Y'));
$selMonth = (int)($_GET['month'] ?? date('n'));

// ── Individual totals (simple, correct param count) ──────────
$p2 = [$selYear, $selMonth];

$pettyTotal = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$hlTotal    = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM hl_expenses    WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$cashTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_cash         WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$cardTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_card         WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$roomTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_roomcharged  WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];

$totalExp = $pettyTotal + $hlTotal;
$totalInc = $cashTotal + $cardTotal + $roomTotal;
$balance  = $totalInc - $totalExp;

// ── Weekly expense breakdown ──────────────────────────────────
$weeklyExp = Database::fetchAll(
    "SELECT week_number, SUM(amount) AS total
     FROM (
       SELECT week_number, amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?
       UNION ALL
       SELECT week_number, amount FROM hl_expenses    WHERE YEAR(date)=? AND MONTH(date)=?
     ) e
     GROUP BY week_number ORDER BY week_number",
    [$selYear,$selMonth,$selYear,$selMonth]
);

$weeklyInc = Database::fetchAll(
    "SELECT week_number, SUM(amount) AS total
     FROM (
       SELECT week_number, amount FROM income_cash        WHERE YEAR(date)=? AND MONTH(date)=?
       UNION ALL
       SELECT week_number, amount FROM income_card        WHERE YEAR(date)=? AND MONTH(date)=?
       UNION ALL
       SELECT week_number, amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=?
     ) i
     GROUP BY week_number ORDER BY week_number",
    [$selYear,$selMonth,$selYear,$selMonth,$selYear,$selMonth]
);

// ── Daily summary — fixed param count (10 params, 10 placeholders) ──
$daily = Database::fetchAll(
    "SELECT d.day_date AS date,
            COALESCE(e.total, 0) AS exp_total,
            COALESCE(i.total, 0) AS inc_total
     FROM (
         SELECT DISTINCT date AS day_date FROM petty_expenses       WHERE YEAR(date)=? AND MONTH(date)=?
         UNION
         SELECT DISTINCT date FROM hl_expenses                      WHERE YEAR(date)=? AND MONTH(date)=?
         UNION
         SELECT DISTINCT date FROM income_cash                      WHERE YEAR(date)=? AND MONTH(date)=?
         UNION
         SELECT DISTINCT date FROM income_card                      WHERE YEAR(date)=? AND MONTH(date)=?
         UNION
         SELECT DISTINCT date FROM income_roomcharged               WHERE YEAR(date)=? AND MONTH(date)=?
     ) d
     LEFT JOIN (
         SELECT date, SUM(amount) AS total FROM (
             SELECT date, amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?
             UNION ALL
             SELECT date, amount FROM hl_expenses    WHERE YEAR(date)=? AND MONTH(date)=?
         ) ex GROUP BY date
     ) e ON d.day_date = e.date
     LEFT JOIN (
         SELECT date, SUM(amount) AS total FROM (
             SELECT date, amount FROM income_cash        WHERE YEAR(date)=? AND MONTH(date)=?
             UNION ALL
             SELECT date, amount FROM income_card        WHERE YEAR(date)=? AND MONTH(date)=?
             UNION ALL
             SELECT date, amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=?
         ) inc GROUP BY date
     ) i ON d.day_date = i.date
     ORDER BY d.day_date",
    [
        $selYear,$selMonth,  // petty distinct
        $selYear,$selMonth,  // hl distinct
        $selYear,$selMonth,  // cash distinct
        $selYear,$selMonth,  // card distinct
        $selYear,$selMonth,  // room distinct
        $selYear,$selMonth,  // petty sum
        $selYear,$selMonth,  // hl sum
        $selYear,$selMonth,  // cash sum
        $selYear,$selMonth,  // card sum
        $selYear,$selMonth,  // room sum
    ]
);

// ── Chart arrays ──────────────────────────────────────────────
$weekNums  = array_column($weeklyExp, 'week_number');
$wkExpData = array_map('floatval', array_column($weeklyExp, 'total'));
$wkIncMap  = array_column($weeklyInc, 'total', 'week_number');
$wkIncData = array_map(fn($w) => (float)($wkIncMap[$w] ?? 0), $weekNums);

// Daily chart
$dailyLabels  = array_map(fn($d) => date('M d', strtotime($d['date'])), $daily);
$dailyIncData = array_map(fn($d) => (float)$d['inc_total'], $daily);
$dailyExpData = array_map(fn($d) => (float)$d['exp_total'], $daily);

include 'includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Reports</h1>
        <p class="text-muted mb-0">Monthly and weekly financial summary — <?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?></p>
    </div>
    <a href="exports/export.php?table=report&year=<?= $selYear ?>&month=<?= $selMonth ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Period Selector -->
<div class="filter-bar mb-4">
    <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
        <div>
            <label class="form-label mb-1 fw-semibold">Year</label>
            <select name="year" class="form-select form-select-sm" style="width:auto">
                <?php foreach ([2023,2024,2025,2026] as $y): ?>
                <option value="<?=$y?>" <?=$selYear==$y?'selected':''?>><?=$y?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 fw-semibold">Month</label>
            <select name="month" class="form-select form-select-sm" style="width:auto">
                <?php foreach (range(1,12) as $m): ?>
                <option value="<?=$m?>" <?=$selMonth==$m?'selected':''?>><?=date('F', mktime(0,0,0,$m,1))?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary btn-sm px-4">Generate Report</button>
    </form>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon income mx-auto mb-2"><i class="bi bi-arrow-up-circle-fill"></i></div>
            <div class="stat-label">Total Income</div>
            <div class="stat-value fs-5 amount-income"><?= formatCurrency($totalInc) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon expense mx-auto mb-2"><i class="bi bi-arrow-down-circle-fill"></i></div>
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value fs-5 amount-expense"><?= formatCurrency($totalExp) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon balance mx-auto mb-2"><i class="bi bi-wallet-fill"></i></div>
            <div class="stat-label">Net Balance</div>
            <div class="stat-value fs-5 <?= $balance>=0?'balance-positive':'balance-negative' ?>"><?= formatCurrency($balance) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon neutral mx-auto mb-2"><i class="bi bi-percent"></i></div>
            <div class="stat-label">Net Margin</div>
            <?php $margin = $totalInc > 0 ? round($balance/$totalInc*100,1) : 0; ?>
            <div class="stat-value fs-5 <?= $margin>=0?'balance-positive':'balance-negative' ?>"><?= $margin ?>%</div>
        </div>
    </div>
</div>

<!-- Income & Expense breakdown cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="data-card h-100">
            <div class="data-card-header"><span class="fw-semibold text-success"><i class="bi bi-arrow-up-circle me-1"></i>Income Breakdown</span></div>
            <div class="data-card-body">
                <?php
                $incRows = [
                    ['Cash Income',   $cashTotal,  '#22c55e', 'bi-cash'],
                    ['Card Income',   $cardTotal,  '#7c3aed', 'bi-credit-card'],
                    ['Room Charged',  $roomTotal,  '#0891b2', 'bi-door-open'],
                ];
                foreach ($incRows as [$lbl, $val, $col, $icon]):
                    $pct = $totalInc > 0 ? round($val/$totalInc*100,1) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small"><i class="bi <?=$icon?> me-1" style="color:<?=$col?>"></i><?=$lbl?></span>
                        <span class="fw-semibold small"><?= formatCurrency($val) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                    </div>
                    <div class="progress" style="height:8px;border-radius:99px">
                        <div class="progress-bar" style="width:<?=$pct?>%;background:<?=$col?>;border-radius:99px"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="border-top pt-2 mt-2 d-flex justify-content-between fw-bold">
                    <span>Total Income</span>
                    <span class="amount-income"><?= formatCurrency($totalInc) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="data-card h-100">
            <div class="data-card-header"><span class="fw-semibold text-danger"><i class="bi bi-arrow-down-circle me-1"></i>Expense Breakdown</span></div>
            <div class="data-card-body">
                <?php
                $expRows = [
                    ['Petty Expenses', $pettyTotal, '#f97316', 'bi-wallet2'],
                    ['H/L Expenses',   $hlTotal,    '#ef4444', 'bi-bank'],
                ];
                foreach ($expRows as [$lbl, $val, $col, $icon]):
                    $pct = $totalExp > 0 ? round($val/$totalExp*100,1) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small"><i class="bi <?=$icon?> me-1" style="color:<?=$col?>"></i><?=$lbl?></span>
                        <span class="fw-semibold small"><?= formatCurrency($val) ?> <span class="text-muted">(<?=$pct?>%)</span></span>
                    </div>
                    <div class="progress" style="height:8px;border-radius:99px">
                        <div class="progress-bar" style="width:<?=$pct?>%;background:<?=$col?>;border-radius:99px"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="border-top pt-2 mt-2 d-flex justify-content-between fw-bold">
                    <span>Total Expenses</span>
                    <span class="amount-expense"><?= formatCurrency($totalExp) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Weekly Income vs Expenses — <?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?></span>
            </div>
            <div class="data-card-body">
                <div class="chart-container" style="height:260px">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Summary Table -->
<div class="data-card">
    <div class="data-card-header">
        <span class="fw-semibold">Daily Summary — <?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?></span>
        <span class="badge bg-light text-secondary border"><?= count($daily) ?> days with activity</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover datatable mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th class="text-end">Total Income</th>
                    <th class="text-end">Total Expenses</th>
                    <th class="text-end">Daily Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($daily as $d):
                $dayBal = (float)$d['inc_total'] - (float)$d['exp_total'];
            ?>
                <tr>
                    <td><strong><?= formatDate($d['date']) ?></strong></td>
                    <td class="text-end amount-income"><?= formatCurrency((float)$d['inc_total']) ?></td>
                    <td class="text-end amount-expense"><?= formatCurrency((float)$d['exp_total']) ?></td>
                    <td class="text-end fw-semibold <?= $dayBal>=0?'balance-positive':'balance-negative' ?>">
                        <?= ($dayBal >= 0 ? '+' : '') . formatCurrency($dayBal) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td>Monthly Total</td>
                    <td class="text-end amount-income"><?= formatCurrency($totalInc) ?></td>
                    <td class="text-end amount-expense"><?= formatCurrency($totalExp) ?></td>
                    <td class="text-end fw-bold <?= $balance>=0?'balance-positive':'balance-negative' ?>">
                        <?= ($balance >= 0 ? '+' : '') . formatCurrency($balance) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php
$jsWeekLabels = json_encode(array_map(fn($w) => "Week $w", $weekNums));
$jsWkInc      = json_encode($wkIncData);
$jsWkExp      = json_encode($wkExpData);

$extraJS = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function(){
    buildBarChart('weeklyChart', $jsWeekLabels, $jsWkInc, $jsWkExp);
});
</script>
JS;

include 'includes/footer.php';
?>
