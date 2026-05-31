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
        try {
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
                    $stmt = $conn->prepare("INSERT INTO stock_outflow (transaction_id, product_id, user_id, customer_id, quantity, cost_price, price_per_unit, total_amount, amount_paid, customer_name, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $upd = $conn->prepare("UPDATE products SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ? AND current_stock >= ?");
                    $check = $conn->prepare("SELECT current_stock, name, cost_price FROM products WHERE id = ? FOR UPDATE");

                    foreach ($cart as $item) {
                        $product_id = intval($item['id']);
                        $qty = intval($item['qty']);
                        $price = floatval($item['price']);
                        $item_total = $qty * $price; // Recalculate for individual item

                        // Check stock during loop with row-level locking
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
                        $stmt->bind_param("iiiiiddddsss", $transaction_id, $product_id, $uid, $customer_id, $qty, $p['cost_price'], $price, $item_total, $applied_payment, $customer, $payment, $notes);
                        if (!$stmt->execute()) {
                            throw new Exception("Could not record sale detail: " . $stmt->error);
                        }

                        // Conditional update to double-check stock availability
                        $upd->bind_param("iii", $qty, $product_id, $qty);
                        if (!$upd->execute() || $conn->affected_rows === 0) {
                            throw new Exception("Stock deduction failed for " . $p['name'] . ". Possibly sold out in another session.");
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
        } catch (Exception $e) {
            log_error($e, 'SALE_PAGE_ACTION');
            $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error';
        }
    }
}

$prod_res = $conn->query("SELECT id, name, brand, current_stock, cost_price, selling_price, quality, category, sku, compatible_models FROM products WHERE current_stock > 0 AND is_deleted = 0 ORDER BY name ASC");
if (!$prod_res) {
    die("Database Error (Products): " . $conn->error . ". Please ensure you have run the latest SQL updates.");
}
$products = $prod_res->fetch_all(MYSQLI_ASSOC);

$brands = array_filter(array_unique(array_column($products, 'brand')));
sort($brands);
$categories_list = array_filter(array_unique(array_column($products, 'category')));
sort($categories_list);
$qualities_list = array_filter(array_unique(array_column($products, 'quality')));
sort($qualities_list);

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

$products_json = json_encode($products); // Pass products to JS for dynamic filtering

require_once 'includes/header.php';

echo renderToast($msg, $msg_type);
?>

<div class="two-col">
  <!-- Sale Form -->
  <div class="card form-card" id="sale-card-container" style="overflow: visible; position: relative;">
    <div class="card-header"><h3>New Transaction</h3></div>

    <?php if (empty($products)): ?>
      <div class="empty-state">⚠ No products in stock. <?php if(isAdmin()): ?><a href="bulk_stock.php">Add stock first.</a><?php else: ?>Please contact an administrator to restock.<?php endif; ?></div>
    <?php else: ?>
    <div class="item-selector" style="background: var(--surface-2); padding: 20px; border-radius: var(--radius); margin: 20px; border: 1px solid var(--border-l);">
      <div class="form-row" style="margin-bottom: 15px;">
        <div class="form-group">
          <label class="small accent-text">Brand Filter</label>
          <select id="brand-filter" class="select2-simple" onchange="filterProducts()">
            <option value="">— All Brands —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="small accent-text">Category Filter</label>
          <select id="category-filter" class="select2-simple" onchange="filterProducts()">
            <option value="">— All Categories —</option>
            <?php foreach ($categories_list as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="small accent-text">Quality Filter</label>
          <select id="quality-filter" class="select2-simple" onchange="filterProducts()">
            <option value="">— All Qualities —</option>
            <?php foreach ($qualities_list as $q): ?>
              <option value="<?= htmlspecialchars($q) ?>"><?= htmlspecialchars($q) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <a href="javascript:void(0)" onclick="clearFilters()" class="btn btn-ghost btn-xs" style="margin-bottom: 15px;">Clear All Filters</a>

      <div class="form-group">
        <label>Device Model *</label>
        <select id="product-select" onchange="autoFillPrice(this)">
          <option value="">— Select Product —</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"
            data-brand="<?= htmlspecialchars($p['brand']) ?>"
            data-cost="<?= $p['cost_price'] ?>"
            data-price="<?= $p['selling_price'] ?>"
            data-stock="<?= $p['current_stock'] ?>"
            data-category="<?= htmlspecialchars($p['category']) ?>"
            data-quality="<?= htmlspecialchars($p['quality']) ?>"
            data-sku="<?= htmlspecialchars($p['sku'] ?: 'N/A') ?>"
            data-compat="<?= htmlspecialchars($p['compatible_models'] ?? '') ?>">
            [<?= htmlspecialchars($p['sku'] ?: 'No SKU') ?>] <?= htmlspecialchars($p['brand']) ?> <?= htmlspecialchars($p['name']) ?> [<?= htmlspecialchars($p['quality']) ?>]
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
          <input type="number" id="qty-input" min="1" placeholder="1" oninput="calcTotal()">
        </div>
        <div class="form-group">
          <label>Price (₦) <span id="item-subtotal" class="small dim" style="margin-left:10px"></span></label>
          <input type="number" id="price-input" step="0.01" min="0" oninput="calcTotal()">
          <span class="form-hint">Auto-filled from inventory, edit if needed</span>
        </div>
      </div>
      <button type="button" class="btn btn-outline btn-full" onclick="addToCart()">Add to Cart</button>
    </div>

    <div style="margin: 25px 0; border-top: 1px dashed var(--border); position: relative;"><span style="position: absolute; top: -10px; left: 20px; background: var(--surface); padding: 0 10px; font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Cart & Checkout</span></div>

    <form method="POST" id="main-sale-form">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="cart_data" id="cart-data-input">
      
      <div id="cart-empty-msg" class="empty-state small" style="padding:20px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">Your cart is currently empty. Add parts above to begin.</div>
      <div class="table-wrap" id="cart-table-wrap" style="display:none; margin-bottom:20px; border:1px solid var(--border);">
        <table class="data-table small">
            <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th><?php if(isAdmin()): ?><th>Margin</th><?php endif; ?><th></th></tr></thead>
            <tbody id="cart-body"></tbody>
        </table>
      </div>

      <div id="profit-summary" class="small mono" style="display:none; text-align: right; margin-bottom: 10px; color: var(--green);">
        Est. Profit: <span id="cart-profit-val">₦0.00</span>
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

      <button type="submit" class="btn btn-primary btn-full" id="submit-btn" disabled>Complete Transaction</button>
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
              <span class="quality-tag-inline">[<?= htmlspecialchars($r['quality']) ?>]</span>
              <div class="dim small"><?= !empty($r['brand']) ? htmlspecialchars($r['brand']) : '—' ?></div>
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

<script>
function initializeProductSelect() {
    // Destroys any existing Select2 instance before re-initializing
    $('#product-select').select2({
        placeholder: "Select from Stock...",
        width: '100%',
        closeOnSelect: true,
        matcher: function(params, data) {
            // Enhanced Search: Look in Name, SKU, and Compatibility
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            
            const term = params.term.toLowerCase();
            const text = data.text.toLowerCase();
            const sku = $(data.element).data('sku') ? $(data.element).data('sku').toString().toLowerCase() : '';
            const compat = $(data.element).data('compat') ? $(data.element).data('compat').toString().toLowerCase() : '';

            if (text.indexOf(term) > -1 || sku.indexOf(term) > -1 || compat.indexOf(term) > -1) {
                return data;
            }
            return null;
        }
    });
}

$(document).ready(function() {
    initializeProductSelect();
    
    $('#customer-select').select2({
        placeholder: "Select customer...",
        closeOnSelect: true
    });

    // Initialize filter dropdowns with select2
    $('#brand-filter, #category-filter, #quality-filter').select2({
        width: '100%',
        closeOnSelect: true
    });

    // Attach filterProducts to change event of filter dropdowns
    $('#brand-filter, #category-filter, #quality-filter').on('change', filterProducts);
    
    // Initial filter application on page load
    filterProducts();

    // Keyboard Shortcut: Press Enter in Qty or Price to Add to Cart
    $('#qty-input, #price-input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            addToCart();
        }
    });

    // Toggle Walk-in Requirement
    $('#customer-select').on('change', function() {
        const isWalkIn = $(this).val() == "0";
        const $input = $('#walk-in-input');
        const $label = $('#walk-in-label');
        
        if (!isWalkIn) { // Registered customer selected
            $input.val('').attr('placeholder', 'Documentation pulled from account').prop('disabled', true).removeClass('filter-active');
            $label.html('Documentation <span class="dim small">(Auto)</span>');
        } else {
            $input.prop('disabled', false).attr('placeholder', 'Enter full name for documentation').addClass('filter-active');
            $label.html('Walk-in Name (Required) *');
        }
    });
});

let cart = [];
let isFiltering = false;
let allProducts = <?= $products_json ?>; // Global variable for all products

// Function to update the filter dropdowns (Brand, Category, Quality)
// to only show options that are still relevant based on other active filters.
function updateFilterOptions() {
    const currentBrand = $('#brand-filter').val();
    const currentCat = $('#category-filter').val();
    const currentQual = $('#quality-filter').val();

    // Helper to calculate how much of a product is already in the cart
    const getInCartQty = (pid) => cart.filter(item => item.id == pid).reduce((sum, item) => sum + item.qty, 0);

    // Rebuild brand filter options
    const availableBrands = new Set(allProducts.filter(p =>
        (p.current_stock - getInCartQty(p.id) > 0) && 
        (!currentCat || p.category === currentCat) && 
        (!currentQual || p.quality === currentQual)
    ).map(p => p.brand));
    $('#brand-filter option').each(function() {
        if (this.value === '') return;
        $(this).prop('disabled', !availableBrands.has(this.value));
    });

    // Rebuild category filter options
    const availableCategories = new Set(allProducts.filter(p =>
        (p.current_stock - getInCartQty(p.id) > 0) &&
        (!currentBrand || p.brand === currentBrand) && 
        (!currentQual || p.quality === currentQual)
    ).map(p => p.category));
    $('#category-filter option').each(function() {
        if (this.value === '') return;
        $(this).prop('disabled', !availableCategories.has(this.value));
    });

    // Rebuild quality filter options
    const availableQualities = new Set(allProducts.filter(p =>
        (p.current_stock - getInCartQty(p.id) > 0) &&
        (!currentBrand || p.brand === currentBrand) && 
        (!currentCat || p.category === currentCat)
    ).map(p => p.quality));
    $('#quality-filter option').each(function() {
        if (this.value === '') return;
        $(this).prop('disabled', !availableQualities.has(this.value));
    });

    // Important: Tell Select2 to refresh the visual state of the dropdown options
    $('#brand-filter, #category-filter, #quality-filter').trigger('change.select2');
}

function addToCart() {
    const select = document.getElementById('product-select');
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return alert("Select a product");

    const qty = parseInt(document.getElementById('qty-input').value) || 0;
    const price = parseFloat(document.getElementById('price-input').value) || 0;
    const stock = parseInt(opt.dataset.stock);

    if (qty < 1) return alert("Enter valid quantity");
    if (qty > stock) return alert("Not enough stock ("+stock+" available)");

    cart.push({
        id: opt.value,
        name: opt.text,
        qty: qty,
        cost: parseFloat(opt.dataset.cost) || 0,
        price: price,
        total: qty * price
    });

    renderCart();

    // Reset filters and inputs for the next item
    $('#brand-filter, #category-filter, #quality-filter').val(null).trigger('change.select2');
    filterProducts(); // This restores the full product list in the dropdown

    document.getElementById('qty-input').value = 1;
    document.getElementById('price-input').value = '';
    document.getElementById('item-subtotal').textContent = '';
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
    filterProducts(); // Re-validate inventory list to bring back the removed item
}

function renderCart() {
    const body = document.getElementById('cart-body');
    const wrap = document.getElementById('cart-table-wrap');
    const empty = document.getElementById('cart-empty-msg');
    const submitBtn = document.getElementById('submit-btn');
    const totalBox = document.getElementById('total-box');
    const paidInput = document.getElementById('paid-input');
    const profitBox = document.getElementById('profit-summary');
    
    body.innerHTML = '';
    let grandTotal = 0;
    let totalCost = 0;

    cart.forEach((item, index) => {
        grandTotal += item.total;
        totalCost += (item.qty * item.cost);
        
        let marginHtml = '';
        <?php if(isAdmin()): ?>
            let margin = item.total - (item.qty * item.cost);
            marginHtml = `<td class="mono small ${margin >= 0 ? 'green-text' : 'red-text'}">₦${margin.toLocaleString()}</td>`;
        <?php endif; ?>

        body.innerHTML += `
            <tr>
                <td class="small">${item.name}</td>
                <td class="mono">${item.qty}</td>
                <td class="mono">${item.price.toLocaleString()}</td>
                <td class="mono bold">${item.total.toLocaleString()}</td>
                ${marginHtml}
                <td style="text-align:right"><button type="button" class="btn btn-xs btn-danger" onclick="removeFromCart(${index})">×</button></td>
            </tr>
        `;
    });

    document.getElementById('cart-data-input').value = JSON.stringify(cart);
    wrap.style.display = cart.length ? 'block' : 'none';
    empty.style.display = cart.length ? 'none' : 'block';
    totalBox.style.display = cart.length ? 'block' : 'none';
    if(profitBox) profitBox.style.display = cart.length ? 'block' : 'none';
    submitBtn.disabled = cart.length === 0;

    if (cart.length > 0) {
        document.getElementById('total-amount').textContent = '₦' + grandTotal.toLocaleString('en-NG', {minimumFractionDigits: 2});
        if(document.getElementById('cart-profit-val')) {
            let profit = grandTotal - totalCost;
            document.getElementById('cart-profit-val').textContent = '₦' + profit.toLocaleString(undefined, {minimumFractionDigits: 2});
        }
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
  if (opt && opt.value) {
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

// Main filtering function for products
function filterProducts() {
    if (isFiltering) return;
    isFiltering = true;

    const brand = $('#brand-filter').val();
    const cat = $('#category-filter').val();
    const qual = $('#quality-filter').val();

    const getInCartQty = (pid) => cart.filter(item => item.id == pid).reduce((sum, item) => sum + item.qty, 0);

    let filtered = allProducts.filter(p => {
        // Calculate effective stock
        const effectiveStock = p.current_stock - getInCartQty(p.id);
        if (effectiveStock <= 0) return false; // Hide if fully exhausted in cart

        const bMatch = !brand || p.brand === brand;
        const cMatch = !cat || p.category === cat;
        const qMatch = !qual || p.quality === qual;
        return bMatch && cMatch && qMatch;
    });

    const $productSelect = $('#product-select');
    $productSelect.empty().append('<option value="">— Select Product —</option>'); // Clear and add default

    filtered.forEach(p => {
        const effectiveStock = p.current_stock - getInCartQty(p.id);
        const optionText = `[${p.sku || 'No SKU'}] ${p.brand} ${p.name} [${p.quality}] (${effectiveStock} available)`;
        
        const option = new Option(optionText, p.id, false, false);
        option.setAttribute('data-cost', p.cost_price);
        option.setAttribute('data-brand', p.brand);
        option.setAttribute('data-price', p.selling_price);
        option.setAttribute('data-stock', effectiveStock);
        option.setAttribute('data-category', p.category);
        option.setAttribute('data-quality', p.quality);
        option.setAttribute('data-sku', p.sku || '');
        option.setAttribute('data-compat', p.compatible_models || '');
        $productSelect.append(option);
    });

    // Update the filter dropdowns themselves
    updateFilterOptions();

    $productSelect.val(null);
    if ($productSelect.hasClass('select2-hidden-accessible')) {
        $productSelect.select2('destroy');
    }
    initializeProductSelect();

    // Sync the info box UI
    autoFillPrice($productSelect[0]);

    isFiltering = false;
}

function clearFilters() {
    // Silently clear filter values and refresh Select2 visual labels
    $('#brand-filter, #category-filter, #quality-filter').val(null).trigger('change.select2');

    // Reset the manual item inputs and subtotal display
    $('#qty-input').val(1);
    $('#price-input').val('');
    $('#item-subtotal').text('');

    filterProducts(); // Re-apply filters after clearing
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
