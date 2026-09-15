<?php
/**
 * Shared helper functions
 */

function get_setting(): array
{
    static $settings = null;
    if ($settings === null) {
        $stmt = db()->query("SELECT * FROM store_settings LIMIT 1");
        $settings = $stmt->fetch() ?: [];
    }
    return $settings;
}

function money(float $amount): string
{
    $s = get_setting();
    $symbol = $s['currency_symbol'] ?? '₹';
    return $symbol . number_format($amount, 2);
}

function today(): string
{
    return date('Y-m-d');
}

/**
 * Generates the next invoice number. IMPORTANT: this must always be called
 * from inside a transaction the caller already opened (billing_process.php
 * and api/mobile/sync_sales.php both wrap their whole checkout in one) —
 * PDO does not support nested transactions, so this function does NOT open
 * its own. The row lock only takes effect if the caller's transaction is
 * active, which is the case at both call sites.
 */
function generate_invoice_no(): string
{
    $pdo = db();
    $stmt = $pdo->query("SELECT invoice_prefix, invoice_next_number FROM store_settings LIMIT 1 FOR UPDATE");
    $row = $stmt->fetch();
    $prefix = $row['invoice_prefix'] ?: 'INV';
    $next = (int)$row['invoice_next_number'];
    $invoiceNo = $prefix . '-' . date('ymd') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    $upd = $pdo->prepare("UPDATE store_settings SET invoice_next_number = invoice_next_number + 1");
    $upd->execute();
    return $invoiceNo;
}

function generate_code(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

// ---------------- i18n ----------------
function load_lang(): array
{
    static $lang = null;
    if ($lang === null) {
        $code = $_SESSION['lang'] ?? (get_setting()['default_language'] ?? 'ta');
        $file = __DIR__ . '/../lang/' . $code . '.json';
        if (!file_exists($file)) $file = __DIR__ . '/../lang/en.json';
        $lang = json_decode(file_get_contents($file), true) ?: [];
    }
    return $lang;
}

function t(string $key): string
{
    $lang = load_lang();
    return $lang[$key] ?? $key;
}

// ---------------- input helpers ----------------
function e(?string $val): string
{
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function post(string $key, $default = null)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get(string $key, $default = null)
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------- stock helpers ----------------
function low_stock_products(int $limit = 50): array
{
    $s = get_setting();
    $threshold = (int)($s['low_stock_threshold'] ?? 10);
    $stmt = db()->prepare("SELECT * FROM products WHERE is_active = 1 AND deleted_at IS NULL AND stock_qty <= GREATEST(reorder_level, ?) ORDER BY stock_qty ASC LIMIT ?");
    $stmt->bindValue(1, $threshold, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function expiring_products(int $limit = 50): array
{
    $s = get_setting();
    $days = (int)($s['expiry_alert_days'] ?? 15);
    $stmt = db()->prepare("SELECT * FROM products WHERE is_active = 1 AND deleted_at IS NULL AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY expiry_date ASC LIMIT ?");
    $stmt->bindValue(1, $days, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function adjust_stock(int $productId, float $delta, ?int $userId = null, string $reason = ''): void
{
    $stmt = db()->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
    $stmt->execute([$delta, $productId]);

    if ($reason !== '') {
        $type = $delta >= 0 ? 'add' : 'remove';
        $log = db()->prepare("INSERT INTO stock_adjustments (product_id, adjustment_type, qty, reason, created_by) VALUES (?, ?, ?, ?, ?)");
        $log->execute([$productId, $type, abs($delta), $reason, $userId]);
    }
}

// ---------------- Excel (HTML-table) export ----------------
function export_html_table_as_excel(string $filename, array $headers, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Tamil text opens correctly in Excel
    echo '<table border="1"><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . e($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . e((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
