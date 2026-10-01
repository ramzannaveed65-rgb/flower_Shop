<?php
// =====================================================
// api/login.php - Customer login with phone + password
//
// POST api/login.php
// Body (JSON): { "phone": "03001234567", "password": "secret123" }
// Returns: token (save it in the app) + customer details
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('POST');

$in       = input();
$phone    = normalize_phone($in['phone'] ?? '');
$password = (string) ($in['password'] ?? '');

if ($phone === '' || $password === '') {
    fail('Enter your mobile number and password');
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
$customer = $stmt->fetch();

// Same message for wrong phone or wrong password (don't reveal which one)
if (!$customer || !password_verify($password, $customer['password_hash'])) {
    fail('Wrong mobile number or password', 401);
}

// New token on every login (logs out the old phone/session)
$token = create_token($pdo, (int) $customer['id']);

respond([
    'message'  => 'Logged in',
    'token'    => $token,
    'customer' => format_customer($customer),
]);
