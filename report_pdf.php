<?php
// ============================================================
// report_pdf.php — Generate & Download PDF Report
// ============================================================
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/auth.php';
requireLogin();

$selYear  = (int)($_GET['year']  ?? date('Y'));
$selMonth = (int)($_GET['month'] ?? date('n'));
$p2       = [$selYear, $selMonth];
$period   = date('F Y', mktime(0,0,0,$selMonth,1,$selYear));

// ── Fetch all data ────────────────────────────────────────────
$pettyTotal = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$hlTotal    = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM hl_expenses    WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$cashTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_cash         WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$cardTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_card         WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];
$roomTotal  = (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS t FROM income_roomcharged  WHERE YEAR(date)=? AND MONTH(date)=?", $p2)['t'];

$totalExp = $pettyTotal + $hlTotal;
$totalInc = $cashTotal  + $cardTotal + $roomTotal;

// Weekly data merged (income + expenses per week)
$weeklyExpRows = Database::fetchAll(
    "SELECT week_number, SUM(amount) AS total FROM
     (SELECT week_number, amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?
      UNION ALL SELECT week_number, amount FROM hl_expenses WHERE YEAR(date)=? AND MONTH(date)=?) e
     GROUP BY week_number ORDER BY week_number",
    [$selYear,$selMonth,$selYear,$selMonth]
);
$weeklyIncRows = Database::fetchAll(
    "SELECT week_number, SUM(amount) AS total FROM
     (SELECT week_number, amount FROM income_cash        WHERE YEAR(date)=? AND MONTH(date)=?
      UNION ALL SELECT week_number, amount FROM income_card        WHERE YEAR(date)=? AND MONTH(date)=?
      UNION ALL SELECT week_number, amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=?) i
     GROUP BY week_number ORDER BY week_number",
    [$selYear,$selMonth,$selYear,$selMonth,$selYear,$selMonth]
);

// Merge weekly into single array
$weeklyMerged = [];
foreach ($weeklyExpRows as $r) {
    $weeklyMerged[$r['week_number']]['week_number'] = $r['week_number'];
    $weeklyMerged[$r['week_number']]['expenses']    = (float)$r['total'];
}
foreach ($weeklyIncRows as $r) {
    $weeklyMerged[$r['week_number']]['week_number'] = $r['week_number'];
    $weeklyMerged[$r['week_number']]['income']      = (float)$r['total'];
}
foreach ($weeklyMerged as &$w) {
    $w['income']   = $w['income']   ?? 0;
    $w['expenses'] = $w['expenses'] ?? 0;
}
unset($w);
ksort($weeklyMerged);

// Daily summary
$daily = Database::fetchAll(
    "SELECT d.day_date AS date,
            COALESCE(e.total,0) AS exp_total,
            COALESCE(i.total,0) AS inc_total
     FROM (
         SELECT DISTINCT date AS day_date FROM petty_expenses       WHERE YEAR(date)=? AND MONTH(date)=?
         UNION SELECT DISTINCT date FROM hl_expenses                WHERE YEAR(date)=? AND MONTH(date)=?
         UNION SELECT DISTINCT date FROM income_cash                WHERE YEAR(date)=? AND MONTH(date)=?
         UNION SELECT DISTINCT date FROM income_card                WHERE YEAR(date)=? AND MONTH(date)=?
         UNION SELECT DISTINCT date FROM income_roomcharged         WHERE YEAR(date)=? AND MONTH(date)=?
     ) d
     LEFT JOIN (SELECT date, SUM(amount) AS total FROM
                (SELECT date,amount FROM petty_expenses WHERE YEAR(date)=? AND MONTH(date)=?
                 UNION ALL SELECT date,amount FROM hl_expenses WHERE YEAR(date)=? AND MONTH(date)=?) ex
                GROUP BY date) e ON d.day_date = e.date
     LEFT JOIN (SELECT date, SUM(amount) AS total FROM
                (SELECT date,amount FROM income_cash        WHERE YEAR(date)=? AND MONTH(date)=?
                 UNION ALL SELECT date,amount FROM income_card        WHERE YEAR(date)=? AND MONTH(date)=?
                 UNION ALL SELECT date,amount FROM income_roomcharged WHERE YEAR(date)=? AND MONTH(date)=?) inc
                GROUP BY date) i ON d.day_date = i.date
     ORDER BY d.day_date",
    [$selYear,$selMonth, $selYear,$selMonth, $selYear,$selMonth,
     $selYear,$selMonth, $selYear,$selMonth,
     $selYear,$selMonth, $selYear,$selMonth,
     $selYear,$selMonth, $selYear,$selMonth, $selYear,$selMonth]
);

// Transaction rows
$pettyRows = Database::fetchAll(
    "SELECT date, description, amount, week_number FROM petty_expenses
     WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date, id", $p2
);
$hlRows = Database::fetchAll(
    "SELECT date, description, amount, week_number FROM hl_expenses
     WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date, id", $p2
);
$cashRows = Database::fetchAll(
    "SELECT date, category, amount, week_number FROM income_cash
     WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date, id", $p2
);
$cardRows = Database::fetchAll(
    "SELECT date, category, amount, week_number FROM income_card
     WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date, id", $p2
);
$roomRows = Database::fetchAll(
    "SELECT date, room_reference, amount, week_number FROM income_roomcharged
     WHERE YEAR(date)=? AND MONTH(date)=? ORDER BY date, id", $p2
);

// ── Build JSON payload ────────────────────────────────────────
$user = currentUser();
$payload = [
    'period'        => $period,
    'year'          => $selYear,
    'month'         => $selMonth,
    'user'          => $user['name'],
    'totalIncome'   => $totalInc,
    'totalExpenses' => $totalExp,
    'pettyTotal'    => $pettyTotal,
    'hlTotal'       => $hlTotal,
    'cashTotal'     => $cashTotal,
    'cardTotal'     => $cardTotal,
    'roomTotal'     => $roomTotal,
    'weekly'        => array_values($weeklyMerged),
    'daily'         => $daily,
    'pettyRows'     => $pettyRows,
    'hlRows'        => $hlRows,
    'cashRows'      => $cashRows,
    'cardRows'      => $cardRows,
    'roomRows'      => $roomRows,
];

$json     = json_encode($payload, JSON_UNESCAPED_UNICODE);
$filename = 'WVR_Report_' . $period . '_' . date('Ymd_His') . '.pdf';
$tmpPdf   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
$script   = __DIR__ . '/exports/generate_report_pdf.py';

// ── Call Python generator ─────────────────────────────────────
// Try python3 first, fall back to python
$pythonBins = ['python3', 'python'];
$output = null;
$pyBin  = null;

foreach ($pythonBins as $bin) {
    $test = shell_exec("$bin --version 2>&1");
    if ($test && stripos($test, 'python') !== false) {
        $pyBin = $bin;
        break;
    }
}

if (!$pyBin) {
    die('<div style="font-family:sans-serif;padding:30px;background:#fee;border:2px solid red;border-radius:8px;margin:20px">
        <h3>Python Not Found</h3>
        <p>Python 3 is required to generate PDF reports. Please install Python 3 on your server (XAMPP includes it at <code>C:\Python3X\python.exe</code>) and make sure it is in your PATH.</p>
        <p>Alternatively, use the <a href="exports/export.php?table=report&year=' . $selYear . '&month=' . $selMonth . '">CSV Export</a> instead.</p>
        <a href="reports.php?year=' . $selYear . '&month=' . $selMonth . '" style="background:#4f6ef7;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block;margin-top:10px">← Back to Reports</a>
    </div>');
}

// Check reportlab
$rlCheck = shell_exec("$pyBin -c \"import reportlab; print('ok')\" 2>&1");
if (trim($rlCheck ?? '') !== 'ok') {
    // Try to install reportlab
    shell_exec("$pyBin -m pip install reportlab --quiet 2>&1");
    $rlCheck = shell_exec("$pyBin -c \"import reportlab; print('ok')\" 2>&1");
    if (trim($rlCheck ?? '') !== 'ok') {
        die('<div style="font-family:sans-serif;padding:30px;background:#fff3cd;border:2px solid #f59e0b;border-radius:8px;margin:20px">
            <h3>Missing: reportlab</h3>
            <p>Please install the <code>reportlab</code> Python library:</p>
            <pre style="background:#f8f9fa;padding:12px;border-radius:6px">pip install reportlab</pre>
            <a href="reports.php?year=' . $selYear . '&month=' . $selMonth . '" style="background:#4f6ef7;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block;margin-top:10px">← Back to Reports</a>
        </div>');
    }
}

// Escape for shell
$escapedTmp    = escapeshellarg($tmpPdf);
$escapedScript = escapeshellarg($script);
$cmd = "$pyBin $escapedScript $escapedTmp 2>&1";

// Write JSON to temp file instead of stdin (safer cross-platform)
$tmpJson = sys_get_temp_dir() . '/wvr_report_data_' . uniqid() . '.json';
file_put_contents($tmpJson, $json);

$escapedJson = escapeshellarg($tmpJson);
$cmd = "$pyBin $escapedScript $escapedTmp < $escapedJson 2>&1";

$result = shell_exec($cmd);
@unlink($tmpJson);

if (!file_exists($tmpPdf) || filesize($tmpPdf) < 100) {
    die('<div style="font-family:sans-serif;padding:30px;background:#fee;border:2px solid red;border-radius:8px;margin:20px">
        <h3>PDF Generation Failed</h3>
        <p>Error output:</p>
        <pre style="background:#f8f9fa;padding:12px;border-radius:6px;font-size:12px">' . htmlspecialchars($result ?? 'No output') . '</pre>
        <a href="reports.php?year=' . $selYear . '&month=' . $selMonth . '" style="background:#4f6ef7;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;display:inline-block;margin-top:10px">← Back to Reports</a>
    </div>');
}

// ── Stream PDF to browser ─────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpPdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmpPdf);
@unlink($tmpPdf);
exit;
