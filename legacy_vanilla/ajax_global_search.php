<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$searchTerm = "%$query%";
$results = [];

// 1. Search Products (Inventory)
$stmt = $conn->prepare("SELECT id, name, brand, sku, quality, current_stock FROM products WHERE (name LIKE ? OR sku LIKE ? OR brand LIKE ?) AND is_deleted = 0 LIMIT 5");
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($res as $row) {
    $results[] = [
        'category' => 'Inventory',
        'title'    => $row['brand'] . ' ' . $row['name'],
        'sub'      => "SKU: " . ($row['sku'] ?: 'N/A') . " • Quality: " . $row['quality'],
        'meta'     => $row['current_stock'] . " in stock",
        'url'      => "inventory.php?q=" . urlencode($row['sku'] ?: $row['name']),
        'icon'     => '📦'
    ];
}

// 2. Search Customers
$stmt = $conn->prepare("SELECT id, name, phone, balance FROM view_customer_balances WHERE name LIKE ? OR phone LIKE ? LIMIT 3");
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($res as $row) {
    $results[] = [
        'category' => 'Customers',
        'title'    => $row['name'],
        'sub'      => $row['phone'],
        'meta'     => $row['balance'] > 0 ? "Debt: ₦" . number_format($row['balance'], 0) : "Clear",
        'url'      => "customer_details.php?id=" . $row['id'],
        'icon'     => '👤'
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
exit;
