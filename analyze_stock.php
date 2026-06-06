<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

// Security: Only admins should run system-level scripts
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

// --- Handle CSV Export (Native PHP) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $query = "
        SELECT p.id, p.name, p.brand, p.quality, p.current_stock, p.cost_price, 
               (p.current_stock * p.cost_price) as frozen_capital
        FROM products p
        WHERE p.is_deleted = 0 
        AND p.current_stock > 0
        AND p.id NOT IN (
            SELECT product_id FROM stock_outflow 
            WHERE created_at > (NOW() - INTERVAL 30 DAY)
        )
        ORDER BY frozen_capital DESC";

    $res = $conn->query($query);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="dead_stock_report_' . date('Ymd') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Product Name', 'Brand', 'Quality', 'Stock Level', 'Cost Price', 'Frozen Capital']);

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['brand'],
            $row['quality'],
            $row['current_stock'],
            $row['cost_price'],
            $row['frozen_capital']
        ]);
    }
    fclose($output);
    exit;
}

$page_title = 'Inventory Intelligence';
$dead_stock = [];
$total_frozen = 0;

// Identify "Dead Stock" (Items not sold in the last 30 days)
$query = "
    SELECT p.id, p.name, p.brand, p.quality, p.current_stock, p.cost_price, 
           (p.current_stock * p.cost_price) as frozen_capital
    FROM products p
    WHERE p.is_deleted = 0 
    AND p.current_stock > 0
    AND p.id NOT IN (
        SELECT product_id FROM stock_outflow 
        WHERE created_at > (NOW() - INTERVAL 30 DAY)
    )
    ORDER BY frozen_capital DESC";

$res = $conn->query($query);
while ($row = $res->fetch_assoc()) {
    $dead_stock[] = $row;
    $total_frozen += $row['frozen_capital'];
}

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>📊 Stagnant Stock Analysis</h3>
        <?php if (!empty($dead_stock)): ?>
            <a href="?export=csv" class="btn btn-success btn-sm">📥 Download CSV Report</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 30px;">
        <div class="stats-grid compact" style="margin-bottom: 30px;">
            <div class="stat-card accent">
                <div class="stat-label">Total Frozen Capital</div>
                <div class="stat-value mono red-text"><?= money($total_frozen) ?></div>
                <div class="stat-sub">Value of items unsold in 30+ days</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Stagnant Items</div>
                <div class="stat-value"><?= count($dead_stock) ?></div>
                <div class="stat-sub">Unique models needing attention</div>
            </div>
        </div>

        <?php if (empty($dead_stock)): ?>
            <div class="empty-state ok">
                <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                <p>Inventory is healthy! All stocked items have recorded sales within the last 30 days.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table small">
                    <thead>
                        <tr>
                            <th>Product Model</th>
                            <th>Brand</th>
                            <th>Stock</th>
                            <th>Cost Price</th>
                            <th>Frozen Capital</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dead_stock as $row): ?>
                            <tr>
                                <td class="bold accent-text">
                                    <?= htmlspecialchars($row['name']) ?>
                                    <span class="dim">[<?= htmlspecialchars($row['quality']) ?>]</span>
                                </td>
                                <td><?= htmlspecialchars($row['brand']) ?></td>
                                <td class="mono"><?= $row['current_stock'] ?></td>
                                <td class="mono dim"><?= money($row['cost_price']) ?></td>
                                <td class="mono bold red-text"><?= money($row['frozen_capital']) ?></td>
                                <td>
                                    <a href="bulk_prices.php" class="btn btn-xs btn-outline">Adjust Price</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>