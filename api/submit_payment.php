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

$order_id       = (int) ($in['order_id'] ?? 0);
$transaction_id = strtoupper(preg_replace('/\s+/', '', (string) ($in['transaction_id'] ?? '')));
$sender_number  = normalize_phone((string) ($in['sender_number'] ?? ''));

// ----- Check the order -----
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$order_id, (int) $customer['id']]);
$order = $stmt->fetch();

if (!$order)                                         fail('Order not found', 404);
if ($order['payment_method'] === 'cod')              fail('This order is Cash on Delivery. No payment proof needed');
if ($order['order_status'] === 'cancelled')          fail('This order was cancelled');
if ($order['payment_status'] === 'submitted')        fail('Payment already submitted. Please wait for the shop to check it');
if ($order['payment_status'] === 'verified')         fail('Payment already verified');

// ----- Check the Transaction ID -----
if (!preg_match('/^[A-Z0-9-]{6,40}$/', $transaction_id)) {
    fail('Enter the Transaction ID from your NayaPay/Easypaisa message (letters and numbers)');
}
if ($sender_number !== '' && !preg_match('/^03\d{9}$/', $sender_number)) {
    fail('Sender number must look like 03001234567');
}
$stmt = $pdo->prepare("SELECT id FROM payments WHERE transaction_id = ?");
$stmt->execute([$transaction_id]);
if ($stmt->fetch()) {
    fail('This Transaction ID has already been used', 409);
}

// ----- Check and save the screenshot -----
$file = $_FILES['screenshot'] ?? null;
if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    fail('Please attach a screenshot of the payment');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('Screenshot upload failed. Try a smaller image');
}
if ($file['size'] > 5 * 1024 * 1024) {
    fail('Screenshot is too big (max 5 MB)');
}

// getimagesize() reads the real file content, so a renamed .php file is rejected
$info    = @getimagesize($file['tmp_name']);
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
if (!$info || !isset($allowed[$info[2]])) {
    fail('Screenshot must be a JPG, PNG or WEBP image');
}

$folder = __DIR__ . '/../uploads/payments/';
if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
    fail('Server could not create the uploads folder', 500);
}
// Random file name: nobody can guess other customers' screenshots
$filename = $order['order_number'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
    fail('Could not save the screenshot', 500);
}

// ----- Save the payment -----
try {
    $pdo->beginTransaction();
    $pdo->prepare(
        "INSERT INTO payments (order_id, method, transaction_id, sender_number, amount, screenshot)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        $order_id, $order['payment_method'], $transaction_id,
        $sender_number !== '' ? $sender_number : null,
        $order['total'], 'uploads/payments/' . $filename,
    ]);
    $pdo->prepare("UPDATE orders SET payment_status = 'submitted' WHERE id = ?")->execute([$order_id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($folder . $filename);
    fail('Could not save the payment. Please try again', 500);
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);

respond([
    'message' => 'Payment submitted. The shop will confirm it soon',
    'order'   => format_order($pdo, $stmt->fetch()),
]);
