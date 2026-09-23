<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$page_title = 'Low Stock Report';

// Fetch low stock items
$low_stock_items = $conn->query("
    SELECT id, name, brand, category, quality, sku, current_stock, low_stock_threshold, cost_price, selling_price
    FROM products
    WHERE current_stock <= low_stock_threshold AND is_deleted = 0
    ORDER BY current_stock ASC, name ASC
")->fetch_all(MYSQLI_ASSOC);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="low_stock_report_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, [
        'ID', 'Product Name', 'Brand', 'Category', 'Quality', 'SKU/IMEI',
        'Current Stock', 'Low Stock Threshold', 'Cost Price', 'Selling Price'
    ]);

    // CSV Data
    foreach ($low_stock_items as $item) {
        fputcsv($output, [
            $item['id'],
            $item['name'],
            $item['brand'],
            $item['category'],
            $item['quality'],
            $item['sku'],
            $item['current_stock'],
            $item['low_stock_threshold'],
            $item['cost_price'],
            $item['selling_price']
        ]);
    }

    fclose($output);
    exit();
}

require_once 'includes/header.php';
?>

<div class="toolbar">
    <div class="toolbar-left">
        <h3>Low Stock Items</h3>
    </div>
    <div class="toolbar-right">
        <a href="?export=csv" class="btn btn-outline">Export to CSV</a>
        <button onclick="window.print()" class="btn btn-ghost">Print to PDF</button>
    </div>
</div>

<div class="card">
    <?php if (empty($low_stock_items)): ?>
        <div class="empty-state ok">
            ✓ All products are well-stocked. No low stock items found.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Quality</th>
                        <th>SKU/IMEI</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_items as $item): ?>
                        <tr>
                            <td class="dim small"><?= $item['id'] ?></td>
                            <td class="bold accent-text">
                                <span class="view-product-link" onclick="viewProduct(<?= $item['id'] ?>)">
                                    <?= htmlspecialchars($item['name']) ?>
                                </span>
                            </td>
                            <td class="dim"><?= htmlspecialchars($item['brand'] ?? '—') ?></td>
                            <td><span class="tag"><?= htmlspecialchars($item['category']) ?></span></td> 
                            <td><span class="pill pill-green" style="font-size:0.7rem"><?= htmlspecialchars($item['quality']) ?></span></td>
                            <td class="mono small dim"><?= htmlspecialchars($item['sku'] ?? '—') ?></td>
                            <td>
                                <span class="stock-badge <?= $item['current_stock'] == 0 ? 'red' : 'yellow' ?>">
                                    <?= $item['current_stock'] ?>
                                </span>
                            </td>
                            <td><?= $item['low_stock_threshold'] ?></td>
                            <td class="mono"><?= money($item['cost_price']) ?></td>
                            <td class="mono accent-text"><?= money($item['selling_price']) ?></td>
                            <td>
                                <a href="bulk_stock.php?product_id=<?= $item['id'] ?>" class="btn btn-xs btn-outline">Restock</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>