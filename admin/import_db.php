<?php
// =====================================================
// admin/import_db.php - ONE-TIME database setup on Railway
//
// Works only while the Railway variable IMPORT_KEY is set:
//   https://YOUR-APP.up.railway.app/flower_shop/admin/import_db.php?key=YOUR_IMPORT_KEY
//
// Imports database/flower_shop_data.sql if it exists (your own data exported
// from phpMyAdmin), otherwise database/flower_shop_database.sql (fresh database).
// Delete the IMPORT_KEY variable in Railway when it is done.
// =====================================================

$key = getenv('IMPORT_KEY');
if (!$key || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

$dir  = __DIR__ . '/../database/';
$file = is_file($dir . 'flower_shop_data.sql') ? 'flower_shop_data.sql' : 'flower_shop_database.sql';
if (!is_file($dir . $file)) {
    exit("No SQL file found in database/");
}

// Already has tables? Only replace when asked
$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")
              ->fetchAll(PDO::FETCH_COLUMN);
if ($tables && ($_GET['confirm'] ?? '') !== 'replace') {
    exit("The database already has " . count($tables) . " tables (" . implode(', ', $tables) . ").\n"
       . "Nothing was changed. To DELETE them and import again, add  &confirm=replace  to the address.");
}

// Split the file into single statements
$sql = file_get_contents($dir . $file);
$sql = str_replace("\r\n", "\n", $sql);
$lines = array_filter(explode("\n", $sql), function ($line) {
    $t = ltrim($line);
    return $t !== '' && !str_starts_with($t, '--');           // drop comment lines
});
$statements = array_filter(array_map('trim', preg_split('/;\s*$/m', implode("\n", $lines))));
// Railway already gives us a database: skip CREATE DATABASE / USE statements
$statements = array_filter($statements, fn($st) => !preg_match('/^(CREATE\s+DATABASE|USE\s)/i', $st));

$done = 0;
$st   = '';
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $t) . '`');
    }
    foreach ($statements as $st) {
        $pdo->exec($st);
        $done++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (PDOException $e) {
    http_response_code(500);
    exit("Stopped at statement " . ($done + 1) . ":\n" . mb_substr($st, 0, 300) . "\n\nError: " . $e->getMessage());
}

$count = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
echo "Done! Imported $file: $done statements, $count tables.\n\n";
echo "Now:\n";
echo "1. Delete the IMPORT_KEY variable in Railway (this page then stops working).\n";
echo "2. Log in to the admin panel and change the admin password.\n";
