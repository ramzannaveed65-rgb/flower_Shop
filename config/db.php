<?php
// =====================================================
// config/db.php - Database connection
// Every API and admin file includes this file.
// =====================================================

// Address of this project, built from the address the request came in on.
// Browser -> http://localhost/flower_shop/
// Emulator -> http://10.0.2.2/flower_shop/   Real phone -> http://192.168.x.x/flower_shop/
// So photo links always work on the device that asked for them.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', "$scheme://$host/flower_shop/");

// Pakistan time for same-day cutoff, delivery dates and order numbers
date_default_timezone_set('Asia/Karachi');

$DB_HOST = 'localhost';
$DB_NAME = 'flower_shop';
$DB_USER = 'root';   // XAMPP default user
$DB_PASS = '';       // XAMPP default: no password

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // show SQL errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as ['name' => ...]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements (safer)
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Is MySQL running in XAMPP?',
    ]);
    exit;
}
