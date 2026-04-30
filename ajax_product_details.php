<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

header('Content-Type: application/json');
if ($product) {
    echo json_encode($product);
} else {
    echo json_encode(['error' => 'Product not found']);
}