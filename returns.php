<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Process Return';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.'; $msg_type = 'error';
    } else {
    $product_id    = intval($_POST['product_id'] ?? 0);
    $customer_id   = intval($_POST['customer_id'] ?? 0) ?: null;
    $quantity      = intval($_POST['quantity'] ?? 0);
    $refund_amount = floatval($_POST['refund_amount'] ?? 0);
    $notes         = trim($_POST['notes'] ?? '');
    $uid           = $_SESSION['khadilu_user_id'];

    if (!$product_id || $quantity < 1) {
        $msg = 'Please select a product and valid quantity.'; $msg_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Record the return
            $stmt = $conn->prepare("INSERT INTO stock_returns (product_id, customer_id, user_id, quantity, refund_amount, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiidd", $product_id, $customer_id, $uid, $quantity, $refund_amount, $notes);
            $stmt->execute();

            // 2. Increase inventory stock
            $upd = $conn->prepare("UPDATE products SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?");
            $upd->bind_param("ii", $quantity, $product_id);
            $upd->execute();

            $conn->commit();
            $msg = "✓ Return processed. Inventory updated and customer account credited."; 
            $msg_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            log_error($e, 'PRODUCT_RETURN');
            $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }
    }
}

$products = $conn->query("SELECT id, name, brand, selling_price FROM products WHERE is_deleted = 0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$customers = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Recent Returns
$recent = $conn->query("
    SELECT r.*, p.name as product_name, c.name as customer_name 
    FROM stock_returns r 
    JOIN products p ON r.product_id = p.id 
    LEFT JOIN customers c ON r.customer_id = c.id 
    ORDER BY r.created_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
    <div class="card form-card">
        <div class="card-header"><h3>Process Customer Return</h3></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <div class="form-group">
                <label>Product being Returned *</label>
                <select name="product_id" id="product-select" required>
                    <option value="">— Select Product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                            <?= htmlspecialchars($p['brand'] . ' ' . $p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity Returned</label>
                    <input type="number" name="quantity" value="1" min="1" required>
                </div>
                <div class="form-group">
                    <label>Credit/Refund Value (₦)</label>
                    <input type="number" name="refund_amount" step="0.01" placeholder="0.00">
                    <span class="form-hint">This reduces the customer's debt.</span>
                </div>
            </div>

            <div class="form-group">
                <label>Customer (to credit their debt)</label>
                <select name="customer_id" id="customer-select">
                    <option value="0">Walk-in (No account credit)</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Reason / Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g. Factory defect, wrong model..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Submit Return →</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Return Logs</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Credit</th>
                        <th>Customer</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><div class="bold"><?= htmlspecialchars($r['product_name']) ?></div></td>
                        <td><span class="pill pill-green">+<?= $r['quantity'] ?></span></td>
                        <td class="mono"><?= money($r['refund_amount']) ?></td>
                        <td class="dim"><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></td>
                        <td class="dim small"><?= timeAgo($r['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#product-select, #customer-select').select2({ width: '100%' });
    
    $('#product-select').on('change', function() {
        const price = $(this).find(':selected').data('price');
        if (price) $('input[name="refund_amount"]').val(price);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>