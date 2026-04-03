<?php
// ============================================================
// functions.php - Helper Functions
// White Villas Resort - Income & Expenses Tracker
// ============================================================

require_once __DIR__ . '/config.php';

// ── Formatting ──────────────────────────────────────────────

function formatCurrency(float $amount): string {
    return CURRENCY . number_format($amount, 2);
}

/**
 * Robustly parse any date string into a Unix timestamp.
 * Handles: YYYY-MM-DD, MM/DD/YYYY, DD/MM/YYYY, Excel serials, BOM, etc.
 */
function parseDate(string $raw): int|false {
    // 1. Strip UTF-8 BOM, non-breaking spaces, zero-width spaces, whitespace
    $raw = trim(preg_replace('/[\x{FEFF}\x{00A0}\x{200B}]/u', '', $raw));
    if ($raw === '') return false;

    // 2. Strip any time portion Excel appends, e.g. "9/1/2025 12:00:00 AM" -> "9/1/2025"
    //    Also handles ISO datetime "2025-09-01T00:00:00" or "2025-09-01 00:00:00"
    $raw = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?(\s*(AM|PM))?$/i', '', trim($raw));
    $raw = preg_replace('/T\d{2}:\d{2}(:\d{2})?.*$/', '', $raw);
    $raw = trim($raw);

    // 3. MM/DD/YYYY or M/D/YYYY — slash format (Excel default, PRIORITY)
    //    e.g. "09/01/2025" = September 1 | "1/13/2026" = January 13
    //    Detect by checking: if second number > 12, it MUST be the day (so first = month)
    //    If both <= 12, we still treat as MM/DD since that is confirmed format for this app
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
        $p1 = (int)$m[1]; $p2 = (int)$m[2]; $yr = (int)$m[3];
        // If p1 > 12, it cannot be a month — treat as DD/MM
        if ($p1 > 12) {
            $ts = mktime(0, 0, 0, $p2, $p1, $yr); // DD/MM
        } else {
            $ts = mktime(0, 0, 0, $p1, $p2, $yr); // MM/DD (confirmed app format)
        }
        return ($ts && $ts > 0) ? $ts : false;
    }

    // 4. MM-DD-YYYY — dash format (same MM/DD logic)
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $m)) {
        $p1 = (int)$m[1]; $p2 = (int)$m[2]; $yr = (int)$m[3];
        if ($p1 > 12) {
            $ts = mktime(0, 0, 0, $p2, $p1, $yr); // DD/MM
        } else {
            $ts = mktime(0, 0, 0, $p1, $p2, $yr); // MM/DD
        }
        return ($ts && $ts > 0) ? $ts : false;
    }

    // 5. MM.DD.YYYY — dot format
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $raw, $m)) {
        $p1 = (int)$m[1]; $p2 = (int)$m[2]; $yr = (int)$m[3];
        if ($p1 > 12) {
            $ts = mktime(0, 0, 0, $p2, $p1, $yr);
        } else {
            $ts = mktime(0, 0, 0, $p1, $p2, $yr);
        }
        return ($ts && $ts > 0) ? $ts : false;
    }

    // 6. YYYY-MM-DD — ISO standard
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        return ($ts && $ts > 0) ? $ts : false;
    }

    // 7. YYYY/MM/DD
    if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $raw, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        return ($ts && $ts > 0) ? $ts : false;
    }

    // 7. Excel serial number (integer or float, range covers ~1990–2050)
    if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
        $serial = (float)$raw;
        if ($serial > 25000 && $serial < 100000) {
            // Excel epoch is Dec 30 1899 (accounts for Excel's fake Feb 29 1900 leap bug)
            $ts = mktime(0, 0, 0, 12, 30, 1899) + (int)($serial * 86400);
            return ($ts > 0 && date('Y', $ts) >= 1980) ? $ts : false;
        }
    }

    // 8. strtotime fallback — handles "Sep 1, 2025", natural language, etc.
    $ts = strtotime($raw);
    if ($ts === false || $ts <= 0 || date('Y', $ts) < 1980) return false;
    return $ts;
}

function parseDateToMySQL(string $raw): string|false {
    $ts = parseDate($raw);
    return $ts ? date('Y-m-d', $ts) : false;
}

function formatDate(string $date): string {
    $ts = parseDate($date);
    return $ts ? date('M d, Y', $ts) : htmlspecialchars($date);
}

function getWeekNumber(string $date): int {
    $ts = parseDate($date);
    return $ts ? (int)date('W', $ts) : (int)date('W');
}

function getMonth(string $date): string {
    $ts = parseDate($date);
    return $ts ? date('F', $ts) : date('F');
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)));
}

// ── Dashboard Summary ────────────────────────────────────────

/**
 * Get dashboard totals optionally filtered by month, year, and/or week.
 * Params are nullable — pass null to include all records.
 * Week filter uses week_number column (stored on insert).
 * When week is set without month/year, it queries only by week_number.
 */
function getDashboardTotals(?int $month = null, ?int $year = null, ?int $week = null): array {
    $conditions = [];
    $params     = [];

    if ($year !== null && $year > 0) {
        $conditions[] = 'YEAR(date) = ?';
        $params[]     = $year;
    }
    if ($month !== null && $month > 0) {
        $conditions[] = 'MONTH(date) = ?';
        $params[]     = $month;
    }
    if ($week !== null && $week > 0) {
        $conditions[] = 'week_number = ?';
        $params[]     = $week;
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $fetch = fn(string $table) =>
        (float) Database::fetch("SELECT COALESCE(SUM(amount),0) AS total FROM $table $where", $params)['total'];

    $petty = $fetch('petty_expenses');
    $hl    = $fetch('hl_expenses');
    $cash  = $fetch('income_cash');
    $card  = $fetch('income_card');
    $room  = $fetch('income_roomcharged');

    $totalExpenses = $petty + $hl;
    $totalIncome   = $cash + $card + $room;
    $balance       = $totalIncome - $totalExpenses;

    return compact('petty', 'hl', 'cash', 'card', 'room', 'totalExpenses', 'totalIncome', 'balance');
}

function getMonthlyTotals(): array {
    $sql = "SELECT 
                YEAR(date) AS yr,
                MONTH(date) AS mo,
                DATE_FORMAT(date, '%M %Y') AS label,
                SUM(amount) AS total
            FROM (
                SELECT date, amount FROM petty_expenses
                UNION ALL
                SELECT date, amount FROM hl_expenses
            ) e
            GROUP BY yr, mo
            ORDER BY yr DESC, mo DESC
            LIMIT 12";

    $expenses = Database::fetchAll($sql);

    $sql2 = "SELECT 
                YEAR(date) AS yr,
                MONTH(date) AS mo,
                DATE_FORMAT(date, '%M %Y') AS label,
                SUM(amount) AS total
            FROM (
                SELECT date, amount FROM income_cash
                UNION ALL
                SELECT date, amount FROM income_card
                UNION ALL
                SELECT date, amount FROM income_roomcharged
            ) i
            GROUP BY yr, mo
            ORDER BY yr DESC, mo DESC
            LIMIT 12";

    $income = Database::fetchAll($sql2);

    return ['expenses' => $expenses, 'income' => $income];
}

function getCategorySummary(): array {
    $cash_sql = "SELECT category AS name, SUM(amount) AS total FROM income_cash GROUP BY category ORDER BY total DESC";
    $card_sql = "SELECT category AS name, SUM(amount) AS total FROM income_card GROUP BY category ORDER BY total DESC";

    $petty_desc = "SELECT description AS name, SUM(amount) AS total FROM petty_expenses GROUP BY description ORDER BY total DESC LIMIT 10";
    $hl_desc    = "SELECT description AS name, SUM(amount) AS total FROM hl_expenses GROUP BY description ORDER BY total DESC LIMIT 10";

    return [
        'cash'  => Database::fetchAll($cash_sql),
        'card'  => Database::fetchAll($card_sql),
        'petty' => Database::fetchAll($petty_desc),
        'hl'    => Database::fetchAll($hl_desc),
    ];
}

function getWeeklySummary(): array {
    $sql = "SELECT 
                week_number, 
                month,
                YEAR(date) AS yr,
                COALESCE(SUM(amount),0) AS total
            FROM (
                SELECT date, amount, week_number, month FROM petty_expenses
                UNION ALL
                SELECT date, amount, week_number, month FROM hl_expenses
            ) e
            GROUP BY week_number, month, yr
            ORDER BY yr DESC, week_number DESC
            LIMIT 12";

    $expWkly = Database::fetchAll($sql);

    $sql2 = "SELECT 
                week_number,
                month,
                YEAR(date) AS yr,
                COALESCE(SUM(amount),0) AS total
            FROM (
                SELECT date, amount, week_number, month FROM income_cash
                UNION ALL
                SELECT date, amount, week_number, month FROM income_card
                UNION ALL
                SELECT date, amount, week_number, month FROM income_roomcharged
            ) i
            GROUP BY week_number, month, yr
            ORDER BY yr DESC, week_number DESC
            LIMIT 12";

    $incWkly = Database::fetchAll($sql2);

    return ['expenses' => $expWkly, 'income' => $incWkly];
}

// ── Pagination ───────────────────────────────────────────────

function paginate(int $total, int $perPage, int $currentPage, string $urlBase): array {
    $totalPages = (int) ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return compact('totalPages', 'currentPage', 'offset', 'total', 'perPage', 'urlBase');
}

function renderPagination(array $p): string {
    if ($p['totalPages'] <= 1) return '';

    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';

    $html .= '<li class="page-item' . ($p['currentPage'] <= 1 ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $p['urlBase'] . '&page=' . ($p['currentPage'] - 1) . '">‹</a></li>';

    $start = max(1, $p['currentPage'] - 2);
    $end   = min($p['totalPages'], $p['currentPage'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $p['urlBase'] . '&page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><a class="page-link">…</a></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $p['currentPage'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $p['urlBase'] . '&page=' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $p['totalPages']) {
        if ($end < $p['totalPages'] - 1) $html .= '<li class="page-item disabled"><a class="page-link">…</a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $p['urlBase'] . '&page=' . $p['totalPages'] . '">' . $p['totalPages'] . '</a></li>';
    }

    $html .= '<li class="page-item' . ($p['currentPage'] >= $p['totalPages'] ? ' disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $p['urlBase'] . '&page=' . ($p['currentPage'] + 1) . '">›</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

// ── Flash Messages ───────────────────────────────────────────

function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = compact('type', 'message');
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = ['success' => '✓', 'danger' => '✕', 'warning' => '⚠', 'info' => 'ℹ'];
    $icon  = $icons[$flash['type']] ?? 'ℹ';
    return '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <span>' . $icon . '</span>
        <span>' . htmlspecialchars($flash['message']) . '</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

// ── CSV Export ───────────────────────────────────────────────

function exportCSV(array $data, array $headers, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($data as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// ── Import CSV / Excel ────────────────────────────────────────

function importCSV(string $filePath, string $table, bool $clearFirst = false): array {
    $results = ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'cleared' => 0];

    // Optionally wipe existing records before importing
    if ($clearFirst) {
        $allowed = ['petty_expenses','hl_expenses','income_cash','income_card','income_roomcharged'];
        if (in_array($table, $allowed)) {
            $count = (int) Database::fetch("SELECT COUNT(*) AS c FROM $table")['c'];
            Database::execute("TRUNCATE TABLE $table");
            $results['cleared'] = $count;
        }
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $results['errors'][] = 'Cannot open file.';
        return $results;
    }

    // Skip BOM on first line if present
    $bom = fread($handle, 3);
    if ($bom !== "ï»¿") rewind($handle);

    // Skip header row
    fgetcsv($handle);
    $line = 2;

    while (($row = fgetcsv($handle)) !== false) {
        // Skip completely blank rows
        if (empty(array_filter($row, fn($c) => trim($c) !== ''))) {
            $line++;
            continue;
        }

        try {
            $rawDate = trim($row[0] ?? '');
            $mysqlDate = parseDateToMySQL($rawDate);

            if (!$mysqlDate) {
                $results['errors'][] = "Line $line: Cannot parse date '" . htmlspecialchars($rawDate) . "' — skipped.";
                $results['skipped']++;
                $line++;
                continue;
            }

            $week  = getWeekNumber($mysqlDate);
            $month = getMonth($mysqlDate);

            if ($table === 'petty_expenses' || $table === 'hl_expenses') {
                $desc   = trim($row[1] ?? '');
                $amount = (float)preg_replace('/[^0-9.\-]/', '', $row[2] ?? '0');
                if (!$desc) {
                    $results['skipped']++;
                    $line++;
                    continue;
                }
                Database::execute(
                    "INSERT INTO $table (date, description, amount, week_number, month) VALUES (?,?,?,?,?)",
                    [$mysqlDate, $desc, $amount, $week, $month]
                );

            } elseif ($table === 'income_cash' || $table === 'income_card') {
                $category = trim($row[1] ?? '');
                $amount   = (float)preg_replace('/[^0-9.\-]/', '', $row[2] ?? '0');
                Database::execute(
                    "INSERT INTO $table (date, category, amount, week_number, month) VALUES (?,?,?,?,?)",
                    [$mysqlDate, $category ?: 'Other', $amount, $week, $month]
                );

            } elseif ($table === 'income_roomcharged') {
                $ref    = trim($row[1] ?? '') ?: null;
                $amount = (float)preg_replace('/[^0-9.\-]/', '', $row[2] ?? '0');
                Database::execute(
                    "INSERT INTO $table (date, room_reference, amount, week_number, month) VALUES (?,?,?,?,?)",
                    [$mysqlDate, $ref, $amount, $week, $month]
                );
            }

            $results['inserted']++;

        } catch (Exception $e) {
            $results['errors'][] = "Line $line: " . $e->getMessage();
        }

        $line++;
    }

    fclose($handle);
    return $results;
}

// ── Category helpers ─────────────────────────────────────────

function getAllCategories(?string $type = null): array {
    if ($type) {
        return Database::fetchAll("SELECT * FROM categories WHERE type = ? ORDER BY name", [$type]);
    }
    return Database::fetchAll("SELECT * FROM categories ORDER BY type, name");
}

// ── Available months for filters ─────────────────────────────

function getAvailableMonths(): array {
    $sql = "SELECT DISTINCT DATE_FORMAT(date,'%Y-%m') AS ym, DATE_FORMAT(date,'%M %Y') AS label
            FROM (
                SELECT date FROM petty_expenses
                UNION SELECT date FROM hl_expenses
                UNION SELECT date FROM income_cash
                UNION SELECT date FROM income_card
                UNION SELECT date FROM income_roomcharged
            ) d
            ORDER BY ym DESC";
    return Database::fetchAll($sql);
}
