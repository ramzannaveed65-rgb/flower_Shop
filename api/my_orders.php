<?php
// =====================================================
// api/my_orders.php - Customer's orders (needs login)
// Header: Authorization: Bearer <token>
//
// All my orders (newest first):  GET api/my_orders.php
// One order with full details:   GET api/my_orders.php?id=7
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('GET');

$customer    = require_customer($pdo);
$customer_id = (int) $customer['id'];

// ----- One order -----
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([(int) $_GET['id'], $customer_id]);
    $order = $stmt->fetch();
    if (!$order) {
        fail('Order not found', 404);
    }
    respond(['order' => format_order($pdo, $order)]);
}

// ----- All orders -----
$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$customer_id]);
$orders = array_map(fn($o) => format_order($pdo, $o), $stmt->fetchAll());

respond([
    'count'  => count($orders),
    'orders' => $orders,
]);
