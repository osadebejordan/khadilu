<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$page_title = 'Dashboard';
require_once 'includes/header.php';

// ── Stats ──────────────────────────────────────────────
$total_products = $conn->query("SELECT COUNT(*) AS c FROM products WHERE is_deleted = 0")->fetch_assoc()['c'];
$total_stock    = $conn->query("SELECT SUM(current_stock) AS c FROM products WHERE is_deleted = 0")->fetch_assoc()['c'] ?? 0;
$low_stock_list = $conn->query("SELECT * FROM products WHERE current_stock <= low_stock_threshold AND is_deleted = 0 ORDER BY current_stock ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$out_of_stock   = $conn->query("SELECT COUNT(*) AS c FROM products WHERE current_stock = 0 AND is_deleted = 0")->fetch_assoc()['c'];

// Inventory value
$inv_value = $conn->query("SELECT SUM(current_stock * selling_price) AS v FROM products WHERE is_deleted = 0")->fetch_assoc()['v'] ?? 0;

// Today's sales
$today = date('Y-m-d');
$today_sales = $conn->query("SELECT SUM(total_amount) AS v, COUNT(*) AS c FROM stock_outflow WHERE DATE(created_at) = '$today'")->fetch_assoc();
$today_revenue = $today_sales['v'] ?? 0;
$today_txn     = $today_sales['c'] ?? 0;

// This month
$month_start = date('Y-m-01');
$month_sales = $conn->query("SELECT SUM(total_amount) AS v FROM stock_outflow WHERE created_at >= '$month_start'")->fetch_assoc()['v'] ?? 0;

// Total Debt calculation
$total_debt = $conn->query("SELECT SUM(balance) AS v FROM view_customer_balances WHERE balance > 0")->fetch_assoc()['v'] ?? 0;

// Ready repairs count
$ready_repairs = $conn->query("SELECT COUNT(*) AS c FROM repairs WHERE status = 'Ready'")->fetch_assoc()['c'] ?? 0;

// Recent transactions
$recent = $conn->query("
  SELECT o.created_at, o.quantity, o.total_amount, o.customer_name, o.payment_method, p.name AS product_name, p.quality, p.id as product_id
  FROM stock_outflow o JOIN products p ON o.product_id = p.id
  ORDER BY o.created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Recent inflow
$recent_in = $conn->query("
  SELECT i.created_at, i.quantity, i.total_cost, i.supplier, p.name AS product_name, p.brand, p.quality, p.id as product_id
  FROM stock_inflow i JOIN products p ON i.product_id = p.id
  ORDER BY i.created_at DESC LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

// Chart Data: Last 7 Days Sales
$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i days"));
  $chart_labels[] = date('D', strtotime($date));
  $val = $conn->query("SELECT SUM(total_amount) as v FROM stock_outflow WHERE DATE(created_at) = '$date'")->fetch_assoc()['v'] ?? 0;
  $chart_values[] = (float)$val;
}

// Prepare low stock notification
$notification = '';
if ($out_of_stock > 0) {
  $notification = renderToast("⚠️ Attention: You have $out_of_stock items completely out of stock!", 'error');
} elseif (!empty($low_stock_list)) {
  $low_count = count($low_stock_list);
  $notification = renderToast("💡 Notice: $low_count items are reaching low stock thresholds.", 'success');
}
?>

<style>
  /* Fix for large numbers in stat cards */
  .stat-card {
    min-width: 0;
    /* Allows ellipsis to work in flex/grid */
  }

  .stat-value {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: clamp(1.2rem, 3vw, 2.2rem);
    /* Dynamically shrinks text as screen gets smaller */
    display: block;
    width: 100%;
    cursor: pointer;
  }
</style>

<?= $notification ?>

<div class="stats-grid">
  <?php if (isAdmin()): ?>
    <a href="history.php?type=sales" class="stat-card accent" style="text-decoration:none;">
      <div class="stat-icon">💰</div>
      <div class="stat-label">Today's Gross</div>
      <div class="stat-value" title="<?= money($today_revenue) ?>"><?= shortMoney($today_revenue) ?></div>
      <div class="stat-sub"><?= $today_txn ?> sale<?= $today_txn != 1 ? 's' : '' ?> today</div>
    </a>
    <a href="closing_report.php" class="stat-card" style="text-decoration:none;">
      <div class="stat-icon">📊</div>
      <div class="stat-label">Monthly Volume</div>
      <div class="stat-value" title="<?= money($month_sales) ?>"><?= shortMoney($month_sales) ?></div>
      <div class="stat-sub"><?= date('F Y') ?></div>
    </a>
  <?php endif; ?>
  <?php if (isAdmin()): ?>
    <a href="inventory.php" class="stat-card" style="text-decoration:none;">
      <div class="stat-icon">📦</div>
      <div class="stat-label">Stock Value</div>
      <div class="stat-value" title="<?= money($inv_value) ?>"><?= shortMoney($inv_value) ?></div>
      <div class="stat-sub"><?= number_format($total_stock) ?> units in stock</div>
    </a>
    <a href="customers.php?filter=debt" class="stat-card danger" style="text-decoration:none;">
      <div class="stat-icon">🛑</div>
      <div class="stat-label">Total Debt</div>
      <div class="stat-value" title="<?= money($total_debt) ?>"><?= shortMoney($total_debt) ?></div>
      <div class="stat-sub">Outstanding Amount</div>
    </a>
  <?php endif; ?>
  <div class="stat-card <?= $ready_repairs > 0 ? 'accent' : '' ?>">
    <div class="stat-icon">🔧</div>
    <div class="stat-label">Ready Repairs</div>
    <div class="stat-value"><?= $ready_repairs ?></div>
    <div class="stat-sub">Waiting for pickup · <a href="repairs.php?status=Ready" class="bold underline" style="color: inherit;">View Jobs</a></div>
  </div>
  <div class="stat-card <?= $out_of_stock > 0 ? 'danger' : '' ?>">
    <div class="stat-icon">⚠️</div>
    <div class="stat-label">Products</div>
    <div class="stat-value"><?= $total_products ?></div>
    <div class="stat-sub"><?= $out_of_stock ?> out of stock</div>
  </div>
</div>

<div class="dash-grid">
  <div class="card" style="grid-column: 1 / -1;">
    <div class="card-header">
      <h3>Sales Performance (Last 7 Days)</h3>
    </div>
    <div style="padding: 20px; height: 300px; position: relative;">
      <div id="chart-loader" class="spinner-container">
        <div class="spinner"></div>
      </div>
      <canvas id="salesChart"></canvas>
    </div>
  </div>
  <!-- Recent Sales -->
  <div class="card">
    <div class="card-header" style="background: rgba(255,255,255,0.02)">
      <h3>Recent Sales</h3>
      <a href="record_sale.php" class="btn btn-sm btn-ghost">New Sale</a>
    </div>
    <?php if (empty($recent)): ?>
      <div class="empty-state">No sales recorded yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Device Model</th>
              <th>Qty</th><?php if (isAdmin()): ?><th>Amount</th><?php endif; ?><th>Buyer</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td class="bold accent-text">
                  <span class="view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
                    <?= htmlspecialchars($r['product_name']) ?>
                  </span>
                  <span class="bold">[<?= htmlspecialchars($r['quality']) ?>]</span>
                </td>
                <td><span class="pill pill-red">–<?= $r['quantity'] ?></span></td>
                <?php if (isAdmin()): ?>
                  <td class="mono accent-text"><?= money($r['total_amount']) ?></td>
                <?php endif; ?>
                <td class="dim"><?= htmlspecialchars($r['customer_name']) ?></td>
                <td class="dim small"><?= timeAgo($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Low Stock Alerts -->
  <div class="card">
    <div class="card-header">
      <h3>Stock Alerts</h3>
      <a href="inventory.php" class="btn btn-sm btn-ghost">View All</a>
    </div>
    <?php if (empty($low_stock_list)): ?>
      <div class="empty-state ok">✓ All products are well-stocked.</div>
    <?php else: ?>
      <div class="alerts-list">
        <?php foreach ($low_stock_list as $item): ?>
          <div class="alert-row <?= $item['current_stock'] == 0 ? 'out' : 'low' ?>">
            <div class="alert-info">
              <div class="alert-name">
                <?= htmlspecialchars($item['name']) ?>
                <span class="bold">[<?= htmlspecialchars($item['quality']) ?>]</span>
              </div>
              <div class="alert-brand"><?= htmlspecialchars($item['brand'] ?? '—') ?></div>
            </div>
            <div class="alert-stock">
              <span class="stock-num <?= $item['current_stock'] == 0 ? 'red' : 'yellow' ?>">
                <?= $item['current_stock'] == 0 ? 'OUT' : $item['current_stock'] . ' left' ?>
              </span>
            </div>
            <?php if (isAdmin()): ?>
              <a href="bulk_stock.php?product_id=<?= $item['id'] ?>" class="btn btn-xs btn-outline">Restock</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card-divider"></div>
    <div class="card-header">
      <h3>Recent Inflow</h3>
      <?php if (isAdmin()): ?>
        <a href="bulk_stock.php" class="btn btn-sm btn-ghost">Add Stock</a>
      <?php endif; ?>
    </div>
    <?php if (empty($recent_in)): ?>
      <div class="empty-state">No stock added yet.</div>
    <?php else: ?>
      <?php foreach ($recent_in as $r): ?>
        <div class="inflow-row">
          <div class="inflow-info">
            <div class="inflow-name view-product-link" onclick="viewProduct(<?= $r['product_id'] ?>)">
              <?= htmlspecialchars($r['product_name']) ?> <span class="bold">[<?= htmlspecialchars($r['quality']) ?>]</span>
            </div>
            <div class="bold small accent-text"><?= htmlspecialchars($r['brand'] ?? '—') ?></div>
            <div class="dim small"><?= htmlspecialchars($r['supplier']) ?> · <?= timeAgo($r['created_at']) ?></div>
          </div>
          <span class="pill pill-green">+<?= $r['quantity'] ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
  const ctx = document.getElementById('salesChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [{
        label: 'Daily Revenue (₦)',
        data: <?= json_encode($chart_values) ?>,
        borderColor: '#38bdf8',
        backgroundColor: 'rgba(56, 189, 248, 0.1)',
        borderWidth: 3,
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      onProgress: function(animation) {
        // Fallback: if animation is taking time, keep showing loader
      },
      animation: {
        onComplete: function() {
          const loader = document.getElementById('chart-loader');
          if (loader) {
            // Add a tiny delay so the user actually sees the spin
            setTimeout(() => {
              loader.style.opacity = '0';
              setTimeout(() => loader.style.display = 'none', 300);
            }, 400);
          }
        }
      },
      scales: {
        y: {
          grid: {
            color: 'rgba(255,255,255,0.05)'
          },
          ticks: {
            color: '#94a3b8'
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            color: '#94a3b8'
          }
        }
      }
    }
  });
</script>

<?php require_once 'includes/footer.php'; ?>