<?php
// =====================================================
// config/db.php - Database connection
// Every API and admin file includes this file.
// =====================================================

// Address of this project, built from the address the request came in on.
// Browser on your PC  -> http://localhost/flower_shop/
// Paid hosting        -> https://yourdomain.com/      (when the files are in public_html)
// So links and photo addresses are always right, wherever the project is placed.
// Behind a hosting proxy, the real scheme arrives in X-Forwarded-Proto
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Which folder of the website is this project in?  "/flower_shop/" on XAMPP, "/" on hosting
$docroot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$project = rtrim(str_replace('\\', '/', (string) realpath(__DIR__ . '/..')), '/');
$folder  = ($docroot !== '' && stripos($project, $docroot) === 0)
         ? substr($project, strlen($docroot))
         : '/flower_shop';
define('BASE_PATH', rtrim($folder, '/') . '/');          // "/flower_shop/" or "/"
define('BASE_URL', "$scheme://$host" . BASE_PATH);

// Pakistan time for same-day cutoff, delivery dates and order numbers
date_default_timezone_set('Asia/Karachi');

// Where the database details come from:
//  1. config/local.php  - on paid hosting (cPanel). Copy local.sample.php, rename it and fill it in.
//  2. Railway variables - on Railway.
//  3. XAMPP defaults    - on your PC.
$local = is_file(__DIR__ . '/local.php') ? (array) require __DIR__ . '/local.php' : [];

$DB_HOST = $local['host'] ?? (getenv('MYSQLHOST') ?: 'localhost');
$DB_PORT = $local['port'] ?? (getenv('MYSQLPORT') ?: '3306');
$DB_NAME = $local['name'] ?? (getenv('MYSQLDATABASE') ?: 'flower_shop');
$DB_USER = $local['user'] ?? (getenv('MYSQLUSER') ?: 'root');                                  // XAMPP default user
$DB_PASS = $local['pass'] ?? (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : ''); // XAMPP default: no password

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // show SQL errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as ['name' => ...]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements (safer)
        ]
    );
    // Order/booking times in Pakistan time (hosting servers usually run in UTC)
    $pdo->exec("SET time_zone = '+05:00'");
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'The shop is not available right now. Please try again in a few minutes.',
    ]);
    exit;
}
