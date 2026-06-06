<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Inventory';

$msg = '';
$msg_type = '';

// ── Add new product ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    // Server-side security: Only admins can perform these actions
    if (in_array($_POST['action'], ['add_product', 'edit_product', 'delete_product', 'archive_product', 'restore_product']) && !can('manage_inventory')) {
      header("Location: inventory.php");
      exit();
    }

    // CSRF Check
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
      throw new Exception("Security Validation Failed.");
    }

    if ($_POST['action'] === 'add_product') {
      $name      = trim($_POST['name'] ?? '');
      $brand     = trim($_POST['brand'] ?? '');
      $compat    = trim($_POST['compatible_models'] ?? '');
      $category  = trim($_POST['category'] ?? 'Smartphone');
      $quality   = trim($_POST['quality'] ?? 'Original');
      $sku       = trim($_POST['sku'] ?? '') ?: generateSKU();
      $threshold = intval($_POST['low_stock_threshold'] ?? 3);

      if (!$name) {
        throw new Exception('Product name is required.');
      }

      $stmt = $conn->prepare("INSERT INTO products (name, brand, compatible_models, category, quality, sku, low_stock_threshold) VALUES (?,?,?,?,?,?,?)");
      $stmt->bind_param("ssssssi", $name, $brand, $compat, $category, $quality, $sku, $threshold);
      if ($stmt->execute()) {
        $msg = "Product \"" . htmlspecialchars($name) . "\" added successfully!";
        $msg_type = 'success';
        logActivity('ADD_PRODUCT', 'products', $conn->insert_id, "Added $name ($brand)");
      }
    }

    // Soft delete (archive) a product
    if ($_POST['action'] === 'archive_product') {
      $id = intval($_POST['product_id']);
      $stmt = $conn->prepare("UPDATE products SET is_deleted = 1 WHERE id = ?");
      $stmt->bind_param("i", $id);
      if ($stmt->execute()) {
        $msg = 'Product archived successfully.';
        $msg_type = 'success';
        logActivity('ARCHIVE_PRODUCT', 'products', $id);
      }
    }

    // Restore a soft-deleted product
    if ($_POST['action'] === 'restore_product') {
      $id = intval($_POST['product_id']);
      $stmt = $conn->prepare("UPDATE products SET is_deleted = 0 WHERE id = ?");
      $stmt->bind_param("i", $id);
      if ($stmt->execute()) {
        $msg = 'Product restored successfully.';
        $msg_type = 'success';
      }
    }

    // Permanent Delete
    if ($_POST['action'] === 'delete_product') {
      $id = intval($_POST['product_id']);
      $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
      $stmt->bind_param("i", $id);
      try {
        if ($stmt->execute()) {
          $msg = 'Product permanently removed from database.';
          $msg_type = 'success';
        }
      } catch (mysqli_sql_exception $e) {
        throw new Exception('Cannot delete: This product has historical records. Use "Archive" instead.');
      }
    }
  } catch (Exception $e) {
    log_error($e, 'INVENTORY_ACTION');
    $msg = 'Error: ' . $e->getMessage();
    $msg_type = 'error';
  }
}

// ── Filter ─────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? ''); // Search term
$brand_f = trim($_GET['b_f'] ?? '');
$cat_f = trim($_GET['c_f'] ?? '');

$where_conditions = [];
$bind_params = [];
$bind_types = '';

// Default: only show active products
$where_conditions[] = "is_deleted = 0";

if ($filter === 'low')      $where_conditions[] = "current_stock <= low_stock_threshold AND current_stock > 0";
if ($filter === 'out')      $where_conditions[] = "current_stock = 0";
if ($filter === 'archived') $where_conditions = ["is_deleted = 1"]; // Override to only show archived products

if ($brand_f) {
  $where_conditions[] = "brand = ?";
  $bind_params[] = $brand_f;
  $bind_types .= 's';
}
if ($cat_f) {
  $where_conditions[] = "category = ?";
  $bind_params[] = $cat_f;
  $bind_types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
  $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

if ($search) {
  $searchTerm = "%$search%";
  $where_conditions[] = "(name LIKE ? OR brand LIKE ? OR sku LIKE ?)";
  $bind_params[] = $searchTerm;
  $bind_params[] = $searchTerm;
  $bind_params[] = $searchTerm;
  $bind_types .= 'sss';
  $final_where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
  $stmt = $conn->prepare("SELECT * FROM products $final_where_sql ORDER BY name ASC");
  $stmt->bind_param($bind_types, ...$bind_params);
  $stmt->execute();
  $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
  $stmt = $conn->prepare("SELECT * FROM products $where_sql ORDER BY name ASC");
  if (!empty($bind_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
  }
  $stmt->execute();
  $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Helper to maintain search parameters when switching tabs
$params = $_GET;
function getTabUrl($new_filter, $current_params)
{
  $current_params['filter'] = $new_filter;
  if ($new_filter === 'all') unset($current_params['filter']);
  return 'inventory.php?' . http_build_query($current_params);
}

// Fetch dynamic categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$qualities = $conn->query("SELECT * FROM qualities ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$brands_list = $conn->query("SELECT DISTINCT brand FROM products WHERE is_deleted = 0 AND brand IS NOT NULL AND brand != '' ORDER BY brand ASC")->fetch_all(MYSQLI_ASSOC);

// Edit modal data
$edit_product = null;
if (isset($_GET['edit']) && can('manage_inventory')) {
  // Security: Using prepared statements even for simple SELECTs is best practice
  $eid = intval($_GET['edit']);
  $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
  $stmt->bind_param("i", $eid);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res->num_rows) $edit_product = $res->fetch_assoc();
}

require_once 'includes/header.php';

echo renderToast($msg, $msg_type);
?>

<div class="toolbar">
  <div class="toolbar-left">
    <form class="filter-group" method="GET">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <div class="search-box-wrapper">
        <input type="text" name="q" placeholder="Search model, SKU..." value="<?= htmlspecialchars($search) ?>" class="<?= $search ? 'filter-active' : '' ?>">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
      </div>
      <div class="filter-selects">
        <select name="b_f" onchange="this.form.submit()" class="select2-filter <?= $brand_f ? 'filter-active' : '' ?>">
          <option value="">— Brand —</option>
          <?php foreach ($brands_list as $b): ?>
            <option value="<?= htmlspecialchars($b['brand']) ?>" <?= $brand_f === $b['brand'] ? 'selected' : '' ?>><?= htmlspecialchars($b['brand']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="c_f" onchange="this.form.submit()" class="select2-filter <?= $cat_f ? 'filter-active' : '' ?>">
          <option value="">— Category —</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $cat_f === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($search || $brand_f || $cat_f): ?>
        <a href="inventory.php" class="btn btn-ghost btn-xs">Clear Filters</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="toolbar-right">
    <div class="filter-tabs">
      <a href="<?= getTabUrl('all', $params) ?>" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
      <a href="<?= getTabUrl('low', $params) ?>" class="filter-tab yellow <?= $filter === 'low' ? 'active' : '' ?>">Low Stock</a>
      <a href="<?= getTabUrl('out', $params) ?>" class="filter-tab red <?= $filter === 'out' ? 'active' : '' ?>">Out</a>
      <a href="<?= getTabUrl('archived', $params) ?>" class="filter-tab <?= $filter === 'archived' ? 'active' : '' ?>">Archived</a>
    </div>
    <?php if (can('manage_inventory')): ?>
      <a href="analyze_stock.php" class="btn btn-outline" style="margin-right: 10px;">📊 Stock Analytics</a>
      <button class="btn btn-primary" onclick="document.getElementById('add-modal').style.display='flex'">Register Device</button>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <?php if (empty($products)): ?>
    <div class="empty-state">
      <p>No devices or parts found in this sector.</p>
      <?php if (isAdmin()): ?><button class="btn btn-sm btn-primary" onclick="document.getElementById('add-modal').style.display='flex'">Register New Item</button><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Device Model</th>
            <th>Manufacturer</th>
            <th>Compatibility</th>
            <th>Category</th>
            <th>Quality</th>
            <th>SKU / IMEI</th>
            <th>Stock</th>
            <?php if (can('view_costs')): ?>
              <th>Cost Price</th>
            <?php endif; ?>
            <th>Selling Price</th>
            <th>Margin</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $i => $p):
            $margin = $p['selling_price'] - $p['cost_price'];
            $margin_pct = round(calculateMargin($p['cost_price'], $p['selling_price']));
            $stock_class = $p['current_stock'] == 0 ? 'red' : ($p['current_stock'] <= $p['low_stock_threshold'] ? 'yellow' : 'green');
          ?>
            <tr data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>'>
              <td class="dim small"><?= $i + 1 ?></td>
              <td class="bold accent-text">
                <?= htmlspecialchars($p['name']) ?> <span class="quality-tag-inline">[<?= htmlspecialchars($p['quality']) ?>]</span>
              </td>
              <td class="dim"><?= !empty($p['brand']) ? htmlspecialchars($p['brand']) : 'Generic' ?></td>
              <td class="small dim" style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($p['compatible_models'] ?? '') ?>">
                <?= htmlspecialchars($p['compatible_models'] ?? '—') ?>
              </td>
              <td><span class="tag"><?= htmlspecialchars($p['category']) ?></span></td>
              <td><span class="pill pill-green" style="font-size:0.7rem"><?= htmlspecialchars($p['quality']) ?></span></td>
              <td class="mono small dim"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
              <td><span class="stock-badge <?= $stock_class ?>"><?= $p['current_stock'] == 0 ? 'OUT' : $p['current_stock'] ?></span></td>
              <?php if (can('view_costs')): ?>
                <td class="mono"><?= money($p['cost_price']) ?></td>
              <?php endif; ?>
              <td class="mono accent-text"><?= money($p['selling_price']) ?></td>
              <?php if (can('view_costs')): ?>
                <td class="mono <?= $margin >= 0 ? 'green-text' : 'red-text' ?>"><?= money($margin) ?> <span class="dim small">(<?= $margin_pct ?>%)</span></td>
              <?php endif; ?>
              <td>
                <div class="action-btns">
                  <?php if (can('manage_inventory')): ?>
                    <?php if ($p['is_deleted'] == 0): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Archive <?= addslashes($p['name']) ?>? It will be hidden from inventory but its history will remain.')">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="archive_product">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-xs btn-danger">Archive</button>
                      </form>
                    <?php else: ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Restore <?= addslashes($p['name']) ?> to active inventory?')">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="restore_product">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-xs btn-success">Restore</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('PERMANENTLY DELETE <?= addslashes($p['name']) ?>? This cannot be undone and will fail if sales history exists.')">
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-xs" style="color:var(--red); border:1px solid var(--red); background:transparent;">Delete</button>
                    </form>
                    <a href="bulk_stock.php?product_id=<?= $p['id'] ?>" class="btn btn-xs btn-ghost">+ Stock</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add Product Modal -->
<?php if (isAdmin()): ?>
  <div class="modal-overlay" id="add-modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
      <div class="modal-header">
        <h3>Register New Device Model</h3>
        <button class="modal-close" onclick="document.getElementById('add-modal').style.display='none'">✕</button>
      </div>
      <form method="POST" class="modal-form">
        <input type="hidden" name="action" value="add_product">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <div class="form-row">
          <div class="form-group"><label>Device Model *</label><input type="text" name="name" placeholder="e.g. iPhone 15 Pro Max" required></div>
          <div class="form-group">
            <label>Manufacturer</label>
            <select name="brand" id="add-brand-select" class="select2-modal-brand">
              <option value="">— Select or Type New Brand —</option>
              <?php foreach (array_column($brands_list, 'brand') as $b): ?>
                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Compatible Models</label><input type="text" name="compatible_models" placeholder="e.g. Fits iPhone X, XS, XR"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Category</label>
            <select name="category">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label id="add-variant-label">Variant / Quality</label>
            <select name="quality" id="quality-selector">
              <option value="Standard">Standard / Generic</option>
              <?php foreach ($qualities as $q): ?>
                <option value="<?= htmlspecialchars($q['name']) ?>" data-category="<?= htmlspecialchars($q['category_name'] ?? '') ?>"><?= htmlspecialchars($q['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>SKU / IMEI / Serial</label><input type="text" name="sku" placeholder="Unique identifier"></div>
        </div>
        <div class="form-group">
          <label>Low Stock Alert Threshold</label>
          <input type="number" name="low_stock_threshold" placeholder="3" min="1">
          <span class="form-hint">Alert when stock falls to this number</span>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-modal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Register Device</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<script>
  $(document).ready(function() {
    $('.select2-filter').select2({
      minimumResultsForSearch: 10,
      width: '150px',
      closeOnSelect: true
    });
    // Initialize Select2 for selects within the Add Product modal
    $('#add-modal select[name="category"], #add-modal select[name="quality"]').select2({
      dropdownParent: $('#add-modal .modal'), // Ensure dropdown renders correctly
      width: '100%',
      closeOnSelect: true
    });
    $('#add-brand-select').select2({
      dropdownParent: $('#add-modal .modal'),
      width: '100%',
      tags: true,
      closeOnSelect: true
    });

    // Filter Qualities based on Category selection in Add Modal
    $('#add-modal select[name="category"]').on('change', function() {
      const cat = $(this).val();
      const $qSelect = $('#quality-selector');
      const $label = $('#add-variant-label');

      // Dynamic UI: Rename the field based on context
      if (cat === 'Backglass' || cat === 'Housing' || cat === 'Battery Cover') {
        $label.text('Type / Color');
      } else {
        $label.text('Variant / Quality');
      }

      // Filter the Quality options
      $qSelect.find('option').each(function() {
        const limitCat = $(this).data('category');
        // Show if Global (no category), matching category, or the "Standard" default
        if (!limitCat || limitCat === "" || limitCat === cat || $(this).val() === 'Standard') {
          $(this).prop('disabled', false).show();
        } else {
          $(this).prop('disabled', true).hide();
        }
      });

      // Reset to Standard if current selection is now hidden
      $qSelect.val('Standard').trigger('change.select2');
    }).trigger('change');
  });
</script>

<?php require_once 'includes/footer.php'; ?>