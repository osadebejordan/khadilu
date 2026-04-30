<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$customer_id = intval($_GET['id'] ?? 0);
if (!$customer_id) {
    header("Location: customers.php");
    exit();
}

$msg = '';
$msg_type = '';

// --- Handle deleting a payment (Admin only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment']) && isAdmin()) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Security Validation Failed."; $msg_type = "error";
    } else {
    $payment_id = intval($_POST['payment_id']);
    $stmt = $conn->prepare("DELETE FROM customer_payments WHERE id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $payment_id, $customer_id);
    $stmt->execute();
    $msg = "Payment entry removed from logs."; $msg_type = "success";
    }
}

// --- Handle recording a debt payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Security Validation Failed."; $msg_type = "error";
    } else {
    $amount = floatval($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'Cash';
    $notes  = trim($_POST['notes'] ?? '');
    $uid    = $_SESSION['khadilu_user_id'];

    if ($amount <= 0) {
        $msg = "Please enter a valid amount.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO customer_payments (customer_id, user_id, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iidss", $customer_id, $uid, $amount, $method, $notes);
        if ($stmt->execute()) {
            $msg = "✓ Payment of " . money($amount) . " recorded successfully.";
            $msg_type = "success";
        } else {
            $msg = "Error: " . $conn->error;
            $msg_type = "error";
        }
    }
    }
}

// --- Fetch Customer Info ---
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header("Location: customers.php");
    exit();
}

$page_title = 'Audit Log: ' . $customer['name'];

// --- Fetch Combined Audit Log ---
// Charges (Sales) vs Credits (Payments + initial deposits)
$sql = "SELECT * FROM (
        (SELECT 
            'sale' as type, 
            o.id,
            o.created_at, 
            CONCAT(p.name, ' (', p.quality, ')') as detail,
            o.total_amount as charge,
            o.amount_paid as credit,
            (o.total_amount - o.amount_paid) as net,
            o.notes
          FROM stock_outflow o 
          JOIN products p ON o.product_id = p.id
          WHERE o.customer_id = ?)
        UNION ALL
        (SELECT 
            'payment' as type, 
            cp.id,
            cp.created_at, 
            'Debt Payment Recieved' as detail,
            0 as charge,
            cp.amount as credit,
            (cp.amount * -1) as net,
            cp.notes
          FROM customer_payments cp
          WHERE cp.customer_id = ?)
        UNION ALL
        (SELECT 
            'return' as type, 
            r.id,
            r.created_at, 
            CONCAT('Returned: ', p.name) as detail,
            0 as charge,
            r.refund_amount as credit,
            (r.refund_amount * -1) as net,
            r.notes
          FROM stock_returns r 
          JOIN products p ON r.product_id = p.id
          WHERE r.customer_id = ?)
        UNION ALL
        (SELECT 
            'repair' as type, 
            rep.id,
            rep.created_at, 
            CONCAT('Repair: ', rep.device_model) as detail,
            rep.estimated_cost as charge,
            0 as credit,
            rep.estimated_cost as net,
            rep.notes
          FROM repairs rep
          WHERE rep.customer_id = ?)
        ) as audit_log ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $customer_id, $customer_id, $customer_id, $customer_id);
$stmt->execute();
$log = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Totals for summary cards
$total_bought = 0;
$total_paid = 0;
foreach($log as $entry) {
    $total_bought += $entry['charge'];
    $total_paid += $entry['credit'];
}
$balance = $total_bought - $total_paid;

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="stats-grid compact">
    <div class="stat-card">
        <div class="stat-label">Total Purchases</div>
        <div class="stat-value mono"><?= money($total_bought) ?></div>
        <div class="stat-sub">Lifetime Value</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Paid</div>
        <div class="stat-value mono green-text"><?= money($total_paid) ?></div>
        <div class="stat-sub">Lifetime Inflow</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-label">Current Balance</div>
        <div class="stat-value mono <?= $balance > 0 ? 'red-text' : 'green-text' ?>"><?= money($balance) ?></div>
        <div class="stat-sub"><?= $balance > 0 ? 'Outstanding Debt' : 'Clear Account' ?></div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h3>Statement of Account</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity Details</th>
                        <th>Charge (₦)</th>
                        <th>Credit (₦)</th>
                        <th>Balance (₦)</th>
                        <?php if(isAdmin()): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $running_bal = 0;
                    foreach ($log as $row): 
                        $running_bal += $row['net'];
                    ?>
                    <tr>
                        <td class="dim small"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                        <td>
                            <div class="bold <?= $row['type'] === 'payment' ? 'green-text' : '' ?>"><?= htmlspecialchars($row['detail']) ?></div>
                            <?php if ($row['notes']): ?><div class="dim small italic"><?= htmlspecialchars($row['notes']) ?></div><?php endif; ?>
                        </td>
                        <td class="mono"><?= $row['charge'] > 0 ? money($row['charge']) : '—' ?></td>
                        <td class="mono green-text"><?= $row['credit'] > 0 ? money($row['credit']) : '—' ?></td>
                        <td class="mono bold <?= $running_bal > 0 ? 'red-text' : 'green-text' ?>">
                            <?= money($running_bal) ?>
                        </td>
                        <?php if(isAdmin()): ?>
                        <td style="text-align:right">
                            <?php if($row['type'] === 'payment'): ?>
                            <form method="POST" onsubmit="return confirm('Remove this payment record?')">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="delete_payment" value="1">
                                <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger">×</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($log)): ?>
                    <tr><td colspan="5" class="empty-state">No transaction history found for this client.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card form-card">
        <div class="card-header"><h3>📥 Record Debt Payment</h3></div>
        <form method="POST">
            <input type="hidden" name="record_payment" value="1">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <div class="form-group">
                <label>Amount Recieved (₦) *</label>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" value="<?= $balance > 0 ? htmlspecialchars(number_format($balance, 2, '.', '')) : '' ?>" required>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option>Cash</option>
                    <option>Transfer</option>
                    <option>POS</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Audit Notes</label>
                <textarea name="notes" rows="3" placeholder="e.g. Cleared balance for iPhone 11 Screen"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Post Payment →</button>
        </form>
        <div class="form-footer">
            <a href="customers.php" class="btn btn-ghost btn-full">← Return to Ledger</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>