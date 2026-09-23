<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Repairs Management';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_repair') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Security Validation Failed.";
        $msg_type = "error";
    } else {

        try {
            $product_id    = intval($_POST['product_id'] ?? 0) ?: null; // New field
            $customer_id   = intval($_POST['customer_id'] ?? 0) ?: null;
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_phone = trim($_POST['customer_phone'] ?? '');
            $category      = trim($_POST['category'] ?? '');
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
            if ($product_id && (empty($device) || empty($category))) {
                $p_stmt = $conn->prepare("SELECT name, brand, category FROM products WHERE id = ?");
                $p_stmt->bind_param("i", $product_id);
                $p_stmt->execute();
                $p_res = $p_stmt->get_result()->fetch_assoc();
                if ($p_res) {
                    if (empty($device)) $device = $p_res['brand'] . ' ' . $p_res['name'];
                    if (empty($category)) $category = $p_res['category'];
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
                        throw new Exception("Stock Error: The selected part is no longer available in inventory.");
                    }
                }

                $stmt = $conn->prepare("INSERT INTO repairs (customer_id, product_id, customer_name, customer_phone, device_model, category, issue_description, estimated_cost, amount_paid, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssssddi", $customer_id, $product_id, $customer_name, $customer_phone, $device, $category, $issue, $cost, $paid, $uid);

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
            $msg = $e->getMessage();
            $msg_type = 'error';
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
    $id_search = (str_starts_with(strtoupper($search), '#REP-')) ? intval(substr($search, 5)) : (is_numeric($search) && strlen($search) < 5 ? (int)$search : 0);
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
$stmt = $conn->prepare("SELECT r.*, p.quality FROM repairs r LEFT JOIN products p ON r.product_id = p.id $where_sql ORDER BY r.created_at DESC");
if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
}
$stmt->execute();
$repairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$customers = $conn->query("SELECT id, name, phone FROM customers WHERE type IN ('Repair', 'Both') ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch products available for repair (in stock)
// Also fetch count of ready repairs for the tab badge
$ready_repairs_count = $conn->query("SELECT COUNT(*) AS c FROM repairs WHERE status = 'Ready'")->fetch_assoc()['c'] ?? 0;

$products_for_repair = $conn->query("SELECT id, name, brand, current_stock, category, selling_price FROM products WHERE is_deleted = 0 AND current_stock > 0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

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
                <a href="repairs.php?status=<?= urlencode($filter_status) ?>" class="btn btn-ghost btn-xs">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="toolbar-right">
        <div class="filter-tabs">
            <?php $link_params = ($search ? '&q=' . urlencode($search) : ''); ?>
            <a href="?status=active<?= $link_params ?>" class="filter-tab <?= $filter_status === 'active' ? 'active' : '' ?>">Ongoing</a>
            <a href="?status=Ready<?= $link_params ?>" class="filter-tab yellow <?= $filter_status === 'Ready' ? 'active' : '' ?>">Ready (<?= $ready_repairs_count ?>)</a>
            <a href="?status=Delivered<?= $link_params ?>" class="filter-tab <?= $filter_status === 'Delivered' ? 'active' : '' ?>">Completed</a>
            <a href="?status=all<?= $link_params ?>" class="filter-tab <?= $filter_status === 'all' ? 'active' : '' ?>">All</a>
        </div>
        <button class="btn btn-primary" onclick="openNewJobModal()">+ New Job</button>
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
                        <td class="mono small">#REP-<?= str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div class="bold accent-text">
                                <?= htmlspecialchars($r['device_model']) ?>
                                <?php if (!empty($r['quality'])): ?> <span class="bold" style="color:var(--text); opacity:0.8">[<?= htmlspecialchars($r['quality']) ?>]</span><?php endif; ?>
                            </div>
                            <div class="tag" style="font-size:0.65rem; padding:1px 5px;"><?= htmlspecialchars($r['category'] ?? 'Repair') ?></div>
                            <div class="dim small"><?= htmlspecialchars($r['issue_description']) ?></div>
                        </td>
                        <td>
                            <div class="bold"><?= htmlspecialchars($r['customer_name']) ?></div>
                            <?php if ($r['customer_phone']): ?>
                                <div class="small dim mono"><?= htmlspecialchars($r['customer_phone']) ?></div>
                            <?php endif; ?>
                            <div class="dim small"><?= timeAgo($r['created_at']) ?></div>
                        </td>
                        <td>
                            <?php
                            $p_class = match ($r['status']) {
                                'Ready'       => 'pill-blue',
                                'Delivered'   => 'pill-green',
                                'Cancelled'   => 'pill-red',
                                'In-Progress' => 'pill-yellow',
                                default       => '',
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
                <?php if (empty($repairs)): ?><tr>
                        <td colspan="6" class="empty-state">No repair jobs found.</td>
                    </tr><?php endif; ?>
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
                <div class="form-group" style="grid-column: span 2; background: var(--surface-2); padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                    <label class="small dim" style="margin-bottom: 8px; display: block;">Narrow down Inventory (Filters)</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 12px;">
                        <select id="modal-brand-filter" class="select2-simple" style="flex: 1;">
                            <option value="">— All Brands —</option>
                            <?php
                            $modal_brands = array_unique(array_column($products_for_repair, 'brand'));
                            sort($modal_brands);
                            foreach ($modal_brands as $b):
                            ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="modal-category-filter" class="select2-simple" style="flex: 1;">
                            <option value="">— All Categories —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label>Select Part/Device *</label>
                    <select name="product_id" id="repair-product-select">
                        <option value="">— Select from Inventory —</option>
                        <?php foreach ($products_for_repair as $p): ?>
                            <option value="<?= $p['id'] ?>" data-brand="<?= htmlspecialchars($p['brand']) ?>" data-category="<?= htmlspecialchars($p['category']) ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['selling_price'] ?>">
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
                        <?php foreach ($customers as $c): ?>
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
                <div class="form-group"><label>Estimated Cost (₦)</label><input type="number" name="estimated_cost" id="repair-cost-input" step="0.01" placeholder="0.00" value=""></div>
                <div class="form-group"><label>Initial Deposit (₦)</label><input type="number" name="amount_paid" step="0.01" placeholder="0.00" value=""></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('repair-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Receive Device</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Modal Select2s
        $('#cust-sel, #repair-product-select').select2({
            dropdownParent: $('#repair-modal .modal'),
            width: '100%',
            closeOnSelect: true
        });
        $('#modal-brand-filter, #modal-category-filter').select2({
            dropdownParent: $('#repair-modal .modal'),
            width: '100%',
            closeOnSelect: true
        });

        // Handle dynamic device model input based on product selection
        $('#repair-product-select').on('change', function() {
            const selectedOption = $(this).find(':selected');
            const deviceModelInput = $('#repair-device-model');
            const deviceModelHint = $('#device-model-hint');
            const costInput = $('#repair-cost-input');

            if (selectedOption.val()) {
                const productName = selectedOption.data('name');
                const productBrand = selectedOption.data('brand');
                const productPrice = selectedOption.data('price');

                deviceModelInput.val(`${productBrand} ${productName}`).prop('readonly', true);
                deviceModelHint.text('Pre-filled from inventory.').css('color', 'var(--green)');
                costInput.val(parseFloat(productPrice).toFixed(2));
            } else {
                deviceModelInput.val('').prop('readonly', false);
                deviceModelHint.text('e.g. Samsung S22 Ultra').css('color', '');
            }
        }).trigger('change'); // Trigger on load to set initial state

        // Consolidate modal opening and resetting
        window.openNewJobModal = function() {
            const $form = $('#repair-modal form');
            $form[0].reset();

            // Clear visual states not handled by native reset
            $('#repair-device-model').prop('readonly', false);
            $('#device-model-hint').text('').css('color', '');

            // Reset Select2s silently then refresh modal inventory
            $('#cust-sel').val('0').trigger('change.select2');
            $('#repair-product-select, #modal-brand-filter, #modal-category-filter').val('').trigger('change.select2');

            filterModalProducts();
            $('#repair-modal').css('display', 'flex');
        };

        // Modal Inventory Filtering Logic
        const allRepairProducts = <?= json_encode($products_for_repair) ?>;

        function filterModalProducts() {
            const brand = $('#modal-brand-filter').val();
            const cat = $('#modal-category-filter').val();
            const $select = $('#repair-product-select');

            let filtered = allRepairProducts.filter(p => {
                return (!brand || p.brand === brand) && (!cat || p.category === cat);
            });

            $select.empty().append('<option value="">— Select from Inventory —</option>');
            filtered.forEach(p => {
                const option = new Option(`${p.name} (${p.brand}) - ${p.current_stock} in stock`, p.id, false, false);
                option.setAttribute('data-brand', p.brand);
                option.setAttribute('data-category', p.category);
                option.setAttribute('data-name', p.name);
                $select.append(option);
            });

            // Refresh Select2 visual state
            $select.trigger('change.select2');

            // If only one item remains after filtering, auto-select it for speed
            if (filtered.length === 1) {
                $select.val(filtered[0].id).trigger('change');
            }
        }

        $('#modal-brand-filter, #modal-category-filter').on('change', filterModalProducts);


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