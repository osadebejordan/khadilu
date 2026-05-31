<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$transaction_id = intval($_GET['txn_id'] ?? 0);
if (!$transaction_id) {
    die("Invalid transaction ID.");
}

// Fetch main transaction details
$txn_stmt = $conn->prepare("SELECT st.*, u.full_name as user_name FROM sales_transactions st JOIN users u ON st.user_id = u.id WHERE st.id = ?");
$txn_stmt->bind_param("i", $transaction_id);
$txn_stmt->execute();
$transaction = $txn_stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die("Transaction not found.");
}

// Fetch all items associated with this transaction
$items_stmt = $conn->prepare("SELECT so.*, p.name as product_name, p.brand, p.quality, p.sku FROM stock_outflow so JOIN products p ON so.product_id = p.id WHERE so.transaction_id = ?");
$items_stmt->bind_param("i", $transaction_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Receipt #TXN-' . str_pad($transaction['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: #fff; color: #333; font-family: 'Inter', sans-serif; padding: 20px; }
        .receipt-container { max-width: 500px; margin: 0 auto; border: 1px solid #eee; padding: 25px; box-shadow: 0 0 10px rgba(0,0,0,0.1); background: #fff; }
        .receipt-header { text-align: center; margin-bottom: 20px; }
        .receipt-header h1 { font-size: 1.8rem; margin-bottom: 5px; color: #00d4aa; }
        .receipt-header p { font-size: 0.9rem; color: #666; }
        .receipt-details { margin-bottom: 20px; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 10px 0; }
        .receipt-details div { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .receipt-details div span:first-child { font-weight: bold; }
        .receipt-items table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .receipt-items th, .receipt-items td { padding: 8px 0; text-align: left; border-bottom: 1px solid #eee; }
        .receipt-items th { font-size: 0.8rem; text-transform: uppercase; color: #666; }
        .receipt-items td { font-size: 0.9rem; }
        .receipt-items .total-row { font-weight: bold; border-top: 2px solid #333; }
        .receipt-footer { text-align: center; font-size: 0.8rem; color: #666; margin-top: 20px; }
        .receipt-footer p { margin-bottom: 5px; }
        .print-button { text-align: center; margin-top: 20px; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-container { border: none; box-shadow: none; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1><?= APP_NAME ?></h1>
            <p>Transaction Receipt</p>
            <p>Date: <?= date('d M Y H:i', strtotime($transaction['created_at'])) ?></p>
        </div>

        <div class="receipt-details">
            <div><span>Transaction ID:</span> <span>#TXN-<?= str_pad($transaction['id'], 4, '0', STR_PAD_LEFT) ?></span></div>
            <div><span>Customer:</span> <span><?= htmlspecialchars($transaction['customer_name']) ?></span></div>
            <div><span>Served By:</span> <span><?= htmlspecialchars($transaction['user_name']) ?></span></div>
            <div><span>Payment Method:</span> <span><?= htmlspecialchars($transaction['payment_method']) ?></span></div>
        </div>

        <div class="receipt-items">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?> <span class="bold">[<?= htmlspecialchars($item['quality']) ?>]</span></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= money($item['price_per_unit']) ?></td>
                        <td><?= money($item['total_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3">Subtotal:</td>
                        <td><?= money($transaction['total_cart_value']) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3">Amount Paid:</td>
                        <td><?= money($transaction['total_paid']) ?></td>
                    </tr>
                    <?php if ($transaction['total_cart_value'] > $transaction['total_paid']): ?>
                    <tr class="total-row">
                        <td colspan="3">Balance Due:</td>
                        <td><?= money($transaction['total_cart_value'] - $transaction['total_paid']) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="receipt-footer">
            <?php if ($transaction['notes']): ?><p>Notes: <?= htmlspecialchars($transaction['notes']) ?></p><?php endif; ?>
            <p>Thank you for your business!</p>
        </div>
    </div>
    <div class="print-button">
        <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
    </div>
</body>
</html>