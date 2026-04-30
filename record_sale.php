<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Record Sale';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed. Please try again.'; $msg_type = 'error';
    } else {

    // Handle Register and Add Stock (for new products found during search)
    if (isset($_POST['action']) && $_POST['action'] === 'register_and_add_stock' && isAdmin()) {
        try {
            $name      = trim($_POST['name'] ?? '');
            $brand     = trim($_POST['brand'] ?? '');
            $compat    = trim($_POST['compatible_models'] ?? '');
            $category  = trim($_POST['category'] ?? 'Smartphone');
            $quality   = trim($_POST['quality'] ?? 'Original');
            $sku       = trim($_POST['sku'] ?? '') ?: generateSKU();
            $sell_price= floatval($_POST['selling_price'] ?? 0);
            $cost_price= floatval($_POST['cost_price'] ?? 0);
            $threshold = intval($_POST['low_stock_threshold'] ?? 3);
            $quantity  = intval($_POST['quantity_initial'] ?? 0);

            if (!$name) throw new Exception('Product name is required.');

            $conn->begin_transaction();
            // 1. Create product
            $stmt = $conn->prepare("INSERT INTO products (name, brand, compatible_models, category, quality, sku, selling_price, cost_price, current_stock, low_stock_threshold) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssddii", $name, $brand, $compat, $category, $quality, $sku, $sell_price, $cost_price, $quantity, $threshold);
            $stmt->execute();
            $product_id = $conn->insert_id;

            // 2. Record inflow (if quantity > 0)
            if ($quantity > 0) {
                $uid = $_SESSION['khadilu_user_id'];
                $total_cost = $quantity * $cost_price;
                $inflow = $conn->prepare("INSERT INTO stock_inflow (product_id, user_id, quantity, cost_per_unit, total_cost, supplier, supplier_phone, notes) VALUES (?,?,?,?,?, 'Initial Entry', '', 'Added during sale setup')");
                $inflow->bind_param("iiidd", $product_id, $uid, $quantity, $cost_price, $total_cost);
                $inflow->execute();
            }

            $conn->commit();
            logActivity('ADD_PRODUCT', 'products', $product_id, "Registered $name via Sales page");
            $msg = "✓ Product \"" . htmlspecialchars($name) . "\" registered. You can now add it to your cart!";
            $msg_type = 'success';
        } catch (Exception $e) {
            if (isset($conn)) $conn->rollback();
            $msg = "Error registering product: " . $e->getMessage(); $msg_type = 'error';
        }
        // Stop further processing if this was a registration action
        goto render_page;
    }

    $cart_json      = $_POST['cart_data'] ?? '[]';
    $cart           = json_decode($cart_json, true);
    $customer_id    = intval($_POST['customer_id'] ?? 0) ?: null;
    $total_paid     = floatval($_POST['amount_paid'] ?? 0);
    $customer       = trim($_POST['customer_name'] ?? '');
    $payment        = $_POST['payment_method'] ?? 'Cash';
    $notes          = trim($_POST['notes'] ?? '');

    if (empty($cart)) {
        $msg = 'Your cart is empty. Please add items before recording the sale.'; $msg_type = 'error';
    } elseif (!$customer_id && empty($customer)) {
        $msg = 'Proper documentation is required. Please select a registered customer or enter a name for this transaction.'; $msg_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // Pre-fetch customer name if registered to keep logs consistent
            if ($customer_id) {
                $c_stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
                $c_stmt->bind_param("i", $customer_id);
                $c_stmt->execute();
                $res_c = $c_stmt->get_result()->fetch_assoc();
                if ($res_c) $customer = $res_c['name'];
            }

            $uid = $_SESSION['khadilu_user_id'];
            $total_cart_value = 0;

            // Calculate total cart value first
            foreach ($cart as $item) {
                $total_cart_value += (intval($item['qty']) * floatval($item['price']));
            }

            // 1. Record the main sales transaction
            $txn_stmt = $conn->prepare("INSERT INTO sales_transactions (customer_id, customer_name, user_id, total_cart_value, total_paid, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $txn_stmt->bind_param("isiddss", $customer_id, $customer, $uid, $total_cart_value, $total_paid, $payment, $notes);
            if (!$txn_stmt->execute()) {
                throw new Exception("Could not record main transaction: " . $txn_stmt->error);
            }
            $transaction_id = $conn->insert_id;

            $remaining_payment = $total_paid;
            $stmt = $conn->prepare("INSERT INTO stock_outflow (transaction_id, product_id, user_id, customer_id, quantity, price_per_unit, total_amount, amount_paid, customer_name, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $upd = $conn->prepare("UPDATE products SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?");
            $check = $conn->prepare("SELECT current_stock, name FROM products WHERE id = ?");

            foreach ($cart as $item) {
                $product_id = intval($item['id']);
                $qty = intval($item['qty']);
                $price = floatval($item['price']);
                $item_total = $qty * $price; // Recalculate for individual item

                // Check stock during loop
                $check->bind_param("i", $product_id);
                $check->execute();
                $p = $check->get_result()->fetch_assoc();

                if (!$p || $p['current_stock'] < $qty) {
                    throw new Exception("Insufficient stock for " . ($p['name'] ?? "Unknown Product"));
                }

                // Distribute payment: apply payment to items one by one until exhausted
                $applied_payment = min($remaining_payment, $item_total);
                $remaining_payment -= $applied_payment;

                // Record Outflow
                $stmt->bind_param("iiiiidddsss", $transaction_id, $product_id, $uid, $customer_id, $qty, $price, $item_total, $applied_payment, $customer, $payment, $notes);
                if (!$stmt->execute()) {
                    throw new Exception("Could not record sale: " . $stmt->error);
                }
                // $sale_id = $conn->insert_id; // This is now the stock_outflow ID, not the main transaction ID
                $upd->bind_param("ii", $qty, $product_id); // $qty is correct here
                if (!$upd->execute()) {
                    throw new Exception("Could not update inventory: " . $upd->error);
                }
            }

            $conn->commit();
            logActivity('SALE_COMPLETED', 'sales_transactions', $transaction_id, "Sale to $customer for " . money($total_cart_value));
            $msg = "✓ Transaction complete. Total: " . money($total_cart_value) . " (Paid: " . money($total_paid) . ")";
            $msg .= " — <a href='receipt.php?txn_id=$transaction_id' target='_blank' class='bold underline'>Print Receipt</a>";
            $msg_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            log_error($e, 'SALE_TRANSACTION');
            $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }
    }
}

render_page:

$prod_res = $conn->query("SELECT id, name, brand, current_stock, selling_price, quality, category, sku, compatible_models FROM products WHERE current_stock > 0 AND is_deleted = 0 ORDER BY name ASC");
if (!$prod_res) {
    die("Database Error (Products): " . $conn->error . ". Please ensure you have run the latest SQL updates.");
}
$products = $prod_res->fetch_all(MYSQLI_ASSOC);

$brands = array_filter(array_unique(array_column($products, 'brand')));
sort($brands);
$categories_list = array_filter(array_unique(array_column($products, 'category')));
sort($categories_list);

$customers = $conn->query("SELECT * FROM customers WHERE type IN ('Retail', 'Both') ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch categories and qualities for the quick-add modal
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Recent sales
$recent_res = $conn->query("
  SELECT o.*, p.name AS product_name, p.brand, p.quality, p.category, p.sku, p.id as product_id
  FROM stock_outflow o JOIN products p ON o.product_id = p.id
  ORDER BY o.created_at DESC LIMIT 8
");
if (!$recent_res) {
    die("Database Error (Recent Sales): " . $conn->error);
}
$recent = $recent_res->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';

echo renderToast($msg, $msg_type);
?>

<div class="two-col">
  <!-- Sale Form -->
  <div class="card form-card">
    <div class="card-header"><h3>Build Sale Cart</h3></div>

    <?php if (empty($products)): ?>
      <div class="empty-state">⚠ No products in stock. <a href="add_stock.php">Add stock first.</a></div>
    <?php else: ?>
    <div class="item-selector" style="background: var(--bg-alt); padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border);">
      <div class="form-row" style="margin-bottom: 15px;">
        <div class="form-group">
          <label class="small dim">Brand Filter</label>
          <select id="brand-filter" onchange="filterProducts()">
            <option value="">— All Brands —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="small dim">Category Filter</label>
          <select id="category-filter" onchange="filterProducts()">
            <option value="">— All Categories —</option>
            <?php foreach ($categories_list as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Device Model *</label>
        <select id="product-select" onchange="autoFillPrice(this)">
          <option value="">— Select Product —</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"
            data-brand="<?= htmlspecialchars($p['brand']) ?>"
            data-price="<?= $p['selling_price'] ?>"
            data-stock="<?= $p['current_stock'] ?>"
            data-category="<?= htmlspecialchars($p['category']) ?>"
            data-sku="<?= htmlspecialchars($p['sku'] ?: 'N/A') ?>"
            data-compat="<?= htmlspecialchars($p['compatible_models'] ?? '') ?>">
            [<?= htmlspecialchars($p['sku'] ?: 'No SKU') ?>] <?= htmlspecialchars($p['brand']) ?> <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['quality']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="product-info-box" id="product-info" style="display:none">
        <div><span class="dim small">Available Units:</span> <span class="mono bold green-text" id="stock-display">—</span></div>
        <div><span class="dim small">List Price:</span> <span class="mono accent-text" id="price-display">—</span></div>
        <div><span class="dim small">Category:</span> <span class="tag" id="category-display">—</span></div>
        <div><span class="dim small">SKU/IMEI:</span> <span class="mono small" id="sku-display">—</span></div>
        <div id="compat-info" style="margin-top:5px; border-top:1px solid var(--border); padding-top:5px; width:100%; display:none;">
            <span class="dim small">Fits:</span> <span class="small" id="compat-display"></span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Qty</label>
          <input type="number" id="qty-input" min="1" value="1" oninput="calcTotal()">
        </div>
        <div class="form-group">
          <label>Price (₦) <span id="item-subtotal" class="small dim" style="margin-left:10px"></span></label>
          <input type="number" id="price-input" step="0.01" min="0" oninput="calcTotal()">
          <span class="form-hint">Auto-filled from inventory, edit if needed</span>
        </div>
      </div>
      <button type="button" class="btn btn-outline btn-full" onclick="addToCart()">Add to Cart ↓</button>
    </div>

    <div style="margin: 25px 0; border-top: 1px dashed var(--border); position: relative;"><span style="position: absolute; top: -10px; left: 20px; background: var(--surface); padding: 0 10px; font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Cart & Checkout</span></div>

    <form method="POST" id="main-sale-form">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="cart_data" id="cart-data-input">
      
      <div id="cart-empty-msg" class="empty-state small" style="padding:20px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">Your cart is currently empty. Add parts above to begin.</div>
      <div class="table-wrap" id="cart-table-wrap" style="display:none; margin-bottom:20px; border:1px solid var(--border);">
        <table class="data-table small">
            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
            <tbody id="cart-body"></tbody>
        </table>
      </div>

      <div class="total-display" id="total-box" style="display:none">
        Total Cart Value: <span class="mono accent-text" id="total-amount">₦0.00</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Register to Customer (for Debts)</label>
          <select name="customer_id" id="customer-select">
            <option value="0">Walk-in (No Debt tracking)</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint"><a href="customers.php">Add New Customer</a></span>
        </div>
        <div class="form-group">
          <label id="walk-in-label">Walk-in Name (Required) *</label>
          <input type="text" name="customer_name" id="walk-in-input" placeholder="Enter full name for documentation">
        </div>
      </div>
      <div class="form-row">
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
          <label>Amount Paid Today (₦) *</label>
          <input type="number" name="amount_paid" id="paid-input" step="0.01" min="0" placeholder="0.00" required>
          <span class="form-hint">Enter 0 for full credit/debt</span>
        </div>
      </div>

      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" rows="2" placeholder="Any extra details…"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-full" id="submit-btn" disabled>Complete Transaction →</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Recent Sales -->
  <div class="card">
    <div class="card-header"><h3>Log: Dispatched Inventory</h3></div>
    <?php if (empty($recent)): ?>
      <div class="empty-state">No sales dispatched today.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table small">
        <thead><tr><th>Device Model</th><th>Type/SKU</th><th>Quality</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Buyer</th><th>Payment</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td class="bold accent-text">
              <span class="view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
                <?= htmlspecialchars($r['product_name']) ?>
              </span>
              <div class="dim small"><?= htmlspecialchars($r['brand']) ?></div>
            </td>
            <td>
              <div class="small"><?= htmlspecialchars($r['category']) ?></div>
              <div class="mono small dim"><?= htmlspecialchars($r['sku'] ?: '—') ?></div>
            </td>
            <td><span class="pill pill-green" style="font-size:0.7rem"><?= htmlspecialchars($r['quality']) ?></span></td>
            <td><span class="pill pill-red">–<?= $r['quantity'] ?></span></td>
            <td class="mono"><?= money($r['price_per_unit']) ?></td>
            <td class="mono accent-text"><?= money($r['total_amount']) ?></td>
            <td class="dim"><?= htmlspecialchars($r['customer_name']) ?></td>
            <td><span class="tag"><?= htmlspecialchars($r['payment_method']) ?></span></td>
            <td class="dim small"><?= timeAgo($r['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Register Modal (for products not found in search) -->
<?php if (isAdmin()): ?>
<div class="modal-overlay" id="quick-register-modal" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h3>Register & Add Stock: <span id="reg-title">Device</span></h3>
      <button class="modal-close" onclick="document.getElementById('quick-register-modal').style.display='none'">✕</button>
    </div>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="register_and_add_stock">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <div class="form-row">
        <div class="form-group"><label>Device Model *</label><input type="text" name="name" id="reg-name" required></div>
        <div class="form-group">
          <label>Manufacturer</label>
          <select name="brand" id="reg-brand" class="select2-modal-brand">
            <option value="">— Select or Type New Brand —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Compatible Models</label><input type="text" name="compatible_models" id="reg-compat"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category" id="reg-category">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quality Grade</label>
          <select name="quality" id="reg-quality">
            <?php foreach ($qualities as $q): ?>
              <option value="<?= htmlspecialchars($q['name']) ?>"><?= htmlspecialchars($q['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>SKU / IMEI</label><input type="text" name="sku" id="reg-sku"></div>
        <div class="form-group"><label>Initial Stock Qty *</label><input type="number" name="quantity_initial" id="reg-stock" value="1" min="1"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Cost Price (₦)</label><input type="number" name="cost_price" id="reg-cost" step="0.01"></div>
        <div class="form-group"><label>Selling Price (₦)</label><input type="number" name="selling_price" id="reg-price" step="0.01"></div>
      </div>
      <div class="form-group">
        <label>Low Stock Threshold</label>
        <input type="number" name="low_stock_threshold" id="reg-threshold" min="1" value="3">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('quick-register-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Register and Add Stock →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function initializeProductSelect() {
    $('#product-select').select2({
        placeholder: "Search for a model or SKU...",
        width: '100%',
        tags: true,
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') return null;
            return { id: term, text: term, newTag: true };
        }
    }).off('select2:select').on('select2:select', function (e) {
        if (e.params.data.newTag) {
            const tagName = e.params.data.text;
            $('#product-select').val(null).trigger('change');
            openRegistrationModal(tagName);
        }
    });
}

$(document).ready(function() {
    initializeProductSelect();
    // Standardize modal selects
    $('#reg-category, #reg-quality').select2({
        dropdownParent: $('#quick-register-modal'),
        width: '100%'
    });
    $('#reg-brand').select2({
        dropdownParent: $('#quick-register-modal'),
        width: '100%', tags: true
    });
});

function openRegistrationModal(name) {
    $('#reg-title').text(name);
    $('#reg-name').val(name);
    $('#reg-brand').val('').trigger('change'); // Reset and trigger Select2 update
    $('#reg-compat').val('');
    $('#reg-sku').val('');
    $('#reg-cost').val('');
    $('#reg-price').val('');
    $('#reg-stock').val($('#qty-input').val() || 1);
    
    $('#quick-register-modal').css('display', 'flex');
}

$(document).ready(function() {
    $('#customer-select').select2({
        placeholder: "Select customer...",
    });
    
    // Toggle Walk-in Requirement
    $('#customer-select').on('change', function() {
        const isWalkIn = $(this).val() == "0";
        const $input = $('#walk-in-input');
        const $label = $('#walk-in-label');
        
        if (!isWalkIn) {
            $input.val('').attr('placeholder', 'Documentation pulled from account').prop('disabled', true);
            $label.html('Documentation <span class="dim small">(Auto)</span>');
        } else {
            $input.prop('disabled', false).attr('placeholder', 'Enter full name for documentation');
            $label.html('Walk-in Name (Required) *');
        }
    });
});

let cart = [];

function addToCart() {
    const select = document.getElementById('product-select');
    const opt = select.options[select.selectedIndex];
    if (!opt.value) return alert("Select a product");

    const qty = parseInt(document.getElementById('qty-input').value) || 0;
    const price = parseFloat(document.getElementById('price-input').value) || 0;
    const stock = parseInt(opt.dataset.stock);

    if (qty < 1) return alert("Enter valid quantity");
    if (qty > stock) return alert("Not enough stock ("+stock+" available)");

    cart.push({
        id: opt.value,
        name: opt.text,
        qty: qty,
        price: price,
        total: qty * price
    });

    renderCart();
    // Reset selector
    $('#product-select').val(null).trigger('change');
    document.getElementById('qty-input').value = 1;
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function renderCart() {
    const body = document.getElementById('cart-body');
    const wrap = document.getElementById('cart-table-wrap');
    const empty = document.getElementById('cart-empty-msg');
    const submitBtn = document.getElementById('submit-btn');
    const totalBox = document.getElementById('total-box');
    const paidInput = document.getElementById('paid-input');
    
    body.innerHTML = '';
    let grandTotal = 0;

    cart.forEach((item, index) => {
        grandTotal += item.total;
        body.innerHTML += `
            <tr>
                <td class="small">${item.name}</td>
                <td class="mono">${item.qty}</td>
                <td class="mono">${item.price.toLocaleString()}</td>
                <td class="mono bold">${item.total.toLocaleString()}</td>
                <td style="text-align:right"><button type="button" class="btn btn-xs btn-danger" onclick="removeFromCart(${index})">×</button></td>
            </tr>
        `;
    });

    document.getElementById('cart-data-input').value = JSON.stringify(cart);
    wrap.style.display = cart.length ? 'block' : 'none';
    empty.style.display = cart.length ? 'none' : 'block';
    totalBox.style.display = cart.length ? 'block' : 'none';
    submitBtn.disabled = cart.length === 0;

    if (cart.length > 0) {
        document.getElementById('total-amount').textContent = '₦' + grandTotal.toLocaleString('en-NG', {minimumFractionDigits: 2});
        // Suggest full payment
        if (paidInput.value == 0 || paidInput.dataset.auto == "true") {
            paidInput.value = grandTotal;
            paidInput.dataset.auto = "true";
        }
    }
}

function autoFillPrice(sel) {
  const opt = sel.options[sel.selectedIndex];
  const info = document.getElementById('product-info');
  if (opt.value) {
    document.getElementById('stock-display').textContent = opt.dataset.stock + ' units';
    document.getElementById('price-display').textContent = '₦' + parseFloat(opt.dataset.price).toLocaleString('en-NG', {minimumFractionDigits: 2});
    document.getElementById('category-display').textContent = opt.dataset.category;
    document.getElementById('sku-display').textContent = opt.dataset.sku;
    document.getElementById('price-input').value = opt.dataset.price;
    info.style.display = 'flex';

    if(opt.dataset.compat) {
        document.getElementById('compat-display').textContent = opt.dataset.compat;
        document.getElementById('compat-info').style.display = 'block';
    } else {
        document.getElementById('compat-info').style.display = 'none';
    }
  } else {
    info.style.display = 'none';
  }
}

function filterProducts() {
    const brand = document.getElementById('brand-filter').value;
    const cat = document.getElementById('category-filter').value;
    const $select = $('#product-select');
    
    $select.find('option').each(function() {
        if (!this.value) return;
        const bMatch = !brand || this.dataset.brand === brand;
        const cMatch = !cat || this.dataset.category === cat;
        
        if (bMatch && cMatch) {
            $(this).prop('disabled', false);
        } else {
            $(this).prop('disabled', true);
        }
    });
    
    $select.val(null).trigger('change');
    $select.select2('destroy');
    initializeProductSelect();
}

function calcTotal() {
  const qty = parseFloat(document.getElementById('qty-input').value) || 0;
  const price = parseFloat(document.getElementById('price-input').value) || 0;
  const display = document.getElementById('item-subtotal');
  
  if (qty > 0 && price > 0) {
    display.textContent = 'Subtotal: ₦' + (qty * price).toLocaleString();
  } else {
    display.textContent = '';
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
