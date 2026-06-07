<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Security: Only the main Admin can perform a factory reset
if (!isAdmin()) {
    die("Access Denied: Only administrators can reset the system.");
}

$page_title = "Factory Reset";
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Security Validation Failed.";
        $msg_type = "error";
    } else {
        try {
            // Disable foreign key checks to allow truncating related tables
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");

            $tables_to_clear = [
                'business_expenses',
                'customer_payments',
                'customers',
                'products',
                'sales_transactions',
                'stock_inflow',
                'stock_outflow',
                'stock_returns',
                'system_audit_log'
            ];

            foreach ($tables_to_clear as $table) {
                $conn->query("TRUNCATE TABLE `$table` ");
            }

            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            // Log this final action before the log itself is potentially cleared 
            // (though we just truncated it, we can log the success now)
            logActivity('SYSTEM_FACTORY_RESET', null, null, "All test data cleared by admin.");

            $msg = "System reset successful! All test data and history have been removed.";
            $msg_type = "success";
        } catch (Exception $e) {
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            log_error($e, 'RESET_ACTION');
            $msg = "Error during reset: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrap" style="min-height: auto; padding-top: 50px;">
    <div class="auth-card" style="max-width: 500px; border: 1px solid var(--red);">
        <div class="logo-badge" style="filter: drop-shadow(0 0 10px var(--red));">⚠️</div>
        <h2 style="color: var(--red); margin-bottom: 10px;">Factory Reset</h2>
        <p class="dim" style="margin-bottom: 20px; line-height: 1.6;">
            This action will <strong>permanently delete</strong> all:
        </p>
        <ul class="dim" style="text-align: left; margin-bottom: 25px; padding-left: 20px; font-size: 0.9rem;">
            <li>Inventory Products & Stock Levels</li>
            <li>Sales Transactions & Inflow History</li>
            <li>Customer Records & Ledgers</li>
            <li>Expense Logs & Audit Trails</li>
        </ul>

        <div class="alert alert-error" style="text-align: left; font-size: 0.8rem;">
            <strong>Note:</strong> User accounts, Categories, and Quality settings will be preserved.
        </div>

        <?= renderToast($msg, $msg_type) ?>

        <?php if ($msg_type !== 'success'): ?>
            <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY sure? This cannot be undone.')">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <button type="submit" name="confirm_reset" class="btn btn-primary btn-full" style="background: var(--red); color: white;">
                    Confirm & Wipe All Data
                </button>
            </form>
        <?php else: ?>
            <a href="dashboard.php" class="btn btn-ghost btn-full">Return to Clean Dashboard</a>
            <p class="small dim" style="margin-top: 15px;">Please delete <code>reset_system.php</code> from your server now.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>