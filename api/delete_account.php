<?php
// =====================================================
// api/delete_account.php - Customer deletes their own account (needs login)
// Header: Authorization: Bearer <token>
//
// POST api/delete_account.php
// Body (JSON): { "password": "the account password" }
//
// The password is asked again so nobody else holding the phone
// can delete the account by mistake.
// =====================================================

require __DIR__ . '/_helpers.php';
require __DIR__ . '/../config/account.php';
require_method('POST');

$customer = require_customer($pdo);
$in       = input();
$password = (string) ($in['password'] ?? '');

if ($password === '') {
    fail('Enter your password to delete the account');
}

$stmt = $pdo->prepare("SELECT password_hash FROM customers WHERE id = ?");
$stmt->execute([(int) $customer['id']]);
if (!password_verify($password, (string) $stmt->fetchColumn())) {
    fail('Wrong password. The account was not deleted', 403);
}

try {
    delete_customer_account($pdo, (int) $customer['id']);
} catch (Throwable $e) {
    fail('Could not delete the account. Please try again', 500);
}

respond(['message' => 'Your account has been deleted']);
