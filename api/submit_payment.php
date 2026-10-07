<?php
// =====================================================
// api/submit_payment.php - Send NayaPay / Easypaisa payment proof (needs login)
// Header: Authorization: Bearer <token>
//
// POST api/submit_payment.php   (multipart/form-data, because of the photo)
//   order_id        = 7
//   transaction_id  = 12345678901      (TID from the NayaPay/Easypaisa SMS)
//   sender_number   = 03001234567      (optional: number the money came from)
//   screenshot      = <image file>     (JPG, PNG or WEBP, max 5 MB)
//
// After this, the order's payment_status becomes "submitted".
// The shop owner checks it in the admin panel and marks it verified or rejected.
// If rejected, the customer can submit again.
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('POST');

$customer = require_customer($pdo);
$in       = input();

$order_id = (int) ($in['order_id'] ?? 0);

// ----- Check the order belongs to this customer -----
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$order_id, (int) $customer['id']]);
$order = $stmt->fetch();
if (!$order) fail('Order not found', 404);

// All the rules live in config/orders.php (shared with the website)
try {
    submit_payment_proof(
        $pdo, $order,
        (string) ($in['transaction_id'] ?? ''),
        (string) ($in['sender_number'] ?? ''),
        $_FILES['screenshot'] ?? null
    );
} catch (ShopError $e) {
    fail($e->getMessage(), $e->status);
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);

respond([
    'message' => 'Payment submitted. The shop will confirm it soon',
    'order'   => format_order($pdo, $stmt->fetch()),
]);
