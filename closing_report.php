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

// 3. Fetch Daily Expenses
$exp_stmt = $conn->prepare("SELECT SUM(amount) as amt FROM business_expenses WHERE expense_date = ?");
$exp_stmt->bind_param("s", $date);
$exp_stmt->execute();
$total_expenses = $exp_stmt->get_result()->fetch_assoc()['amt'] ?? 0;

$net_flow = $grand_total - $total_expenses;

$exp_list_stmt = $conn->prepare("SELECT category, amount, description FROM business_expenses WHERE expense_date = ? ORDER BY created_at DESC");
$exp_list_stmt->bind_param("s", $date);
$exp_list_stmt->execute();
$daily_expenses = $exp_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Fetch Detailed Log for the day
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
        <div class="stat-value mono" title="<?= money($totals['Cash']) ?>"><?= shortMoney($totals['Cash']) ?></div>
        <div class="stat-sub">Physical Cash on Hand</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">POS Settlements</div>
        <div class="stat-value mono blue-text" title="<?= money($totals['POS']) ?>"><?= shortMoney($totals['POS']) ?></div>
        <div class="stat-sub">Card / Terminal Payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bank Transfers</div>
        <div class="stat-value mono purple" title="<?= money($totals['Transfer']) ?>"><?= shortMoney($totals['Transfer']) ?></div>
        <div class="stat-sub">Direct Bank Credits</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-label">Grand Revenue</div>
        <div class="stat-value mono accent-text" title="<?= money($grand_total) ?>"><?= shortMoney($grand_total) ?></div>
        <div class="stat-sub">Combined Daily Inflow</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-label">Daily Expenses</div>
        <div class="stat-value mono red-text" title="<?= money($total_expenses) ?>"><?= shortMoney($total_expenses) ?></div>
        <div class="stat-sub">Total Outflow Logged</div>
    </div>
    <div class="stat-card <?= $net_flow >= 0 ? 'accent' : 'danger' ?>">
        <div class="stat-label">Net Cash Flow</div>
        <div class="stat-value mono <?= $net_flow >= 0 ? 'green-text' : 'red-text' ?>" title="<?= money($net_flow) ?>"><?= shortMoney($net_flow) ?></div>
        <div class="stat-sub">Net Profit/Loss Today</div>
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
                            $m_class = match ($row['payment_method']) {
                                'Cash'     => 'pill-green',
                                'POS'      => 'pill-blue',
                                'Transfer' => 'pill-yellow',
                                default    => '',
                            };
                            ?>
                            <span class="pill <?= $m_class ?>"><?= $row['payment_method'] ?></span>
                        </td>
                        <td class="mono bold accent-text"><?= money($row['amount']) ?></td>
                        <td class="small dim italic"><?= htmlspecialchars($row['notes'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">No financial activity recorded for this date.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Expense Breakdown — <?= date('d M Y', strtotime($date)) ?></h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 150px;">Category</th>
                    <th>Description</th>
                    <th style="width: 200px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_expenses as $row): ?>
                    <tr>
                        <td>
                            <?php
                            $c_class = match ($row['category']) {
                                'Rent'        => 'pill-blue',
                                'Electricity' => 'pill-yellow',
                                'Salary'      => 'pill-green',
                                'Marketing'   => 'pill-purple',
                                'Transport'   => 'pill-dim',
                                default       => '',
                            };
                            ?>
                            <span class="pill <?= $c_class ?>"><?= htmlspecialchars($row['category']) ?></span>
                        </td>
                        <td class="small dim"><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                        <td class="mono bold red-text"><?= money($row['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($daily_expenses)): ?>
                    <tr>
                        <td colspan="3" class="empty-state">No operating expenses recorded for this date.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    @media print {

        .no-print,
        .sidebar,
        .sidebar-overlay {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            color: #000 !important;
        }

        .report-header-print {
            display: block !important;
        }

        .card,
        .stat-card {
            border: 1px solid #ccc !important;
            box-shadow: none !important;
            background: #fff !important;
        }

        .stat-value,
        .accent-text {
            color: #000 !important;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>