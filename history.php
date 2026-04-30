<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'History & Reports';

// Filters
$type      = $_GET['type']  ?? 'all';
$from      = $_GET['from']  ?? date('Y-m-01');
$to        = $_GET['to']    ?? date('Y-m-d');
$product_f = intval($_GET['product'] ?? 0);
$brand_f   = $_GET['brand'] ?? '';
$cat_f     = $_GET['category'] ?? '';

$from_dt = $from . ' 00:00:00';
$to_dt   = $to   . ' 23:59:59';

// Summary stats for date range
$s_stmt = $conn->prepare("SELECT COUNT(*) AS cnt, SUM(total_amount) AS rev, SUM(quantity) AS units FROM stock_outflow WHERE created_at BETWEEN ? AND ?");
$s_stmt->bind_param("ss", $from_dt, $to_dt);
$s_stmt->execute();
$sales_stats = $s_stmt->get_result()->fetch_assoc();

$rep_stmt = $conn->prepare("SELECT COUNT(*) AS cnt, SUM(amount_paid) AS rev FROM repairs WHERE created_at BETWEEN ? AND ?");
$rep_stmt->bind_param("ss", $from_dt, $to_dt);
$rep_stmt->execute();
$repair_stats = $rep_stmt->get_result()->fetch_assoc();

$i_stmt = $conn->prepare("SELECT COUNT(*) AS cnt, SUM(total_cost) AS spent, SUM(quantity) AS units FROM stock_inflow WHERE created_at BETWEEN ? AND ?");
$i_stmt->bind_param("ss", $from_dt, $to_dt);
$i_stmt->execute();
$inflow_stats = $i_stmt->get_result()->fetch_assoc();

$r_stmt = $conn->prepare("SELECT COUNT(*) AS cnt, SUM(refund_amount) AS refunded, SUM(quantity) AS units FROM stock_returns WHERE created_at BETWEEN ? AND ?");
$r_stmt->bind_param("ss", $from_dt, $to_dt);
$r_stmt->execute();
$return_stats = $r_stmt->get_result()->fetch_assoc();

// Profit = Sales + Repair Income - Stock Purchases - Refunds/Credits issued
$gross_profit = ($sales_stats['rev'] ?? 0) + ($repair_stats['rev'] ?? 0) - ($inflow_stats['spent'] ?? 0) - ($return_stats['refunded'] ?? 0);

// Optimized Transaction List with UNION
$queries = [];
$all_params = [];
$all_types = "";

if ($type === 'all' || $type === 'sales') {
    $q_sales = "SELECT 'sale' AS txn_type, o.transaction_id AS id, o.created_at, CONCAT(p.name, ' (', p.quality, ')') AS product_name, p.sku, o.quantity, o.price_per_unit AS unit_price, o.total_amount AS total, o.customer_name AS party, o.payment_method AS extra, p.id as product_id 
          FROM stock_outflow o JOIN products p ON o.product_id = p.id 
          WHERE o.created_at BETWEEN ? AND ?";
    $params_sales = [$from_dt, $to_dt];
    $types_sales = "ss";
    if ($product_f) { $q_sales .= " AND o.product_id = ?"; $params_sales[] = $product_f; $types_sales .= "i"; }
    if ($brand_f)   { $q_sales .= " AND p.brand = ?"; $params_sales[] = $brand_f; $types_sales .= "s"; }
    if ($cat_f)     { $q_sales .= " AND p.category = ?"; $params_sales[] = $cat_f; $types_sales .= "s"; }
    $queries[] = $q_sales;
    $all_params = array_merge($all_params, $params_sales);
    $all_types .= $types_sales;
}

if ($type === 'all' || $type === 'inflow') {
    $q_inflow = "SELECT 'inflow' AS txn_type, i.id, i.created_at, CONCAT(p.name, ' (', p.quality, ')') AS product_name, p.sku, i.quantity, i.cost_per_unit AS unit_price, i.total_cost AS total, CONCAT(i.supplier, IF(i.supplier_phone != '', CONCAT(' (', i.supplier_phone, ')'), '')) AS party, '' AS extra, p.id as product_id 
          FROM stock_inflow i JOIN products p ON i.product_id = p.id 
          WHERE i.created_at BETWEEN ? AND ?";
    $params_inflow = [$from_dt, $to_dt];
    $types_inflow = "ss";
    if ($product_f) { $q_inflow .= " AND i.product_id = ?"; $params_inflow[] = $product_f; $types_inflow .= "i"; }
    if ($brand_f)   { $q_inflow .= " AND p.brand = ?"; $params_inflow[] = $brand_f; $types_inflow .= "s"; }
    if ($cat_f)     { $q_inflow .= " AND p.category = ?"; $params_inflow[] = $cat_f; $types_inflow .= "s"; }
    $queries[] = $q_inflow;
    $all_params = array_merge($all_params, $params_inflow);
    $all_types .= $types_inflow;
}

if ($type === 'all' || $type === 'repairs') {
    // We use dummy conditions (LIKE) for brand/cat/product so the parameter count stays consistent across the UNION
    $q_repairs = "SELECT 'repair' AS txn_type, r.id, r.created_at, 
            COALESCE(p.name, CONCAT('Repair: ', r.device_model)) AS product_name, 
            COALESCE(p.sku, '') AS sku, 
            1 AS quantity, 
            r.estimated_cost AS unit_price, 
            r.amount_paid AS total, 
            r.customer_name AS party, 
            r.status AS extra, 
            r.product_id as product_id 
          FROM repairs r 
          LEFT JOIN products p ON r.product_id = p.id 
          WHERE r.created_at BETWEEN ? AND ?";
    $params_repairs = [$from_dt, $to_dt];
    $types_repairs = "ss";
    if ($product_f) { $q_repairs .= " AND (0 = ?)"; $params_repairs[] = 0; $types_repairs .= "i"; } // Repairs aren't tied to inventory IDs
    if ($brand_f)   { $q_repairs .= " AND r.device_model LIKE CONCAT('%', ?, '%')"; $params_repairs[] = "%$brand_f%"; $types_repairs .= "s"; }
    if ($cat_f)     { $q_repairs .= " AND (1=1)"; /* No category filter for repairs */ } // No parameter needed for (1=1)
    $queries[] = $q_repairs;
    $all_params = array_merge($all_params, $params_repairs);
    $all_types .= $types_repairs;
}

if ($type === 'all' || $type === 'returns') {
    $q_returns = "SELECT 'return' AS txn_type, r.id, r.created_at, CONCAT(p.name, ' (', p.quality, ')') AS product_name, p.sku, r.quantity, 0 AS unit_price, r.refund_amount AS total, c.name AS party, r.notes AS extra, p.id as product_id 
          FROM stock_returns r 
          JOIN products p ON r.product_id = p.id 
          LEFT JOIN customers c ON r.customer_id = c.id
          WHERE r.created_at BETWEEN ? AND ?";
    $params_returns = [$from_dt, $to_dt];
    $types_returns = "ss";
    if ($product_f) { $q_returns .= " AND r.product_id = ?"; $params_returns[] = $product_f; $types_returns .= "i"; }
    if ($brand_f)   { $q_returns .= " AND p.brand = ?"; $params_returns[] = $brand_f; $types_returns .= "s"; }
    if ($cat_f)     { $q_returns .= " AND p.category = ?"; $params_returns[] = $cat_f; $types_returns .= "s"; }
    $queries[] = $q_returns;
    $all_params = array_merge($all_params, $params_returns);
    $all_types .= $types_returns;
}

$final_query = implode(" UNION ALL ", $queries) . " ORDER BY created_at DESC";
$stmt = $conn->prepare($final_query);

$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$products = $conn->query("SELECT id, name, brand, category FROM products ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$brands = array_filter(array_unique(array_column($products, 'brand')));
$categories = array_filter(array_unique(array_column($products, 'category')));

require_once 'includes/header.php';
?>

<!-- Filter Bar -->
<div class="card filter-bar">
  <form method="GET" class="filter-form">
    <div class="form-group">
      <label>Type</label>
      <select name="type">
        <option value="all"    <?= $type==='all'    ?'selected':''?>>All Transactions</option>
        <option value="sales"  <?= $type==='sales'  ?'selected':''?>>Sales Only</option>
        <option value="inflow" <?= $type==='inflow' ?'selected':''?>>Stock Inflows Only</option>
        <option value="repairs" <?= $type==='repairs' ?'selected':''?>>Repairs Only</option>
        <option value="returns" <?= $type==='returns' ?'selected':''?>>Returns Only</option>
      </select>
    </div>
    <div class="form-group">
      <label>From</label>
      <input type="date" name="from" value="<?= $from ?>">
    </div>
    <div class="form-group">
      <label>To</label>
      <input type="date" name="to" value="<?= $to ?>">
    </div>
    <div class="form-group">
      <label>Device Model</label>
      <select name="product">
        <option value="">All Devices</option>
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $product_f == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Brand</label>
      <select name="brand">
        <option value="">All Brands</option>
        <?php foreach ($brands as $b): ?>
        <option value="<?= htmlspecialchars($b) ?>" <?= $brand_f == $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Category</label>
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $cat_f == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="history.php" class="btn btn-ghost">Reset</a>
  </form>
</div>

<!-- Summary Cards -->
<div class="stats-grid compact">
  <?php if (isAdmin()): ?>
  <div class="stat-card accent">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value" title="<?= money(($sales_stats['rev'] ?? 0) + ($repair_stats['rev'] ?? 0)) ?>"><?= shortMoney(($sales_stats['rev'] ?? 0) + ($repair_stats['rev'] ?? 0)) ?></div>
    <div class="stat-sub"><?= number_format($sales_stats['cnt'] + $repair_stats['cnt']) ?> operations recorded</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Stock Purchased</div>
    <div class="stat-value" title="<?= money($inflow_stats['spent'] ?? 0) ?>"><?= shortMoney($inflow_stats['spent'] ?? 0) ?></div>
    <div class="stat-sub"><?= number_format($inflow_stats['cnt']) ?> inflows · <?= number_format($inflow_stats['units'] ?? 0) ?> units</div>
  </div>
  <div class="stat-card <?= $gross_profit >= 0 ? '' : 'danger' ?>">
    <div class="stat-label">Estimated Profit</div>
    <div class="stat-value <?= $gross_profit >= 0 ? 'green-text' : 'red-text' ?>" title="<?= money(abs($gross_profit)) ?>"><?= shortMoney(abs($gross_profit)) ?></div>
    <div class="stat-sub"><?= money($return_stats['refunded'] ?? 0) ?> deducted in returns</div>
  </div>
  <?php endif; ?>
</div>

<!-- Transactions Table -->
<div class="card">
  <div class="card-header">
    <h3>Transactions <span class="dim small">(<?= count($rows) ?> records)</span></h3>
  </div>
  <?php if (empty($rows)): ?>
    <div class="empty-state">No transactions found for the selected filters.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date/Time</th>
          <th>Operation</th>
          <th>Device Model</th>
          <th>SKU/IMEI</th>
          <th>Qty</th>
          <?php if (isAdmin()): ?>
          <th>Unit Price</th>
          <th>Total</th>
          <?php endif; ?>
          <th>Party</th>
          <th>Payment</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono small"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
          <td>
            <?php if ($r['txn_type'] === 'sale'): ?>
              <span class="pill pill-red">Sale ↑</span>
            <?php elseif ($r['txn_type'] === 'inflow'): ?>
              <span class="pill pill-green">Stock ↓</span>
            <?php elseif ($r['txn_type'] === 'repair'): ?>
              <span class="pill pill-yellow">Repair 🛠</span>
            <?php else: ?>
              <span class="pill pill-blue">Return ⟲</span>
            <?php endif; ?>
          </td>
          <td class="bold accent-text">
            <span class="view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
                <?= htmlspecialchars($r['product_name']) ?>
            </span>
          </td>
          <td class="mono small dim"><?= htmlspecialchars($r['sku'] ?: '—') ?></td>
          <td class="mono"><?= $r['txn_type'] === 'sale' ? '–' : '+' ?><?= $r['quantity'] ?></td>
          <?php if (isAdmin()): ?>
          <td class="mono"><?= money($r['unit_price']) ?></td>
          <td class="mono <?= $r['txn_type'] === 'sale' ? 'accent-text' : ($r['txn_type'] === 'return' ? 'blue-text' : '') ?>"><?= money($r['total']) ?></td>
          <?php endif; ?>
          <td class="dim"><?= htmlspecialchars($r['party']) ?></td>
          <td class="dim small"><?= htmlspecialchars($r['extra'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('.filter-form select').select2({
        width: 'resolve'
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
