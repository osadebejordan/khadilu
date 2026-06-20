<?php

declare(strict_types=1);

// =============================================
//  Khadilu Phone Hub — Database Configuration
// =============================================

// Ensure the system uses Nigeria's timezone
date_default_timezone_set('Africa/Lagos');

// Security: Disable error display in production to prevent path leakage
define('DEV_MODE', false); // Set to true to enable debugging
define('MAINTENANCE_MODE', false); // Set to true to restrict access to Admins only
error_reporting(DEV_MODE ? E_ALL : 0);
ini_set('display_errors', DEV_MODE ? 1 : 0);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Change if you set a MySQL password
define('DB_NAME', 'khadilu_hub');
define('APP_NAME', 'Khadilu Phone Hub');
define('CURRENCY', '₦');

// Engineer Credentials (used for emergency access and maintenance)
define('ENG_USER', 'khadilu_engineer');
define('ENG_PASS', 'khadilu_dev_2024');

// --- Session Management & Security ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Inactivity Timeout (e.g., 30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['khadilu_user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        session_unset();
        session_destroy();
        header("Location: index.php?msg=expired");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// --- Maintenance Mode Enforcement ---
if (MAINTENANCE_MODE && !isset($skip_maintenance_check)) {
    $user_role = $_SESSION['khadilu_role'] ?? '';
    $current_file = basename($_SERVER['PHP_SELF']);
    if ($user_role !== 'admin' && $current_file !== 'index.php' && $current_file !== 'logout.php') {
        header("Location: index.php?msg=maintenance");
        exit();
    }
}

// Security: Enable strict error reporting but handle it via try-catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Centralized error logging
 */
function log_error(Throwable $e, string $type = 'DATABASE'): void
{
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }

    $user_id = (string)($_SESSION['khadilu_user_id'] ?? 'Guest/System');
    $timestamp = date('Y-m-d H:i:s');
    $message = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();

    $log_entry = sprintf("[%s] [%s] [UID: %s] %s in %s:%d%s", $timestamp, $type, $user_id, $message, $file, $line, PHP_EOL);
    @error_log($log_entry, 3, $log_dir . '/app_errors.log');
}

/**
 * Records administrative and system actions
 */
function logActivity(string $action, ?string $table = null, ?int $record_id = null, ?string $details = null): void
{
    global $conn;
    $user_id = $_SESSION['khadilu_user_id'] ?? null;
    if (!$user_id) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO system_audit_log (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $details, $ip);
    $stmt->execute();
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    log_error($e, 'CONNECTION');
    die('<div style="font-family:sans-serif;padding:40px;background:#080c18;color:#ff6b6b;text-align:center;">
        <h2>System Maintenance</h2>
        <p>We are currently experiencing technical difficulties. Please try again later.</p>
    </div>');
}

// Helper: format money
function money(float|int $amount): string
{
    return CURRENCY . number_format($amount, 2);
}

// Helper: abbreviate large money for dashboard cards
function shortMoney(float|int|string $amount): string
{
    $amount = (float)str_replace(',', '', (string)$amount);

    return match (true) {
        $amount >= 1000000000 => CURRENCY . number_format($amount / 1000000000, 1) . 'B',
        $amount >= 1000000    => CURRENCY . number_format($amount / 1000000, 1) . 'M',
        $amount >= 1000       => CURRENCY . (
            ($val = $amount / 1000) == (int)$val
            ? number_format($val, 0)
            : number_format($val, 1)
        ) . 'K',
        default               => CURRENCY . number_format($amount, 0),
    };
}

/**
 * Calculates margin percentage
 */
function calculateMargin(float|int $cost, float|int $selling): float|int
{
    if ($selling <= 0) return 0;
    $profit = $selling - $cost;
    return ($profit / $selling) * 100;
}

// Helper: time ago
function timeAgo($datetime)
{
    $time = max(0, time() - strtotime($datetime));
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    return date('d M Y', strtotime($datetime));
}

/**
 * Generates a unique random SKU for inventory items
 */
function generateSKU($prefix = 'KH')
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $res = '';
    for ($i = 0; $i < 8; $i++) {
        $res .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $prefix . '-' . $res;
}

/**
 * Standardized Toast Notification Renderer
 */
function renderToast($msg, $type = 'success')
{
    if (!$msg) return '';
    // Basic sanitization to prevent XSS while allowing formatting and links
    $msg = strip_tags((string)$msg, '<a><b><i><strong><em><br><span>');
    $html = '<div class="toast alert-' . $type . '" id="app-toast">';
    $html .= '<div>' . $msg . '</div>';
    $html .= '<div class="close-toast" onclick="this.parentElement.remove()">✕</div>';
    $html .= '</div>';
    $html .= '<script>setTimeout(() => { const t = document.getElementById("app-toast"); if(t) { t.style.opacity = "0"; t.style.transform = "translateY(-20px)"; setTimeout(() => t.remove(), 400); } }, 6000);</script>';
    return $html;
}

// --- CSRF Security Helpers ---
function getCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token)
{
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
