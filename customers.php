<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Customer Accounts & Debts';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    try {
        // CSRF Check
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Security Validation Failed.");
        }

        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $type = $_POST['type'] ?? 'Retail';
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, type) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $phone, $type);
            if ($stmt->execute()) {
                $msg = "Customer registered!"; $msg_type = "success";
            }
        }
    } catch (Exception $e) {
        log_error($e, 'ADD_CUSTOMER');
        $msg = "Error adding customer: " . $e->getMessage(); $msg_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    try {
        // CSRF Check
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Security Validation Failed.");
        }

        if (!isAdmin()) {
            throw new Exception("Access Denied: Only admins can delete customers.");
        }
            $cid = intval($_POST['customer_id']);
            
            // First, check if the customer has any outstanding debt
            $stmt_balance = $conn->prepare("SELECT balance FROM view_customer_balances WHERE id = ?");
            $stmt_balance->bind_param("i", $cid);
            $stmt_balance->execute();
            $customer_balance = $stmt_balance->get_result()->fetch_assoc()['balance'];

            if ($customer_balance > 0) {
                throw new Exception("Cannot delete customer: They still have an outstanding debt of " . money($customer_balance) . ".");
            } else {
                // If balance is zero or negative, proceed with deletion
                $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->bind_param("i", $cid);
                if ($stmt->execute()) {
                    $msg = "Customer and payment history removed successfully.";
                    $msg_type = "success";
                }
            }
    } catch (Exception $e) {
        log_error($e, 'DELETE_CUSTOMER');
        $msg = $e->getMessage(); $msg_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
    try {
        // CSRF Check
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Security Validation Failed.");
        }

        $cid = intval($_POST['customer_id']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $type = $_POST['type'] ?? 'Retail';
        if (!empty($name)) {
            $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, type = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $phone, $type, $cid);
            if ($stmt->execute()) {
                $msg = "Customer updated successfully."; $msg_type = "success";
            }
        }
    } catch (Exception $e) {
        log_error($e, 'EDIT_CUSTOMER');
        $msg = "Error updating customer: " . $e->getMessage(); $msg_type = "error";
    }
}

$customers = [];
try {
    $filter = $_GET['filter'] ?? 'all';
    $type_filter = $_GET['type'] ?? '';
    $sql = "SELECT * FROM view_customer_balances";

    if ($filter === 'debt') {
        $sql .= " WHERE balance > 0";
    }

    if ($type_filter && $type_filter !== 'all') {
        $sql .= (strpos($sql, 'WHERE') !== false ? " AND " : " WHERE ") . "type IN ('$type_filter', 'Both')";
    }

    $sql .= " ORDER BY balance DESC";

    $res = $conn->query($sql);
    $customers = $res->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    log_error($e, 'FETCH_CUSTOMERS');
    $msg = "Database Error: " . $e->getMessage() . ". Please visit <a href='db_check.php'>db_check.php</a> to sync your tables.";
    $msg_type = "error";
}

// Fetch edit customer data
$edit_customer = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    try {
        $res_e = $conn->query("SELECT * FROM customers WHERE id = $eid");
        if ($res_e->num_rows) $edit_customer = $res_e->fetch_assoc();
    } catch (Exception $e) { log_error($e, 'FETCH_EDIT_CUSTOMER'); }
}

require_once 'includes/header.php';
?>
<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <div class="card form-card">
        <div class="card-header"><h3>Register New Customer</h3></div>
        <form method="POST">
            <input type="hidden" name="add_customer" value="1">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone">
            </div>
            <div class="form-group">
                <label>Customer Category</label>
                <select name="type">
                    <option value="Retail">Retail (Buying Parts)</option>
                    <option value="Repair">Repair (Fixing Phones)</option>
                    <option value="Both">Both</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Add Customer →</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Customer Ledger <span class="dim small"><?= ucfirst($filter) ?> (<?= $_GET['type'] ?? 'All' ?>)</span></h3>
            <div class="filter-tabs">
                <a href="customers.php?type=all" class="filter-tab <?= ($_GET['type'] ?? '') == 'all' ? 'active' : '' ?>">All</a>
                <a href="customers.php?type=Retail" class="filter-tab <?= ($_GET['type'] ?? '') == 'Retail' ? 'active' : '' ?>">Retail</a>
                <a href="customers.php?type=Repair" class="filter-tab <?= ($_GET['type'] ?? '') == 'Repair' ? 'active' : '' ?>">Repairs</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Type / Contact</th>
                        <th>Current Debt</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr data-customer='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'>
                        <td class="bold"><?= htmlspecialchars($c['name']) ?></td>
                        <td>
                            <span class="tag"><?= $c['type'] ?></span>
                            <span class="dim small" style="margin-left:5px;"><?= htmlspecialchars($c['phone']) ?></span>
                        </td>
                        <td class="mono <?= $c['balance'] > 0 ? 'red-text bold' : 'green-text' ?>">
                            <?= money($c['balance']) ?>
                        </td>
                        <td style="text-align:right">
                            <div class="action-btns" style="justify-content: flex-end;">
                                <a href="customer_details.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline">View Audit Log</a>
                                <button type="button" onclick="openEditCustomerModal(this)" class="btn btn-xs btn-ghost">Edit</button>
                                <?php if (isAdmin()): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this customer? This will permanently remove their record and payment history.')">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-danger">Del</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="4" class="empty-state">No customers registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal-overlay" id="edit-customer-modal" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h3>Edit Customer Info</h3>
      <button class="modal-close" onclick="document.getElementById('edit-customer-modal').style.display='none'">✕</button>
    </div>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="edit_customer">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="customer_id" id="edit-customer-id">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" id="edit-customer-name" required>
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="text" name="phone" id="edit-customer-phone">
      </div>
      <div class="form-group">
        <label>Customer Category</label>
        <select name="type" id="edit-customer-type">
            <option value="Retail">Retail (Buying Parts)</option>
            <option value="Repair">Repair (Fixing Phones)</option>
            <option value="Both">Both</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-customer-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes →</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditCustomerModal(el) {
    const row = el.closest('tr');
    const c = JSON.parse(row.dataset.customer);
    
    $('#edit-customer-id').val(c.id);
    $('#edit-customer-name').val(c.name);
    $('#edit-customer-phone').val(c.phone);
    $('#edit-customer-type').val(c.type).trigger('change');
    
    $('#edit-customer-modal').css('display', 'flex');
}

$(document).ready(function() {
    // Initialize select2 for the customer type in the edit modal
    $('#edit-customer-type').select2({
        dropdownParent: $('#edit-customer-modal'),
        width: '100%'
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>