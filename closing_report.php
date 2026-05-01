<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Daily Closing Report';
$date = $_GET['date'] ?? date('Y-m-d');

// Initialize method totals
$methods = ['Cash', 'POS', 'Transfer', 'Other'];
$totals = [];
foreach ($methods as $m) $totals[$m] = 0;

// 1. Aggregate from Sales Transactions
$sales_stmt = $conn->prepare("SELECT payment_method, SUM(total_paid) as amt FROM sales_transactions WHERE DATE(created_at) = ? GROUP BY payment_method");
$sales_stmt->bind_param("s", $date);
$sales_stmt->execute();
$sales_res = $sales_stmt->get_result();
while ($row = $sales_res->fetch_assoc()) {
    $m = in_array($row['payment_method'], $methods) ? $row['payment_method'] : 'Other';
    $totals[$m] += $row['amt'];
}

// 2. Aggregate from Ledger/Repair Payments
$pay_stmt = $conn->prepare("SELECT payment_method, SUM(amount) as amt FROM customer_payments WHERE DATE(created_at) = ? GROUP BY payment_method");
$pay_stmt->bind_param("s", $date);
$pay_stmt->execute();
$pay_res = $pay_stmt->get_result();
while ($row = $pay_res->fetch_assoc()) {
    $m = in_array($row['payment_method'], $methods) ? $row['payment_method'] : 'Other';
    $totals[$m] += $row['amt'];
}

$grand_total = array_sum($totals);

// 3. Fetch Detailed Log for the day
$details_sql = "
    (SELECT created_at, customer_name as party, 'Sale' as category, payment_method, total_paid as amount, notes 
     FROM sales_transactions WHERE DATE(created_at) = ?)
    UNION ALL
    (SELECT cp.created_at, c.name as party, 'Ledger/Repair' as category, cp.payment_method, cp.amount, cp.notes 
     FROM customer_payments cp JOIN customers c ON cp.customer_id = c.id WHERE DATE(cp.created_at) = ?)
    ORDER BY created_at DESC";

$details_stmt = $conn->prepare($details_sql);
$details_stmt->bind_param("ss", $date, $date);
$details_stmt->execute();
$details = $details_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="toolbar no-print">
    <div class="toolbar-left">
        <form method="GET" class="filter-group">
            <div class="form-group" style="margin-bottom:0">
                <label class="small dim">Select Report Date</label>
                <input type="date" name="date" value="<?= $date ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
    <div class="toolbar-right">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
    </div>
</div>

<div class="report-header-print" style="display:none; text-align:center; margin-bottom:30px;">
    <h1 style="color:var(--accent)"><?= APP_NAME ?></h1>
    <h2>Daily Closing Summary</h2>
    <p>Business Date: <?= date('l, d F Y', strtotime($date)) ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card accent">
        <div class="stat-label">Total Cash Inflow</div>
        <div class="stat-value mono"><?= money($totals['Cash']) ?></div>
        <div class="stat-sub">Physical Cash on Hand</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">POS Settlements</div>
        <div class="stat-value mono blue-text"><?= money($totals['POS']) ?></div>
        <div class="stat-sub">Card / Terminal Payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bank Transfers</div>
        <div class="stat-value mono purple"><?= money($totals['Transfer']) ?></div>
        <div class="stat-sub">Direct Bank Credits</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-label">Grand Total</div>
        <div class="stat-value mono accent-text"><?= money($grand_total) ?></div>
        <div class="stat-sub">Combined Revenue for the Day</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Transaction Breakdown — <?= date('d M Y', strtotime($date)) ?></h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Customer/Source</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $row): ?>
                <tr>
                    <td class="mono small dim"><?= date('H:i:s', strtotime($row['created_at'])) ?></td>
                    <td><span class="tag"><?= $row['category'] ?></span></td>
                    <td class="bold"><?= htmlspecialchars($row['party']) ?></td>
                    <td>
                        <?php 
                        $m_class = match($row['payment_method']) {
                            'Cash' => 'pill-green',
                            'POS' => 'pill-blue',
                            'Transfer' => 'pill-yellow',
                            default => ''
                        };
                        ?>
                        <span class="pill <?= $m_class ?>"><?= $row['payment_method'] ?></span>
                    </td>
                    <td class="mono bold accent-text"><?= money($row['amount']) ?></td>
                    <td class="small dim italic"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($details)): ?>
                <tr><td colspan="6" class="empty-state">No financial activity recorded for this date.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .no-print, .sidebar, .sidebar-overlay { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; background: #fff !important; color: #000 !important; }
    .report-header-print { display: block !important; }
    .card, .stat-card { border: 1px solid #ccc !important; box-shadow: none !important; background: #fff !important; }
    .stat-value, .accent-text { color: #000 !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>