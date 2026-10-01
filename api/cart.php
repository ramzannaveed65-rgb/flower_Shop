<?php
// =====================================================
// api/cart.php - Shopping cart (needs login)
// Header on every request:  Authorization: Bearer <token>
//
// See cart:          GET  api/cart.php
// Add to cart:       POST api/cart.php  { "action": "add",    "flower_id": 1, "quantity": 1 }
// Change quantity:   POST api/cart.php  { "action": "update", "flower_id": 1, "quantity": 3 }
// Remove one item:   POST api/cart.php  { "action": "remove", "flower_id": 1 }
// Empty the cart:    POST api/cart.php  { "action": "clear" }
//
// Every request returns the full, updated cart.
// =====================================================

require __DIR__ . '/_helpers.php';

$customer    = require_customer($pdo);
$customer_id = (int) $customer['id'];

// Build the cart answer: items, total quantity, subtotal
function cart_response(PDO $pdo, int $customer_id, string $message = ''): void
{
    $stmt = $pdo->prepare(
        "SELECT ci.quantity, f.*, c.name AS category_name
         FROM cart_items ci
         JOIN flowers f         ON f.id = ci.flower_id
         LEFT JOIN categories c ON c.id = f.category_id
         WHERE ci.customer_id = ? AND f.is_active = 1
         ORDER BY ci.added_at, ci.id"
    );
    $stmt->execute([$customer_id]);

    $items    = [];
    $subtotal = 0;
    $count    = 0;

    foreach ($stmt->fetchAll() as $row) {
        $qty        = (int) $row['quantity'];
        $line_total = (float) $row['price'] * $qty;
        $subtotal  += $line_total;
        $count     += $qty;

        $items[] = [
            'flower'     => format_flower($row),
            'quantity'   => $qty,
            'line_total' => $line_total,
        ];
    }

    $data = ['cart' => [
        'items'       => $items,
        'total_items' => $count,
        'subtotal'    => $subtotal,
    ]];
    if ($message !== '') $data['message'] = $message;

    respond($data);
}

// ----- GET: show the cart -----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    cart_response($pdo, $customer_id);
}

require_method('POST');

$in        = input();
$action    = $in['action'] ?? '';
$flower_id = (int) ($in['flower_id'] ?? 0);
$quantity  = (int) ($in['quantity'] ?? 1);

// ----- Empty the cart -----
if ($action === 'clear') {
    $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?")->execute([$customer_id]);
    cart_response($pdo, $customer_id, 'Cart emptied');
}

// All other actions need a flower
if ($flower_id <= 0) {
    fail('flower_id is required');
}

// ----- Remove one item -----
if ($action === 'remove') {
    $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ? AND flower_id = ?")
        ->execute([$customer_id, $flower_id]);
    cart_response($pdo, $customer_id, 'Removed from cart');
}

if ($action !== 'add' && $action !== 'update') {
    fail('action must be add, update, remove or clear');
}

// ----- Check the flower exists and has stock -----
$stmt = $pdo->prepare("SELECT id, name, stock FROM flowers WHERE id = ? AND is_active = 1");
$stmt->execute([$flower_id]);
$flower = $stmt->fetch();
if (!$flower) {
    fail('Flower not found', 404);
}

// What is already in the cart for this flower?
$stmt = $pdo->prepare("SELECT quantity FROM cart_items WHERE customer_id = ? AND flower_id = ?");
$stmt->execute([$customer_id, $flower_id]);
$current = $stmt->fetchColumn();          // false if not in cart

// "add" increases, "update" sets the exact number
$new_qty = $action === 'add' ? ((int) $current + max(1, $quantity)) : $quantity;

// update with 0 or less = remove
if ($new_qty <= 0) {
    $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ? AND flower_id = ?")
        ->execute([$customer_id, $flower_id]);
    cart_response($pdo, $customer_id, 'Removed from cart');
}

$stock = (int) $flower['stock'];
if ($stock <= 0) {
    fail($flower['name'] . ' is out of stock');
}
if ($new_qty > $stock) {
    fail("Only $stock of " . $flower['name'] . ' available');
}

// ----- Save -----
if ($current === false) {
    $pdo->prepare("INSERT INTO cart_items (customer_id, flower_id, quantity) VALUES (?, ?, ?)")
        ->execute([$customer_id, $flower_id, $new_qty]);
} else {
    $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND flower_id = ?")
        ->execute([$new_qty, $customer_id, $flower_id]);
}

cart_response($pdo, $customer_id, $action === 'add' ? 'Added to cart' : 'Cart updated');
