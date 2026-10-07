<?php
// =====================================================
// api/_helpers.php - Shared code for every API file
// Put  require __DIR__ . '/_helpers.php';  at the top of each API file.
// =====================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notify.php';
require_once __DIR__ . '/../config/orders.php';
require_once __DIR__ . '/../config/bookings.php';

// Every API answer is JSON
header('Content-Type: application/json; charset=utf-8');

// Allow the app (and Thunder Client) to call the API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Send a successful answer and stop
function respond(array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Send a successful answer to the app FIRST, then do slow extra work
// (like the WhatsApp alert) so the customer does not have to wait for it.
function respond_then(array $data, int $code, callable $after): void
{
    $json = json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    ignore_user_abort(true);
    http_response_code($code);
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    header('Content-Length: ' . strlen($json));
    header('Connection: close');
    echo $json;
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    try {
        $after();
    } catch (Throwable $e) {
        error_log('After-response work failed: ' . $e->getMessage());
    }
    exit;
}

// Send an error answer and stop
function fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only allow a certain request method (GET or POST)
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail("Use $method for this request", 405);
    }
}

// Read data sent by the app: works for JSON body and normal form data
function input(): array
{
    $json = json_decode(file_get_contents('php://input'), true);
    return is_array($json) ? array_merge($_POST, $json) : $_POST;
}

// Turn a database flower row into clean JSON for the app
function format_flower(array $f): array
{
    return [
        'id'                 => (int) $f['id'],
        'name'               => $f['name'],
        'description'        => $f['description'],
        'price'              => (float) $f['price'],
        'image_url'          => BASE_URL . $f['image'],
        'stock'              => (int) $f['stock'],
        'in_stock'           => (int) $f['stock'] > 0,
        'same_day_available' => (bool) $f['same_day_available'],
        'category_id'        => $f['category_id'] !== null ? (int) $f['category_id'] : null,
        'category_name'      => $f['category_name'] ?? null,
    ];
}

// All shop settings as ['key' => 'value']
function get_settings(PDO $pdo): array
{
    return $pdo->query("SELECT setting_key, setting_value FROM settings")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Event decoration package for the app
function format_package(array $p): array
{
    return [
        'id'             => (int) $p['id'],
        'name'           => $p['name'],
        'description'    => $p['description'] ?? '',
        'starting_price' => (float) $p['starting_price'],
        'image_url'      => $p['image'] ? BASE_URL . $p['image'] : '',
        'event_type_id'  => $p['event_type_id'] !== null ? (int) $p['event_type_id'] : null,
        'event_type'     => $p['event_type'] ?? null,
    ];
}

// Customer data that is safe to send to the app (never the password)
function format_customer(array $c): array
{
    return [
        'id'      => (int) $c['id'],
        'name'    => $c['name'],
        'phone'   => $c['phone'],
        'address' => $c['address'],
        'city'    => $c['city'],
    ];
}

// Create a new login token for a customer and return it.
// Only a hash is stored in the database, so a stolen database can't be used to log in.
function create_token(PDO $pdo, int $customer_id): string
{
    $token = bin2hex(random_bytes(32));
    $stmt  = $pdo->prepare("UPDATE customers SET api_token = ? WHERE id = ?");
    $stmt->execute([hash('sha256', $token), $customer_id]);
    return $token;
}

// Read the token the app sends in the header:  Authorization: Bearer <token>
function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') $header = $value;
        }
    }
    return preg_match('/Bearer\s+(\S+)/i', $header, $m) ? $m[1] : null;
}

// Use at the top of any API that needs a logged-in customer.
// Returns the customer row, or stops with "401 Please log in".
function require_customer(PDO $pdo): array
{
    $token = bearer_token();
    if (!$token) {
        fail('Please log in first', 401);
    }
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE api_token = ?");
    $stmt->execute([hash('sha256', $token)]);
    $customer = $stmt->fetch();
    if (!$customer) {
        fail('Session expired. Please log in again', 401);
    }
    return $customer;
}
