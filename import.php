<?php
// ============================================================
// import.php — Import Data from CSV/Excel
// ============================================================
require_once 'config.php';
require_once 'functions.php';

$pageTitle = 'Import Data';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $table    = $_POST['table']    ?? '';
    $file     = $_FILES['csv_file'];
    $allowed  = ['text/csv','application/vnd.ms-excel','text/plain','application/octet-stream'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $validTables = ['petty_expenses','hl_expenses','income_cash','income_card','income_roomcharged'];

    if (!in_array($table, $validTables)) {
        setFlash('danger', 'Invalid table selection.');
        header('Location: import.php'); exit;
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'File upload error. Code: ' . $file['error']);
        header('Location: import.php'); exit;
    } elseif (!in_array($ext, ['csv','txt'])) {
        setFlash('danger', 'Only CSV files are supported. Please save your file as CSV (comma-separated).');
        header('Location: import.php'); exit;
    }

    $tmpPath   = $file['tmp_name'];
    $clearFirst = isset($_POST['clear_first']) && $_POST['clear_first'] === '1';
    $results   = importCSV($tmpPath, $table, $clearFirst);

    // Build detailed flash message
    $parts = [];
    if (!empty($results['cleared']))  $parts[] = $results['cleared'] . ' old record(s) cleared.';
    if ($results['inserted'] > 0)     $parts[] = $results['inserted'] . ' record(s) imported.';
    if (!empty($results['skipped']))  $parts[] = $results['skipped'] . ' row(s) skipped (blank or invalid date).';

    if ($results['inserted'] > 0 && empty($results['errors'])) {
        setFlash('success', implode(' ', $parts));
    } elseif ($results['inserted'] > 0) {
        $errSummary = implode(' | ', array_slice($results['errors'], 0, 5));
        if (count($results['errors']) > 5) $errSummary .= ' ... and ' . (count($results['errors'])-5) . ' more.';
        setFlash('warning', implode(' ', $parts) . ' Some rows had issues: ' . $errSummary);
    } else {
        $errSummary = implode(' | ', array_slice($results['errors'], 0, 5));
        setFlash('danger', 'No records imported. ' . ($errSummary ?: 'Check your CSV format — make sure dates are YYYY-MM-DD or MM/DD/YYYY.'));
    }

    header('Location: import.php'); exit;
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-upload me-2 text-warning"></i>Import Data</h1>
    <p class="text-muted mb-0">Import income and expense records from CSV files</p>
</div>

<div class="row g-4">
    <!-- Upload Form -->
    <div class="col-lg-6">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Upload CSV File</span>
            </div>
            <div class="data-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Target Table <span class="text-danger">*</span></label>
                        <select name="table" class="form-select" required>
                            <option value="">— Select Target —</option>
                            <optgroup label="Expenses">
                                <option value="petty_expenses">Petty Expenses</option>
                                <option value="hl_expenses">H/L Expenses</option>
                            </optgroup>
                            <optgroup label="Income">
                                <option value="income_cash">Income — Paid by Cash</option>
                                <option value="income_card">Income — Paid by Card</option>
                                <option value="income_roomcharged">Income — Room Charged</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                        <div class="form-text">Max file size: 10MB. Must be UTF-8 encoded CSV.</div>
                    </div>
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="clear_first" value="1" id="clearFirst">
                            <label class="form-check-label fw-semibold" for="clearFirst">
                                <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                                Clear all existing records in this table before importing
                            </label>
                            <div class="text-muted mt-1" style="font-size:11px">
                                ⚠ Use this to fix previously imported data with wrong dates. This <strong>permanently deletes</strong> all current records in the selected table before importing the new file.
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-upload me-1"></i>Import Records
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- CSV Format Guide -->
    <div class="col-lg-6">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">CSV Format Guide</span>
            </div>
            <div class="data-card-body">
                <p class="text-muted small mb-3">Your CSV file must have a header row. Columns must be in order:</p>

                <div class="mb-3">
                    <span class="badge bg-danger-subtle text-danger mb-1">Petty / H/L Expenses</span>
                    <div class="bg-light rounded p-2 font-monospace small">
                        DATE, DESCRIPTION, AMOUNT<br>
                        2025-09-01, GASOLINE, 442.00<br>
                        2025-09-02, Guard Salary, 10615.20
                    </div>
                </div>

                <div class="mb-3">
                    <span class="badge bg-success-subtle text-success mb-1">Income — Cash / Card</span>
                    <div class="bg-light rounded p-2 font-monospace small">
                        DATE, CATEGORY, AMOUNT<br>
                        2025-09-01, Resto Income, 7955.00<br>
                        2025-09-02, Drinks Income, 1035.00
                    </div>
                </div>

                <div class="mb-3">
                    <span class="badge bg-info-subtle text-info mb-1">Income — Room Charged</span>
                    <div class="bg-light rounded p-2 font-monospace small">
                        DATE, ROOM_REFERENCE, AMOUNT<br>
                        2025-09-01, B3 B4 B5, 2525.00<br>
                        2025-09-02, , 3795.00
                    </div>
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Date formats accepted:</strong><br>
                    <code>YYYY-MM-DD</code> (e.g. 2025-09-01) &nbsp;·&nbsp;
                    <code>MM/DD/YYYY</code> (e.g. 09/01/2025) &nbsp;·&nbsp;
                    <code>DD-MM-YYYY</code> (e.g. 01-09-2025)<br>
                    Week number and month are <strong>auto-calculated</strong> — do not include them in the CSV.
                    If your file was saved from Excel, use <em>Save As → CSV UTF-8</em>.
                </div>
            </div>
        </div>
    </div>

    <!-- Export Templates -->
    <div class="col-12">
        <div class="data-card">
            <div class="data-card-header">
                <span class="fw-semibold">Download CSV Templates</span>
            </div>
            <div class="data-card-body">
                <p class="text-muted small mb-3">Download blank templates to fill in and import:</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="exports/template.php?type=petty" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Petty Expenses Template
                    </a>
                    <a href="exports/template.php?type=hl" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>H/L Expenses Template
                    </a>
                    <a href="exports/template.php?type=cash" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Cash Income Template
                    </a>
                    <a href="exports/template.php?type=card" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Card Income Template
                    </a>
                    <a href="exports/template.php?type=room" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Room Charged Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
