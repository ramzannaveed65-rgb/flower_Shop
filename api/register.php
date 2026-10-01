<?php
// =====================================================
// api/register.php - Create a customer account
//
// POST api/register.php
// Body (JSON): { "name": "Ali Khan", "phone": "03001234567",
//                "password": "secret123", "address": "...", "city": "Multan" }
// Returns: token (save it in the app) + customer details
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('POST');

$in       = input();
$name     = trim($in['name'] ?? '');
$phone    = normalize_phone($in['phone'] ?? '');
$password = (string) ($in['password'] ?? '');
$address  = trim($in['address'] ?? '');
$city     = trim($in['city'] ?? '');

// ----- Check the data -----
if (mb_strlen($name) < 3) {
    fail('Please enter your full name');
}
if (!preg_match('/^03\d{9}$/', $phone)) {
    fail('Enter a valid mobile number, e.g. 03001234567');
}
if (strlen($password) < 6) {
    fail('Password must be at least 6 characters');
}

// ----- Phone already registered? -----
$stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    fail('This mobile number is already registered. Please log in', 409);
}

// ----- Save the customer -----
$stmt = $pdo->prepare(
    "INSERT INTO customers (name, phone, password_hash, address, city)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([
    $name,
    $phone,
    password_hash($password, PASSWORD_DEFAULT),
    $address !== '' ? $address : null,
    $city !== '' ? $city : null,
]);
$id = (int) $pdo->lastInsertId();

// ----- Log them in straight away -----
$token = create_token($pdo, $id);

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);

respond([
    'message'  => 'Account created',
    'token'    => $token,
    'customer' => format_customer($stmt->fetch()),
], 201);
