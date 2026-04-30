<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Repairs Management';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_repair') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Security Validation Failed."; $msg_type = "error";
    } else {

        try {
            $product_id    = intval($_POST['product_id'] ?? 0) ?: null; // New field
            $customer_id   = intval($_POST['customer_id'] ?? 0) ?: null;
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_phone = trim($_POST['customer_phone'] ?? '');
            $device        = trim($_POST['device_model'] ?? '');
            $issue         = trim($_POST['issue_description'] ?? '');
            $cost          = floatval($_POST['estimated_cost'] ?? 0);
            $paid          = floatval($_POST['amount_paid'] ?? 0);
            $uid           = $_SESSION['khadilu_user_id'];

            if ($customer_id) {
                $c_stmt = $conn->prepare("SELECT name, phone FROM customers WHERE id = ?");
                $c_stmt->bind_param("i", $customer_id);
                $c_stmt->execute();
                $c_res = $c_stmt->get_result()->fetch_assoc();
                if ($c_res) {
                    $customer_name = $c_res['name'];
                    $customer_phone = $c_res['phone'];
                }
            }

            // Fallback: If an inventory item is picked but device name is missing from POST (due to being disabled)
            if ($product_id && empty($device)) {
                $p_stmt = $conn->prepare("SELECT name, brand FROM products WHERE id = ?");
                $p_stmt->bind_param("i", $product_id);
                $p_stmt->execute();
                $p_res = $p_stmt->get_result()->fetch_assoc();
                if ($p_res) {
                    $device = $p_res['brand'] . ' ' . $p_res['name'];
                }
            }

            if (empty($customer_name) || empty($device)) {
                throw new Exception("Customer name and Device model are required.");
            }

            $conn->begin_transaction();
            try {
                // Decrement stock if an inventory product is selected
                if ($product_id) {
                    $stock_stmt = $conn->prepare("UPDATE products SET current_stock = current_stock - 1 WHERE id = ? AND current_stock > 0");
                    $stock_stmt->bind_param("i", $product_id);
                    if (!$stock_stmt->execute() || $conn->affected_rows === 0) {
                        throw new Exception("Failed to decrement stock for selected product or product is out of stock.");
                    }
                }

                $stmt = $conn->prepare("INSERT INTO repairs (customer_id, product_id, customer_name, customer_phone, device_model, issue_description, estimated_cost, amount_paid, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissssddi", $customer_id, $product_id, $customer_name, $customer_phone, $device, $issue, $cost, $paid, $uid);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to register repair: " . $stmt->error);
                }

                $new_repair_id = $conn->insert_id;
                $msg = "Repair job for " . htmlspecialchars($device) . " registered successfully!";
                $msg_type = 'success';

                // Log the stock decrement after getting the repair ID
                if ($product_id) {
                    logActivity('STOCK_DECREMENT', 'products', $product_id, "Product removed from stock for repair #REP-" . str_pad($new_repair_id, 4, '0', STR_PAD_LEFT));
                }

                // If an initial deposit was made, record it in the ledger so the audit log stays balanced
                if ($paid > 0 && $customer_id) {
                    $pay_notes = "Initial Deposit for Repair Ticket #REP-" . str_pad($new_repair_id, 4, '0', STR_PAD_LEFT);
                    $p_stmt = $conn->prepare("INSERT INTO customer_payments (customer_id, user_id, amount, notes, payment_method) VALUES (?, ?, ?, ?, 'Cash')");
                    $p_stmt->bind_param("iids", $customer_id, $uid, $paid, $pay_notes);
                    if (!$p_stmt->execute()) {
                        throw new Exception("Failed to record repair payment: " . $p_stmt->error);
                    }
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            log_error($e, 'REPAIR_INTAKE');
            $msg = $e->getMessage(); $msg_type = 'error';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? 'active';

$where_conditions = [];
$bind_params = [];
$bind_types = '';

// Add status filter conditions
if ($filter_status === 'active') {
    $where_conditions[] = "status NOT IN ('Delivered', 'Cancelled')";
} elseif ($filter_status !== 'all') {
    $where_conditions[] = "status = ?";
    $bind_params[] = $filter_status;
    $bind_types .= 's';
}

if ($search) {
    $id_search = (str_starts_with(strtoupper($search), '#REP-')) ? intval(substr($search, 5)) : (is_numeric($search) && strlen($search) < 5 ? intval($search) : 0);
    $name_search = "%$search%";
    if ($id_search > 0) {
        $where_conditions[] = "(id = ? OR customer_name LIKE ? OR device_model LIKE ?)";
        $bind_params[] = $id_search;
        $bind_params[] = $name_search;
        $bind_params[] = $name_search;
        $bind_types .= 'iss';
    } else {
        $where_conditions[] = "(customer_name LIKE ? OR device_model LIKE ?)";
        $bind_params[] = $name_search;
        $bind_params[] = $name_search;
        $bind_types .= 'ss';
    }
}
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}
$stmt = $conn->prepare("SELECT * FROM repairs $where_sql ORDER BY created_at DESC");
if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$repairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$customers = $conn->query("SELECT id, name, phone FROM customers WHERE type IN ('Repair', 'Both') ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch products available for repair (in stock)
$products_for_repair = $conn->query("SELECT id, name, brand, current_stock FROM products WHERE is_deleted = 0 AND current_stock > 0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';

echo renderToast($msg, $msg_type);
?>

<div class="toolbar">
    <div class="toolbar-left">
        <form class="filter-group" method="GET">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
            <div class="search-box-wrapper">
                <input type="text" name="q" placeholder="Ticket # or Customer..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </div>
            <?php if ($search): ?>
                <a href="repairs.php?status=<?= urlencode($filter_status) ?>" class="dim small" style="margin-left:5px; text-decoration:underline;">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="toolbar-right">
        <div class="filter-tabs">
            <a href="?status=active<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-tab <?= $filter_status === 'active' ? 'active' : '' ?>">Ongoing</a>
            <a href="?status=Ready<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-tab yellow <?= $filter_status === 'Ready' ? 'active' : '' ?>">Ready</a>
            <a href="?status=Delivered<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-tab <?= $filter_status === 'Delivered' ? 'active' : '' ?>">Completed</a>
            <a href="?status=all<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-tab <?= $filter_status === 'all' ? 'active' : '' ?>">All</a>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('repair-modal').style.display='flex'">+ New Job</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Device / Issue</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($repairs as $r): 
                    $bal = $r['estimated_cost'] - $r['amount_paid'];
                ?>
                <tr>
                    <td class="mono small">#REP-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <?php if ($r['product_id']): ?>
                            <div class="bold accent-text"><?= htmlspecialchars($r['device_model']) ?></div>
                        <?php else: ?>
                            <div class="bold accent-text"><?= htmlspecialchars($r['device_model']) ?></div>
                        <?php endif; ?>
                        <div class="dim small"><?= htmlspecialchars($r['issue_description']) ?></div>
                    </td>
                    <td>
                        <div class="bold"><?= htmlspecialchars($r['customer_name']) ?></div>
                        <?php if($r['customer_phone']): ?>
                            <div class="small dim mono"><?= htmlspecialchars($r['customer_phone']) ?></div>
                        <?php endif; ?>
                        <div class="dim small"><?= timeAgo($r['created_at']) ?></div>
                    </td>
                    <td>
                        <?php 
                        $p_class = match($r['status']) {
                            'Ready' => 'pill-blue',
                            'Delivered' => 'pill-green',
                            'Cancelled' => 'pill-red',
                            'In-Progress' => 'pill-yellow',
                            default => ''
                        };
                        ?>
                        <span class="pill <?= $p_class ?>"><?= $r['status'] ?></span>
                    </td>
                    <td class="mono <?= $bal > 0 ? 'red-text' : 'green-text' ?>">
                        <?= $bal > 0 ? money($bal) : 'Paid' ?>
                    </td>
                    <td>
                        <a href="repair_details.php?id=<?= $r['id'] ?>" class="btn btn-xs btn-outline">Manage</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($repairs)): ?><tr><td colspan="6" class="empty-state">No repair jobs found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Repair Intake Modal -->
<div class="modal-overlay" id="repair-modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-header">
            <h3>New Repair Entry</h3>
            <button class="modal-close" onclick="document.getElementById('repair-modal').style.display='none'">✕</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="add_repair">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Inventory Product (Optional)</label>
                    <select name="product_id" id="repair-product-select">
                        <option value="">— Select from Inventory —</option>
                        <?php foreach($products_for_repair as $p): ?>
                            <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-brand="<?= htmlspecialchars($p['brand']) ?>">
                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['brand']) ?>) - <?= $p['current_stock'] ?> in stock
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">If fixing an item from your stock.</span>
                </div>
                <div class="form-group">
                    <label>Registered Customer</label>
                    <select name="customer_id" id="cust-sel">
                        <option value="0">Walk-in Customer</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Walk-in Name</label>
                    <input type="text" name="customer_name" id="walk-in-name" placeholder="If not registered">
                </div>
                <div class="form-group">
                    <label>Customer Phone</label>
                    <input type="text" name="customer_phone" id="walk-in-phone" placeholder="e.g. 08012345678">
                </div>
            </div>

            <div class="form-group">
                <label>Device Model *</label>
                <input type="text" name="device_model" id="repair-device-model" required placeholder="e.g. Samsung S22 Ultra">
                <span class="form-hint" id="device-model-hint"></span>
            </div>
            
            <div class="form-group">
                <label>Fault / Issue Description *</label>
                <textarea name="issue_description" rows="2" required placeholder="e.g. Cracked screen, Charging port issues..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group"><label>Estimated Cost (₦)</label><input type="number" name="estimated_cost" step="0.01" placeholder="0.00"></div>
                <div class="form-group"><label>Initial Deposit (₦)</label><input type="number" name="amount_paid" step="0.01" placeholder="0.00"></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('repair-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Receive Device →</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#cust-sel').select2({ dropdownParent: $('#repair-modal'), width: '100%' });
    $('#repair-product-select').select2({ dropdownParent: $('#repair-modal'), width: '100%' });

    // Handle dynamic device model input based on product selection
    $('#repair-product-select').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const deviceModelInput = $('#repair-device-model');
        const deviceModelHint = $('#device-model-hint');

        if (selectedOption.val()) {
            const productName = selectedOption.data('name');
            const productBrand = selectedOption.data('brand');
            deviceModelInput.val(`${productBrand} ${productName}`).prop('readonly', true);
            deviceModelHint.text('Pre-filled from inventory.').css('color', 'var(--green)');
        } else {
            deviceModelInput.val('').prop('readonly', false);
            deviceModelHint.text('e.g. Samsung S22 Ultra').css('color', '');
        }
    }).trigger('change'); // Trigger on load to set initial state

    $('#cust-sel').on('change', function() {
        const isWalkIn = $(this).val() == "0";
        if (!isWalkIn) {
            $('#walk-in-name').prop('readonly', true).val('Account Selected');
            $('#walk-in-phone').prop('readonly', true).val('Account Selected');
        } else {
            $('#walk-in-name').prop('readonly', false).val('');
            $('#walk-in-phone').prop('readonly', false).val('');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>