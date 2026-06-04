<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Security: Only admins can manage expenses
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Business Expense Log';
$msg = '';
$msg_type = '';

// --- Handle Add Expense ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.';
        $msg_type = 'error';
    } else {
        try {
            $user_id = $_SESSION['khadilu_user_id'];
            $category = $_POST['category'] ?? 'Others';
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));

            $valid_categories = ['Rent', 'Electricity', 'Salary', 'Marketing', 'Transport', 'Others'];
            if (!in_array($category, $valid_categories)) {
                $category = 'Others';
            }

            if ($amount <= 0) {
                throw new Exception("Please enter a valid expense amount greater than zero.");
            }

            if (empty($expense_date)) {
                $expense_date = date('Y-m-d');
            }

            $stmt = $conn->prepare("INSERT INTO business_expenses (user_id, category, amount, description, expense_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdss", $user_id, $category, $amount, $description, $expense_date);

            if ($stmt->execute()) {
                $insert_id = $conn->insert_id;
                logActivity('ADD_EXPENSE', 'business_expenses', $insert_id, "Logged operating expense: " . money($amount) . " for " . $category);
                $msg = "✓ Success! Operating expense recorded.";
                $msg_type = 'success';
            } else {
                throw new Exception("Failed to save expense details.");
            }
        } catch (Exception $e) {
            log_error($e, 'EXPENSE_ADD');
            $msg = "Error: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// --- Handle Delete Expense ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_expense') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.';
        $msg_type = 'error';
    } else {
        try {
            $id = intval($_POST['expense_id'] ?? 0);
            
            // Retrieve details for the audit log before deleting
            $info_stmt = $conn->prepare("SELECT amount, category FROM business_expenses WHERE id = ?");
            $info_stmt->bind_param("i", $id);
            $info_stmt->execute();
            $info = $info_stmt->get_result()->fetch_assoc();

            if ($info) {
                $stmt = $conn->prepare("DELETE FROM business_expenses WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    logActivity('DELETE_EXPENSE', 'business_expenses', $id, "Deleted operating expense: " . money($info['amount']) . " for " . $info['category']);
                    $msg = "Expense log deleted successfully.";
                    $msg_type = 'success';
                } else {
                    throw new Exception("Failed to delete expense record.");
                }
            } else {
                throw new Exception("Expense record not found.");
            }
        } catch (Exception $e) {
            log_error($e, 'EXPENSE_DELETE');
            $msg = "Error: " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// --- Filtering and Query Logic ---
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date   = $_GET['end_date'] ?? date('Y-m-t');   // Default to last day of current month
$filter_category = $_GET['category'] ?? '';

$where_clauses = ["expense_date BETWEEN ? AND ?"];
$bind_params = [$start_date, $end_date];
$bind_types = 'ss';

if (!empty($filter_category)) {
    $where_clauses[] = "category = ?";
    $bind_params[] = $filter_category;
    $bind_types .= 's';
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Fetch Expenses list
$stmt = $conn->prepare("
    SELECT e.*, u.full_name 
    FROM business_expenses e 
    LEFT JOIN users u ON e.user_id = u.id 
    WHERE $where_sql 
    ORDER BY e.expense_date DESC, e.created_at DESC
");
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Calculate Total Expenses for selected range
$total_stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM business_expenses 
    WHERE $where_sql
");
$total_stmt->bind_param($bind_types, ...$bind_params);
$total_stmt->execute();
$total_expenses = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$categories = ['Rent', 'Electricity', 'Salary', 'Marketing', 'Transport', 'Others'];

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label class="small dim">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" style="height: 34px; padding: 4px 10px;">
            </div>
            <div class="form-group">
                <label class="small dim">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" style="height: 34px; padding: 4px 10px;">
            </div>
            <div class="form-group">
                <label class="small dim">Category</label>
                <select name="category" class="select2-simple" style="height: 34px; padding: 0 5px;">
                    <option value="">— All Categories —</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-top: auto; height: 34px;">Filter Logs</button>
            <?php if ($filter_category || $start_date !== date('Y-m-01') || $end_date !== date('Y-m-t')): ?>
                <a href="expenses.php" class="btn btn-ghost btn-sm" style="margin-top: auto; height: 34px; display: inline-flex; align-items: center;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="toolbar-right">
        <div class="stat-card compact" style="margin-bottom:0; padding:10px 20px; border-left: 3px solid var(--red);">
            <div class="stat-label" style="margin-bottom:2px; font-size:0.65rem;">Total Expenses (Filtered)</div>
            <div class="stat-value mono accent-text" style="font-size:1.3rem; color:var(--red) !important;"><?= money($total_expenses) ?></div>
        </div>
    </div>
</div>

<div class="two-col">
    <!-- Log Expense Form -->
    <div class="card form-card">
        <div class="card-header">
            <h3>Record Operating Expense</h3>
        </div>
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="action" value="add_expense">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

            <div class="form-group">
                <label for="category">Expense Category *</label>
                <select name="category" id="category" required class="select2-simple">
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat ?>"><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount">Amount (₦) *</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label for="expense_date">Date of Expense *</label>
                <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description / Purpose</label>
                <textarea id="description" name="description" rows="3" placeholder="e.g. Fuel for generator, shop rent balance..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Post Expense Log →</button>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="card">
        <div class="card-header">
            <h3>Operating Expenses Log</h3>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Logged By</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td class="mono small dim"><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                        <td>
                            <?php 
                            $c_class = match($e['category']) {
                                'Rent' => 'pill-blue',
                                'Electricity' => 'pill-yellow',
                                'Salary' => 'pill-green',
                                'Marketing' => 'pill-purple',
                                'Transport' => 'pill-dim',
                                default => ''
                            };
                            ?>
                            <span class="pill <?= $c_class ?>"><?= htmlspecialchars($e['category']) ?></span>
                        </td>
                        <td class="small"><?= htmlspecialchars($e['full_name'] ?? 'System') ?></td>
                        <td class="small dim" style="max-width: 200px; word-wrap: break-word;"><?= htmlspecialchars($e['description'] ?: '—') ?></td>
                        <td class="mono bold red-text"><?= money($e['amount']) ?></td>
                        <td style="text-align:right">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this expense log?')" style="display:inline;">
                                <input type="hidden" name="action" value="delete_expense">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">No operating expenses recorded for this period.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2-simple').select2({
        width: '100%',
        closeOnSelect: true
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
