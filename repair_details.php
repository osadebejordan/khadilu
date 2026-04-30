<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$repair_id = intval($_GET['id'] ?? 0);
if (!$repair_id) {
    header("Location: repairs.php");
    exit();
}

$msg = '';
$msg_type = '';

// --- Handle Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Security Validation Failed.");
        }

        if (isset($_POST['update_status'])) {
            $status = $_POST['status'];
            $notes  = trim($_POST['notes'] ?? '');
            
            $stmt = $conn->prepare("UPDATE repairs SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $status, $notes, $repair_id);
            if ($stmt->execute()) {
                $msg = "Repair status updated to $status.";
                $msg_type = 'success';
            }
        }

        if (isset($_POST['record_payment'])) {
            $payment = floatval($_POST['payment_amount'] ?? 0);
            if ($payment <= 0) throw new Exception("Please enter a valid payment amount.");

            $conn->begin_transaction();
            // 1. Update repair table
            $stmt = $conn->prepare("UPDATE repairs SET amount_paid = amount_paid + ? WHERE id = ?");
            $stmt->bind_param("di", $payment, $repair_id);
            $stmt->execute();

            // 2. If it's a registered customer, record it in their ledger for documentation
            $res = $conn->query("SELECT customer_id FROM repairs WHERE id = $repair_id")->fetch_assoc();
            if ($res && $res['customer_id']) {
                $uid = $_SESSION['khadilu_user_id'];
                $pay_notes = "Balance payment for Repair Ticket #REP-" . str_pad($repair_id, 4, '0', STR_PAD_LEFT);
                $p_stmt = $conn->prepare("INSERT INTO customer_payments (customer_id, user_id, amount, notes, payment_method) VALUES (?, ?, ?, ?, 'Cash')");
                $p_stmt->bind_param("iids", $res['customer_id'], $uid, $payment, $pay_notes);
                $p_stmt->execute();
            }

            $conn->commit();
            $msg = "Payment of " . money($payment) . " recorded successfully.";
            $msg_type = 'success';
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno == 0) @$conn->rollback();
        log_error($e, 'REPAIR_UPDATE');
        $msg = $e->getMessage();
        $msg_type = 'error';
    }
}

// Fetch Repair Data
$stmt = $conn->prepare("SELECT r.*, u.full_name as technician, p.name as product_name, p.brand as product_brand FROM repairs r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN products p ON r.product_id = p.id WHERE r.id = ?");
$stmt->bind_param("i", $repair_id);
$stmt->execute();
$repair = $stmt->get_result()->fetch_assoc();

if (!$repair) {
    header("Location: repairs.php");
    exit();
}

$balance = $repair['estimated_cost'] - $repair['amount_paid'];
$page_title = 'Repair Ticket #REP-' . str_pad($repair['id'], 4, '0', STR_PAD_LEFT);
require_once 'includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="repairs.php" class="btn btn-ghost btn-sm">← Back to Repairs List</a>
</div>

<?= renderToast($msg, $msg_type) ?>

<div class="stats-grid compact">
    <div class="stat-card">
        <div class="stat-label">Estimated Cost</div>
        <div class="stat-value mono"><?= money($repair['estimated_cost']) ?></div>
        <div class="stat-sub">Quoted Amount</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Amount Paid</div>
        <div class="stat-value mono green-text"><?= money($repair['amount_paid']) ?></div>
        <div class="stat-sub">Deposits & Payments</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-label">Balance Owed</div>
        <div class="stat-value mono <?= $balance > 0 ? 'red-text' : 'green-text' ?>"><?= money($balance) ?></div>
        <div class="stat-sub"><?= $balance > 0 ? 'Collect on Delivery' : 'Fully Paid' ?></div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h3>Job Specifications</h3></div>
        <div style="padding: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label class="dim small bold uppercase">Device Model</label>
                    <?php if ($repair['product_id']): ?>
                        <span class="view-product-link" onclick="viewProduct(<?= $repair['product_id'] ?>)">
                            <p class="bold accent-text" style="font-size: 1.1rem;"><?= htmlspecialchars($repair['device_model']) ?></p>
                        </span>
                    <?php else: ?>
                        <p class="bold accent-text" style="font-size: 1.1rem;"><?= htmlspecialchars($repair['device_model']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="dim small bold uppercase">Intake Date</label>
                    <p><?= date('d M Y, H:i', strtotime($repair['created_at'])) ?></p>
                </div>
                <div style="grid-column: span 2;">
                    <label class="dim small bold uppercase">Issue Description</label>
                    <p style="background: var(--surface-2); padding: 10px; border-radius: 4px; border: 1px solid var(--border); margin-top: 5px;">
                        <?= nl2br(htmlspecialchars($repair['issue_description'])) ?>
                    </p>
                </div>
                <div>
                    <label class="dim small bold uppercase">Customer</label>
                    <p><?= htmlspecialchars($repair['customer_name']) ?></p>
                </div>
                <div>
                    <label class="dim small bold uppercase">Contact Number</label>
                    <p class="bold"><?= htmlspecialchars($repair['customer_phone'] ?: 'No number recorded') ?></p>
                </div>
                <div>
                    <label class="dim small bold uppercase">Received By</label>
                    <p><?= htmlspecialchars($repair['technician'] ?? 'Unknown') ?></p>
                </div>
            </div>
        </div>
        
        <div class="card-divider"></div>
        
        <div style="padding: 20px;">
            <form method="POST">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                
                <div class="form-group">
                    <label>Job Progress / Status</label>
                    <select name="status">
                        <option value="Received" <?= $repair['status'] === 'Received' ? 'selected' : '' ?>>Received (Intake)</option>
                        <option value="In-Progress" <?= $repair['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress (On Bench)</option>
                        <option value="Ready" <?= $repair['status'] === 'Ready' ? 'selected' : '' ?>>Ready (Waiting for Customer)</option>
                        <option value="Delivered" <?= $repair['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered (Picked Up)</option>
                        <option value="Cancelled" <?= $repair['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Technician Notes & Findings</label>
                    <textarea name="notes" rows="4" placeholder="e.g. Replaced IC Chip, screen tested okay..."><?= htmlspecialchars($repair['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Update Job Details →</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>💳 Payment Actions</h3></div>
        <div style="padding: 20px;">
            <?php if ($balance <= 0): ?>
                <div class="empty-state ok" style="padding: 10px;">
                    ✓ This job is fully paid.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="record_payment" value="1">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <div class="form-group">
                        <label>Collection / Balance Payment (₦)</label>
                        <input type="number" name="payment_amount" step="0.01" value="<?= number_format($balance, 2, '.', '') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-outline btn-full">Post Balance Payment</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="card-divider"></div>
        
        <div class="card-header"><h3>Lifecycle Timeline</h3></div>
        <div style="padding: 20px;">
            <div class="timeline" style="border-left: 2px solid var(--border); padding-left: 20px; position: relative;">
                <div style="margin-bottom: 15px;">
                    <div class="bold accent-text" style="font-size: 0.8rem;">CURRENT STATUS</div>
                    <div class="pill <?= $repair['status'] === 'Ready' ? 'pill-blue' : ($repair['status'] === 'Delivered' ? 'pill-green' : 'pill-yellow') ?>">
                        <?= $repair['status'] ?>
                    </div>
                </div>
                <div>
                    <div class="dim small bold uppercase">Last Activity</div>
                    <div class="small"><?= date('d M Y, H:i', strtotime($repair['updated_at'])) ?></div>
                </div>
            </div>
        </div>

        <?php if ($repair['customer_id']): ?>
        <div class="form-footer">
            <a href="customer_details.php?id=<?= $repair['customer_id'] ?>" class="btn btn-ghost btn-full btn-sm">View Customer Audit Log</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('select').select2({ width: '100%' });
});
</script>

<?php require_once 'includes/footer.php'; ?>