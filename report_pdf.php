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

// ── Locate Python (works even when Apache has no PATH) ────────
function findPython(): ?string {
    // 1. Common Windows install paths (all Python versions)
    $winPaths = [];
    foreach (['313','312','311','310','39','38','37'] as $v) {
        $maj = substr($v,0,1);
        $min = strlen($v)===3 ? substr($v,1) : substr($v,1,1);
        $winPaths[] = "C:/Python$v/python.exe";
        $winPaths[] = "C:/Python$maj$min/python.exe";
        $winPaths[] = "C:/Users/" . (get_current_user() ?: 'User') . "/AppData/Local/Programs/Python/Python$v/python.exe";
        $winPaths[] = "C:/Users/" . (get_current_user() ?: 'User') . "/AppData/Local/Programs/Python/Python$maj$min/python.exe";
    }
    // Windows Store Python
    $winPaths[] = "C:/Users/" . (get_current_user() ?: 'User') . "/AppData/Local/Microsoft/WindowsApps/python3.exe";
    $winPaths[] = "C:/Users/" . (get_current_user() ?: 'User') . "/AppData/Local/Microsoft/WindowsApps/python.exe";
    // Program Files variants
    foreach (['313','312','311','310','39','38'] as $v) {
        $winPaths[] = "C:/Program Files/Python$v/python.exe";
        $winPaths[] = "C:/Program Files (x86)/Python$v/python.exe";
    }
    // 2. PATH-based names (works on Linux/Mac and proper Windows PATH)
    $nameBased = ['python3', 'python', 'python3.exe', 'python.exe'];

    $all = array_merge($winPaths, $nameBased);

    foreach ($all as $candidate) {
        $quoted = str_contains($candidate, ' ') ? '"'.$candidate.'"' : $candidate;
        $out = @shell_exec("$quoted --version 2>&1");
        if ($out && stripos($out, 'python 3') !== false) {
            return $quoted;
        }
    }
    return null;
}

$pyBin = findPython();

if (!$pyBin) {
    $backUrl  = "reports.php?year=$selYear&month=$selMonth";
    $csvUrl   = "exports/export.php?table=report&year=$selYear&month=$selMonth";
    die('<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head><body class="p-4" style="background:#f8f9fa">
    <div class="card border-danger" style="max-width:600px;margin:40px auto">
      <div class="card-header bg-danger text-white fw-bold">Python 3 Not Found</div>
      <div class="card-body">
        <p>Apache could not locate Python 3. Follow these steps:</p>
        <ol>
          <li>Download Python from <a href="https://python.org/downloads" target="_blank">python.org/downloads</a></li>
          <li>During install: <strong>check "Add python.exe to PATH"</strong></li>
          <li>After install, open Command Prompt and run:<br>
              <code>pip install reportlab</code></li>
          <li>Restart Apache in XAMPP Control Panel</li>
          <li>If it still fails, open <code>report_pdf.php</code> and add your Python path:<br>
              <code>$winPaths[] = "C:/Python313/python.exe";</code></li>
        </ol>
        <div class="d-flex gap-2 mt-3">
          <a href="'.$backUrl.'" class="btn btn-primary">← Back to Reports</a>
          <a href="'.$csvUrl.'" class="btn btn-success">Download CSV instead</a>
        </div>
      </div>
    </div></body></html>');
}

// ── Check / install reportlab ─────────────────────────────────
$rlCheck = @shell_exec("$pyBin -c \"import reportlab; print('ok')\" 2>&1");
if (trim($rlCheck ?? '') !== 'ok') {
    // Auto-install
    @shell_exec("$pyBin -m pip install reportlab --quiet 2>&1");
    $rlCheck = @shell_exec("$pyBin -c \"import reportlab; print('ok')\" 2>&1");

    if (trim($rlCheck ?? '') !== 'ok') {
        $backUrl = "reports.php?year=$selYear&month=$selMonth";
        // Find pip path based on Python path for clearer instructions
        $pipCmd = str_replace('python.exe','pip.exe',$pyBin);
        $pipCmd = str_replace('python3','pip3',$pipCmd);
        die('<!DOCTYPE html><html><head><meta charset="UTF-8">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        </head><body class="p-4" style="background:#f8f9fa">
        <div class="card border-warning" style="max-width:600px;margin:40px auto">
          <div class="card-header bg-warning fw-bold">Missing Python Library: reportlab</div>
          <div class="card-body">
            <p>Python was found (<code>'.$pyBin.'</code>) but <strong>reportlab</strong> is not installed.</p>
            <p>Open <strong>Command Prompt as Administrator</strong> and run:</p>
            <div class="bg-dark text-white p-3 rounded font-monospace mb-3">
              pip install reportlab
            </div>
            <p class="text-muted small">Or using the full Python path:</p>
            <div class="bg-dark text-white p-2 rounded font-monospace small mb-3">
              '.htmlspecialchars($pyBin).' -m pip install reportlab
            </div>
            <p>After installing, come back and try again.</p>
            <div class="d-flex gap-2 mt-3">
              <a href="'.$backUrl.'" class="btn btn-primary">← Try Again</a>
              <a href="exports/export.php?table=report&year='.$selYear.'&month='.$selMonth.'" class="btn btn-success">Download CSV instead</a>
            </div>
          </div>
        </div></body></html>');
    }
}

// ── Write JSON data to a temp file (avoids stdin issues on Windows)
$tmpJson = tempnam(sys_get_temp_dir(), 'wvr_json_') . '.json';
file_put_contents($tmpJson, $json);

// ── Run Python via proc_open (reliable cross-platform, no stdin redirect needed)
$escapedScript = $script;       // full path, no shell quoting needed with proc_open
$escapedTmp    = $tmpPdf;
$escapedJson   = $tmpJson;

// Build command array — proc_open with array skips shell interpretation entirely
$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// Strip quotes from $pyBin that findPython() may have added
$pyBinClean = trim($pyBin, '"\'');

$cmdArray = [$pyBinClean, $script, $tmpPdf, $tmpJson];

$descriptors = [
    0 => ['pipe', 'r'],   // stdin
    1 => ['pipe', 'w'],   // stdout
    2 => ['pipe', 'w'],   // stderr
];

$process = proc_open($cmdArray, $descriptors, $pipes, null, null,
                     ['bypass_shell' => true]);

$stdout = ''; $stderr = '';
if (is_resource($process)) {
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    proc_close($process);
}

@unlink($tmpJson);
$result = trim($stdout . $stderr);

if (!file_exists($tmpPdf) || filesize($tmpPdf) < 100) {
    $backUrl = "reports.php?year=$selYear&month=$selMonth";
    die('<!DOCTYPE html><html><head><meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head><body class="p-4" style="background:#f8f9fa">
    <div class="card border-danger" style="max-width:700px;margin:40px auto">
      <div class="card-header bg-danger text-white fw-bold">PDF Generation Failed</div>
      <div class="card-body">
        <p><strong>Python found:</strong> <code>'.htmlspecialchars($pyBinClean).'</code></p>
        <p><strong>Error output:</strong></p>
        <pre class="bg-dark text-white p-3 rounded small" style="max-height:300px;overflow:auto">'.htmlspecialchars($result ?: 'No output — check that reportlab is installed for this Python.').'</pre>
        <p class="text-muted small">Try running in Command Prompt:<br>
        <code>'.htmlspecialchars($pyBinClean).' -m pip install reportlab</code></p>
        <a href="'.$backUrl.'" class="btn btn-primary mt-2">← Back to Reports</a>
      </div>
    </div></body></html>');
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
