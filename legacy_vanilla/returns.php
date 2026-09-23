<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$page_title = 'Process Return';

$msg = '';
$msg_type = '';

// AJAX Lookup for Transaction Items
if (isset($_GET['lookup_txn'])) {
    header('Content-Type: application/json');
    $txn_id = intval($_GET['lookup_txn']);
    $stmt = $conn->prepare("SELECT o.id as outflow_id, o.product_id, o.quantity, o.price_per_unit, o.customer_id, p.name, p.brand, p.quality 
                           FROM stock_outflow o 
                           JOIN products p ON o.product_id = p.id 
                           WHERE o.transaction_id = ?");
    $stmt->bind_param("i", $txn_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// AJAX Lookup for Purchase History (Product + Customer)
if (isset($_GET['lookup_purchases'])) {
    header('Content-Type: application/json');
    $cid = intval($_GET['cid']);
    $pid = intval($_GET['pid']);
    $stmt = $conn->prepare("SELECT o.transaction_id, o.created_at, o.price_per_unit, o.quantity 
                           FROM stock_outflow o 
                           WHERE o.customer_id = ? AND o.product_id = ? 
                           ORDER BY o.created_at DESC LIMIT 5");
    $stmt->bind_param("ii", $cid, $pid);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.'; $msg_type = 'error';
    } else {
    $product_id    = intval($_POST['product_id'] ?? 0);
    $outflow_id    = intval($_POST['outflow_id'] ?? 0) ?: null;
    $customer_id   = intval($_POST['customer_id'] ?? 0) ?: null;
    $quantity      = intval($_POST['quantity'] ?? 0);
    $refund_amount = floatval($_POST['refund_amount'] ?? 0);
    $is_defective  = isset($_POST['is_defective']) ? 1 : 0;
    $notes         = trim($_POST['notes'] ?? '');
    $return_date   = $_POST['return_date'] ?? date('Y-m-d');
    $uid           = $_SESSION['khadilu_user_id'];

    if (!$product_id || $quantity < 1) {
        $msg = 'Please select a product and valid quantity.'; $msg_type = 'error';
    } else {
        // Just record the return as Pending. No stock/ledger changes yet.
        $final_ts = $return_date . ' ' . date('H:i:s');
        $stmt = $conn->prepare("INSERT INTO stock_returns (product_id, outflow_id, customer_id, user_id, quantity, refund_amount, notes, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iiiiidss", $product_id, $outflow_id, $customer_id, $uid, $quantity, $refund_amount, $notes, $final_ts);
        
        if ($stmt->execute()) {
            $msg = "✓ Return request submitted for Admin approval."; 
            $msg_type = 'success';
        } else {
            $msg = "Error submitting return request.";
            $msg_type = 'error';
        }
    }
    }
}

// --- Handle Admin Approval/Rejection ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin() && isset($_POST['update_return_status'])) {
    $rid = intval($_POST['return_id']);
    $status = $_POST['new_status']; // 'Approved' or 'Rejected'

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT product_id, quantity, status, notes FROM stock_returns WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $ret = $stmt->get_result()->fetch_assoc();

        if ($ret && $ret['status'] === 'Pending') {
            $stmt = $conn->prepare("UPDATE stock_returns SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $rid);
            $stmt->execute();

            if ($status === 'Approved') {
                // Only update stock if NOT explicitly marked as defective in notes or via a flag
                if (stripos($ret['notes'], '[DEFECTIVE]') === false) {
                    $conn->query("UPDATE products SET current_stock = current_stock + {$ret['quantity']}, updated_at = NOW() WHERE id = {$ret['product_id']}");
                }
            }
            $conn->commit();
            $msg = "Return record marked as $status."; $msg_type = 'success';
        }
    } catch (Exception $e) { $conn->rollback(); log_error($e, 'RETURN_APPROVAL'); $msg = "Error: " . $e->getMessage(); }
}

$pre_cust_id = intval($_GET['customer_id'] ?? 0);
$products = $conn->query("SELECT id, name, brand, quality, category, selling_price FROM products WHERE is_deleted = 0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$customers = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Recent Returns
$sql_log = "SELECT r.*, p.name as product_name, p.quality, c.name as customer_name 
            FROM stock_returns r JOIN products p ON r.product_id = p.id 
            LEFT JOIN customers c ON r.customer_id = c.id 
            ORDER BY r.created_at DESC";
$recent = $conn->query($sql_log . " LIMIT 20")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <div class="card form-card">
        <div class="card-header">
            <h3>Process Customer Return</h3>
            <div class="search-box-wrapper" style="max-width: 180px;">
                <input type="number" id="txn-lookup-id" placeholder="Receipt #ID">
                <button type="button" onclick="lookupTransaction()" class="btn btn-primary btn-xs">Find</button>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="outflow_id" id="hidden-outflow-id">
            
            <div class="form-group">
                <label id="product-label">Product being Returned *</label>
                <select name="product_id" id="product-select" required>
                    <option value="">— Select Product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                            <?= htmlspecialchars($p['brand'] . ' ' . $p['name']) ?> [<?= htmlspecialchars($p['quality']) ?>] — <?= htmlspecialchars($p['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="txn-hint" class="small blue-text bold" style="margin-top:5px; display:none;">Linked to Receipt #<span id="hint-id"></span></div>
            </div>
            
            <div id="purchase-history-area" style="display:none; margin-bottom: 15px; padding: 10px; background: var(--accent-2); border: 1px dashed var(--accent); border-radius: 8px;">
                <label class="small bold accent-text" style="display:block; margin-bottom:5px;">Found in Purchase History:</label>
                <div id="purchase-options" style="display:flex; flex-wrap:wrap; gap:8px;">
                    <!-- Buttons injected via JS -->
                </div>
                <span class="form-hint">Click a date above to auto-fill the original sale details.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Qty <span id="max-qty-hint" class="dim small"></span></label>
                    <input type="number" id="ret-qty" name="quantity" value="1" min="1" required>
                </div>
                <div class="form-group">
                    <label>Credit Value (₦)</label>
                    <input type="number" name="refund_amount" step="0.01" placeholder="0.00">
                    <span class="form-hint">This reduces the customer's debt.</span>
                </div>
            </div>

            <div class="form-group">
                <label>Date of Return</label>
                <input type="date" name="return_date" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label>Customer (to credit their debt)</label>
                <select name="customer_id" id="customer-select">
                    <option value="0">Walk-in (No account credit)</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $pre_cust_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Condition & Notes</label>
                <div style="display:flex; gap:15px; margin-bottom:8px;">
                    <label class="small" style="display:flex; align-items:center; gap:5px; cursor:pointer;">
                        <input type="checkbox" name="is_defective" id="defective-check"> <span class="red-text">Item is Defective</span>
                    </label>
                    <span class="dim small italic">(Defective items don't return to stock)</span>
                </div>
                <textarea name="notes" id="ret-notes" rows="2" placeholder="e.g. Factory defect, wrong model..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Submit Return →</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Activity & Approvals</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Date</th>
                        <?php if(isAdmin()): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td>
                            <span class="view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
                                <?= htmlspecialchars($r['product_name']) ?>
                            </span>
                            <div class="dim small"><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></div>
                        </td>
                        <td><span class="pill pill-green">+<?= $r['quantity'] ?></span></td>
                        <td>
                            <?php $p_c = $r['status'] === 'Approved' ? 'pill-green' : ($r['status'] === 'Rejected' ? 'pill-red' : 'pill-yellow'); ?>
                            <span class="pill <?= $p_c ?>"><?= $r['status'] ?></span>
                        </td>
                        <td class="dim small"><?= date('d M, H:i', strtotime($r['created_at'])) ?></td>
                        <?php if(isAdmin()): ?>
                        <td>
                            <?php if($r['status'] === 'Pending'): ?>
                                <form method="POST" style="display:flex; gap:4px;">
                                    <input type="hidden" name="update_return_status" value="1">
                                    <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="new_status" value="Approved" class="btn btn-xs btn-primary">Approve</button>
                                    <button type="submit" name="new_status" value="Rejected" class="btn btn-xs btn-danger">Reject</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#product-select, #customer-select').select2({
        width: '100%',
        closeOnSelect: true
    });
    
    function updateRefundValue() {
        const selected = $('#product-select').find(':selected');
        const unitPrice = parseFloat(selected.data('price')) || 0;
        const qty = parseInt($('input[name="quantity"]').val()) || 0;
        
        if (unitPrice > 0) {
            $('input[name="refund_amount"]').val((unitPrice * qty).toFixed(2));
        }
    }

    $('#product-select').on('change', updateRefundValue);
    $('input[name="quantity"]').on('input change', updateRefundValue);

    // Auto-lookup purchase history when Customer + Product are selected
    $('#product-select, #customer-select').on('change', function() {
        const cid = $('#customer-select').val();
        const pid = $('#product-select').val();
        const $area = $('#purchase-history-area');
        const $container = $('#purchase-options');

        if (cid > 0 && pid > 0) {
            $.getJSON('returns.php?lookup_purchases=1&cid=' + cid + '&pid=' + pid, function(data) {
                if (data.length > 0) {
                    $container.empty();
                    data.forEach(txn => {
                        const dateStr = new Date(txn.created_at).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'});
                        const btn = $('<button type="button" class="btn btn-xs btn-ghost"></button>')
                            .text(dateStr + ' (₦' + parseFloat(txn.price_per_unit).toLocaleString() + ')')
                            .attr('onclick', `applyPurchaseSelection(${txn.transaction_id}, ${txn.price_per_unit}, ${txn.quantity})`);
                        $container.append(btn);
                    });
                    $area.fadeIn();
                } else {
                    $area.hide();
                }
            });
        } else {
            $area.hide();
        }
    });

    window.lookupTransaction = function() {
        const txnId = $('#txn-lookup-id').val();
        if(!txnId) return;

        $.getJSON('returns.php?lookup_txn=' + txnId, function(data) {
            if(data.length === 0) {
                alert("Transaction not found or has no items.");
                return;
            }
            
            // For simplicity in this UI, we pick the first item. 
            // Ideally, you'd show a modal to choose the item if there are many.
            const item = data[0];
            
            $('#product-select').val(item.product_id).trigger('change');
            $('#hidden-outflow-id').val(item.outflow_id);
            $('#ret-qty').val(item.quantity).attr('max', item.quantity);
            $('#max-qty-hint').text('(Sold: ' + item.quantity + ')');
            $('#customer-select').val(item.customer_id || 0).trigger('change');
            $('input[name="refund_amount"]').val((item.price_per_unit * item.quantity).toFixed(2));
            
            $('#txn-hint').show();
            $('#hint-id').text(txnId);
            $('#product-label').text('Product from Receipt #' + txnId);
        });
    };

    window.applyPurchaseSelection = function(txnId, price, qty) {
        $('#txn-lookup-id').val(txnId);
        lookupTransaction(); // Re-use the existing lookup to fill everything else
        
        // Visual feedback
        $('#purchase-options .btn').removeClass('btn-primary').addClass('btn-ghost');
        event.target.classList.remove('btn-ghost');
        event.target.classList.add('btn-primary');
    };

    $('#defective-check').on('change', function() {
        const notes = $('#ret-notes');
        if(this.checked) {
            if(!notes.val().includes('[DEFECTIVE]')) {
                notes.val('[DEFECTIVE] ' + notes.val());
            }
        } else {
            notes.val(notes.val().replace('[DEFECTIVE] ', ''));
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>