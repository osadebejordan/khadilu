<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Add Stock';

$msg = '';
$msg_type = '';
$prefill_id = intval($_GET['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Security Validation Failed.'; $msg_type = 'error';
    } else {

    try {
        // Handle Register and Add Stock in one go
        if (isset($_POST['action']) && $_POST['action'] === 'register_and_add_stock' && isAdmin()) {
            $name      = trim($_POST['name'] ?? '');
            $brand     = trim($_POST['brand'] ?? '');
            $compat    = trim($_POST['compatible_models'] ?? '');
            $category  = trim($_POST['category'] ?? 'Smartphone');
            $quality   = trim($_POST['quality'] ?? 'Original');
            $sku       = trim($_POST['sku'] ?? '') ?: generateSKU();
            $sell_price= floatval($_POST['selling_price'] ?? 0);
            $cost_price= floatval($_POST['cost_price'] ?? 0);
            $threshold = intval($_POST['low_stock_threshold'] ?? 3);
            
            // Stock inflow details
            $quantity     = intval($_POST['quantity_initial'] ?? 0);
            $supplier     = trim($_POST['supplier'] ?? 'Unknown');
            $supplier_phone = trim($_POST['supplier_phone'] ?? '');
            $notes        = trim($_POST['notes'] ?? 'Initial Stock Entry');

            if (!$name) throw new Exception('Product name is required.');

            $conn->begin_transaction();
            try {
                // 1. Create the product
                $stmt = $conn->prepare("INSERT INTO products (name, brand, compatible_models, category, quality, sku, selling_price, cost_price, current_stock, low_stock_threshold) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssssddii", $name, $brand, $compat, $category, $quality, $sku, $sell_price, $cost_price, $quantity, $threshold);
                if (!$stmt->execute()) throw new Exception("Failed to create product: " . $stmt->error);
                
                $product_id = $conn->insert_id;
                $uid = $_SESSION['khadilu_user_id'];
                $total_cost = $quantity * $cost_price;

                // 2. Record the inflow
                $inflow = $conn->prepare("INSERT INTO stock_inflow (product_id, user_id, quantity, cost_per_unit, total_cost, supplier, supplier_phone, notes) VALUES (?,?,?,?,?,?,?,?)");
                $inflow->bind_param("iiiddsss", $product_id, $uid, $quantity, $cost_price, $total_cost, $supplier, $supplier_phone, $notes);
                if (!$inflow->execute()) throw new Exception("Failed to record inflow: " . $inflow->error);

                $conn->commit();
                
                logActivity('ADD_PRODUCT', 'products', $product_id, "Registered and added $quantity units of $name");
                $msg = "✓ Product \"" . htmlspecialchars($name) . "\" registered and $quantity units added to stock!";
                $msg_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        }

        // Handle Quick Edit Action
        if (isset($_POST['action']) && $_POST['action'] === 'edit_product' && isAdmin()) {
            $id        = intval($_POST['product_id']);
            $name      = trim($_POST['name'] ?? '');
            $brand     = trim($_POST['brand'] ?? '');
            $compat    = trim($_POST['compatible_models'] ?? '');
            $category  = trim($_POST['category'] ?? '');
            $quality   = trim($_POST['quality'] ?? '');
            $sku       = trim($_POST['sku'] ?? '') ?: null;
            $sell_price= floatval($_POST['selling_price'] ?? 0);
            $cost_price= floatval($_POST['cost_price'] ?? 0);
            $stock     = intval($_POST['current_stock'] ?? 0);
            $threshold = intval($_POST['low_stock_threshold'] ?? 3);

            $stmt = $conn->prepare("UPDATE products SET name=?, brand=?, compatible_models=?, category=?, quality=?, sku=?, selling_price=?, cost_price=?, current_stock=?, low_stock_threshold=? WHERE id=?");
            $stmt->bind_param("ssssssddiii", $name, $brand, $compat, $category, $quality, $sku, $sell_price, $cost_price, $stock, $threshold, $id);
            if ($stmt->execute()) {
                $msg = "Product \"" . htmlspecialchars($name) . "\" updated successfully!"; $msg_type = 'success';
            }
        }

        // Handle Standard Stock Inflow
        if (isset($_POST['product_id']) && !isset($_POST['action']) && is_numeric($_POST['product_id'])) {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $quantity     = intval($_POST['quantity'] ?? 0);
    $cost_per_unit= floatval($_POST['cost_per_unit'] ?? 0);
    $supplier     = trim($_POST['supplier'] ?? 'Unknown');
    $supplier_phone = trim($_POST['supplier_phone'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    if (!$product_id || $quantity < 1) {
        $msg = 'Please select a product and enter a valid quantity.'; $msg_type = 'error';
    } else {
        $total_cost = $quantity * $cost_per_unit;

        $conn->begin_transaction();
        try {
            $uid = $_SESSION['khadilu_user_id'];
            // Insert inflow record
            $stmt = $conn->prepare("INSERT INTO stock_inflow (product_id, user_id, quantity, cost_per_unit, total_cost, supplier, supplier_phone, notes) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iiiddsss", $product_id, $uid, $quantity, $cost_per_unit, $total_cost, $supplier, $supplier_phone, $notes);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record inflow: " . $stmt->error);
            }

            // Update product stock and cost price
            $upd = $conn->prepare("UPDATE products SET current_stock = current_stock + ?, cost_price = ?, updated_at = NOW() WHERE id = ?");
            $upd->bind_param("idi", $quantity, $cost_per_unit, $product_id);
            if (!$upd->execute()) {
                throw new Exception("Failed to update product stock: " . $upd->error);
            }

            $conn->commit();

            $prod_name = $conn->query("SELECT name FROM products WHERE id = $product_id")->fetch_assoc()['name'];
            $msg = "✓ Added $quantity unit(s) of \"" . htmlspecialchars($prod_name) . "\" to stock."; $msg_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
        }
    } catch (Exception $e) {
        log_error($e, 'STOCK_PAGE_ACTION');
        $msg = 'Error: ' . $e->getMessage(); $msg_type = 'error';
    }
    }
}

$products = $conn->query("SELECT * FROM products WHERE is_deleted = 0 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$brands = array_filter(array_unique(array_column($products, 'brand')));
sort($brands);
$categories_list = array_filter(array_unique(array_column($products, 'category')));
sort($categories_list);

// Data for modals
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Recent inflows
$recent = $conn->query("
  SELECT i.*, p.name AS product_name, p.brand
  FROM stock_inflow i JOIN products p ON i.product_id = p.id
  ORDER BY i.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<?= renderToast($msg, $msg_type) ?>

<div class="two-col">
  <!-- Form -->
  <div class="card form-card">
    <div class="card-header">
      <h3>Register Stock Inflow</h3>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <div class="form-row" style="margin-bottom: 15px;">
        <div class="form-group">
          <label class="small dim">Brand Filter</label>
          <select id="brand-filter" class="select2-simple" onchange="filterProducts()">
            <option value="">— All Brands —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="small dim">Category Filter</label>
          <select id="category-filter" class="select2-simple" onchange="filterProducts()">
            <option value="">— All Categories —</option>
            <?php foreach ($categories_list as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Device Model *</label>
        <select name="product_id" id="product-select" required onchange="updateProductInfo(this)">
          <option value="">— Select Product —</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"
            data-brand="<?= htmlspecialchars($p['brand']) ?>"
            data-category="<?= htmlspecialchars($p['category']) ?>"
            data-quality="<?= htmlspecialchars($p['quality']) ?>"
            data-sku="<?= htmlspecialchars($p['sku'] ?? '') ?>"
            data-compat="<?= htmlspecialchars($p['compatible_models'] ?? '') ?>"
            data-stock="<?= $p['current_stock'] ?>"
            data-cost="<?= $p['cost_price'] ?>"
            data-price="<?= $p['selling_price'] ?>"
            data-threshold="<?= $p['low_stock_threshold'] ?>"
            <?= $p['id'] == $prefill_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['brand'] ?? '') ?>) — <?= $p['current_stock'] ?> in stock
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="product-info-box" id="product-info" style="display:none; justify-content: space-between; align-items: center;">
        <div>
          <span class="dim small">On-Hand Stock:</span>
          <span class="mono bold" id="current-stock-display">—</span>
        </div>
        <?php if (isAdmin()): ?>
          <button type="button" class="btn btn-sm btn-outline" style="border-style: dashed;" onclick="openEditModal()">Edit Device Details</button>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="quantity" id="qty-input" min="1" placeholder="e.g. 10" required oninput="calcTotal()">
        </div>
        <div class="form-group">
          <label>Cost per Unit (₦)</label>
          <input type="number" name="cost_per_unit" id="cpu-input" step="0.01" min="0" placeholder="0.00" oninput="calcTotal()">
        </div>
      </div>

      <div class="total-display" id="total-box" style="display:none">
        Gross Cost: <span class="mono accent-text" id="total-cost">₦0.00</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Supplier / Source</label>
          <input type="text" name="supplier" placeholder="e.g. Lagos distributor...">
        </div>
        <div class="form-group">
          <label>Supplier Phone</label>
          <input type="text" name="supplier_phone" placeholder="e.g. 08012345678">
        </div>
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" rows="2" placeholder="Any extra details…"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-full">Record Inflow</button>
    </form>

    <?php if (isAdmin()): ?>
    <div class="form-footer">
      <a href="inventory.php" class="btn btn-ghost btn-sm">Register New Device</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Inflows -->
  <div class="card">
    <div class="card-header"><h3>Log: Hardware Inflows</h3></div>
    <?php if (empty($recent)): ?>
      <div class="empty-state">No inflows recorded yet.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Device Model</th><th>Qty</th><th>Cost/Unit</th><th>Total</th><th>Manufacturer/Source</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td class="bold accent-text">
              <span class="view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
                <?= htmlspecialchars($r['product_name']) ?>
              </span>
            </td>
            <td><span class="pill pill-green">+<?= $r['quantity'] ?></span></td>
            <td class="mono"><?= money($r['cost_per_unit']) ?></td>
            <td class="mono"><?= money($r['total_cost']) ?></td>
            <td class="dim">
              <div class="bold" style="color: var(--text);"><?= htmlspecialchars($r['brand'] ?? '—') ?></div>
              <?= htmlspecialchars($r['supplier']) ?>
              <?php if(!empty($r['supplier_phone'])): ?><br><span class="small mono"><?= htmlspecialchars($r['supplier_phone']) ?></span><?php endif; ?>
            </td>
            <td class="dim small"><?= timeAgo($r['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quick Edit Modal -->
<?php if (isAdmin()): ?>
<div class="modal-overlay" id="quick-edit-modal" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-header">
      <h3><span id="modal-mode-text">Quick Edit</span>: <span id="edit-title">Device</span></h3>
      <button class="modal-close" onclick="document.getElementById('quick-edit-modal').style.display='none'">✕</button>
    </div>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" id="modal-action-field" value="edit_product">
      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
      <input type="hidden" name="product_id" id="edit-id">
      <div class="form-row">
        <div class="form-group"><label>Device Model *</label><input type="text" name="name" id="edit-name" required></div>
        <div class="form-group">
          <label>Manufacturer</label>
          <select name="brand" id="edit-brand" class="select2-modal-brand">
            <option value="">— Select or Type New Brand —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Compatible Models</label><input type="text" name="compatible_models" id="edit-compat"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category" id="edit-category">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quality Grade</label>
          <select name="quality" id="edit-quality">
            <?php foreach ($qualities as $q): ?>
              <option value="<?= htmlspecialchars($q['name']) ?>"><?= htmlspecialchars($q['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>SKU / IMEI</label><input type="text" name="sku" id="edit-sku"></div>
        <div class="form-group" id="stock-field-container"><label>Current Stock</label><input type="number" name="current_stock" id="edit-stock"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Cost Price (₦)</label><input type="number" name="cost_price" id="edit-cost" step="0.01"></div>
        <div class="form-group"><label>Selling Price (₦)</label><input type="number" name="selling_price" id="edit-price" step="0.01" placeholder="0.00"></div>
      </div>
      
      <!-- Fields for New Product Registration Only -->
      <div id="new-product-extras" style="display:none; border-top: 1px dashed var(--border); padding-top: 15px; margin-top: 10px;">
        <div class="form-row">
          <div class="form-group">
            <label>Initial Quantity *</label>
            <input type="number" name="quantity_initial" id="new-qty" min="1" value="1">
          </div>
          <div class="form-group">
            <label>Supplier</label>
            <input type="text" name="supplier" id="new-supplier" placeholder="e.g. Lagos Dealer">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Low Stock Threshold</label>
        <input type="number" name="low_stock_threshold" id="edit-threshold" min="1" placeholder="3">
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('quick-edit-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-submit-btn">Save Changes →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// Helper function to initialize product-select Select2 with all its options
function initializeProductSelect() {
    const $select = $('#product-select');
    $select.select2({
        placeholder: "Search for a model...",
        width: '100%',
        tags: true, // Crucial for allowing new product entry
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') return null;
            return { id: term, text: term, newTag: true };
        }
    }).off('select2:select').on('select2:select', function (e) {
        if (e.params.data.newTag) {
            const tagName = e.params.data.text;
            $('#product-select').val(null).trigger('change');
            openNewProductModal(tagName);
        } else {
            updateProductInfo(document.getElementById('product-select'));
        }
    });
}

$(document).ready(function() {
    initializeProductSelect(); // Initial setup

    // Standardize filters and modal selects
    $('.select2-simple').select2({ width: '100%' });
    $('#edit-category, #edit-quality').select2({
        dropdownParent: $('#quick-edit-modal .modal'),
        width: '100%'
    });
    $('#edit-brand').select2({
        dropdownParent: $('#quick-edit-modal'),
        width: '100%', tags: true
    });
});

function updateProductInfo(sel) {
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value || isNaN(opt.value)) return;
  
  const info = document.getElementById('product-info');
  if (opt.value) {
    document.getElementById('current-stock-display').textContent = opt.dataset.stock + ' units';
    info.style.display = 'flex';
  } else {
    info.style.display = 'none';
  }
  calcTotal();
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
    document.getElementById('product-info').style.display = 'none'; // Hide product info box
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy'); // Destroy current Select2 instance
    }
    initializeProductSelect(); // Re-initialize Select2 with updated options
}

function openNewProductModal(name) {
    // Prepare Modal for New Product Registration
    $('#modal-mode-text').text('Register & Add Stock');
    $('#modal-action-field').val('register_and_add_stock');
    $('#edit-id').val('');
    $('#edit-title').text(name);
    $('#edit-name').val(name);
    $('#edit-brand').val('').trigger('change'); // Reset and trigger change for Select2
    $('#edit-compat').val('');
    $('#edit-sku').val('');
    $('#edit-cost').val('');
    $('#edit-price').val('');
    $('#edit-threshold').val(3);
    
    // Default the selects to the first option
    $('#edit-category').val($('#edit-category option:first').val()).trigger('change');
    $('#edit-quality').val($('#edit-quality option:first').val()).trigger('change');

    $('#stock-field-container').hide();
    $('#new-product-extras').show();
    $('#modal-submit-btn').text('Register and Add Stock →');

    $('#new-qty').val($('#qty-input').val() || 1);
    $('#quick-edit-modal').css('display', 'flex');
}

function openEditModal() {
  const sel = document.getElementById('product-select');
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value || isNaN(opt.value)) return;

  // Prepare Modal for Editing Existing Product
  $('#modal-mode-text').text('Quick Edit');
  $('#modal-action-field').val('edit_product');
  $('#edit-id').val(opt.value);
  $('#edit-title').text(opt.text.split(' (')[0]);

  $('#stock-field-container').show(); // Ensure stock field is visible for edits
  $('#new-product-extras').hide();
  $('#modal-submit-btn').text('Save Changes →');

  // Set brand: create option if it doesn't exist (required for Select2 tags)
  if ($('#edit-brand').find("option[value='" + opt.dataset.brand + "']").length === 0) {
      var newBrand = new Option(opt.dataset.brand, opt.dataset.brand, true, true);
      $('#edit-brand').append(newBrand).trigger('change');
  } else {
      $('#edit-brand').val(opt.dataset.brand).trigger('change');
  }

  document.getElementById('edit-compat').value = opt.dataset.compat;
  document.getElementById('edit-sku').value = opt.dataset.sku;
  document.getElementById('edit-stock').value = opt.dataset.stock;
  document.getElementById('edit-cost').value = opt.dataset.cost;
  document.getElementById('edit-price').value = opt.dataset.price;
  document.getElementById('edit-threshold').value = opt.dataset.threshold;
  
  $('#edit-category').val(opt.dataset.category).trigger('change');
  $('#edit-quality').val(opt.dataset.quality).trigger('change');

  document.getElementById('quick-edit-modal').style.display = 'flex';
}

function calcTotal() {
  const qty = parseFloat(document.getElementById('qty-input').value) || 0;
  const cpu = parseFloat(document.getElementById('cpu-input').value) || 0;
  const total = qty * cpu;
  const box = document.getElementById('total-box');
  if (qty > 0 || cpu > 0) {
    document.getElementById('total-cost').textContent = '₦' + total.toLocaleString('en-NG', {minimumFractionDigits: 2});
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
}

// Trigger on load if prefilled
window.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('product-select');
  if (sel.value) updateProductInfo(sel);
});
</script>

<?php require_once 'includes/footer.php'; ?>
