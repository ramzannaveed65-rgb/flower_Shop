<?php
// =====================================================
// site/_init.php - Shared code for every page of the customer website
// Every page starts with:  require __DIR__ . '/site/_init.php';
// =====================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notify.php';
require_once __DIR__ . '/../config/orders.php';
require_once __DIR__ . '/../config/bookings.php';
require_once __DIR__ . '/../config/migrate.php';

// Customers stay logged in (and keep their cart) for 30 days
session_name('flower_site');
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => BASE_PATH,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
run_migrations($pdo, $settings);      // adds new database columns after an update

$shop_name = $settings['shop_name'] ?? 'Flower Shop';

// ---------- Small helpers ----------

// Safe output in HTML (stops <script> tricks)
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rs($amount): string
{
    return 'Rs ' . number_format((float) $amount);
}

// Link to a page of the website:  url('shop.php?cat=2')  ->  /flower_shop/shop.php?cat=2
function url(string $path = ''): string
{
    return BASE_PATH . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Send the visitor to the next page FIRST, then do slow extra work
// (the WhatsApp alert to the shop), so the customer does not wait for it.
function redirect_then(string $path, callable $after): void
{
    session_write_close();          // let the next page open without waiting
    ignore_user_abort(true);
    header('Location: ' . url($path));
    header('Content-Length: 0');
    header('Connection: close');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
    try {
        $after();
    } catch (Throwable $e) {
        error_log('After-redirect work failed: ' . $e->getMessage());
    }
    exit;
}

// "out_for_delivery" -> "Out for delivery"
function nice(string $s): string
{
    return ucfirst(str_replace('_', ' ', $s));
}

function payment_label(string $method): string
{
    return ['cod' => 'Cash on Delivery', 'nayapay' => 'NayaPay', 'easypaisa' => 'Easypaisa'][$method] ?? $method;
}

// "2026-10-08" -> "Thu, 8 Oct 2026"
function nice_date(string $ymd): string
{
    $t = strtotime($ymd);
    return $t ? date('D, j M Y', $t) : $ymd;
}

// Coloured label for an order or payment status
function status_badge(string $status, ?string $label = null): string
{
    $kinds = [
        'pending' => 'wait', 'unpaid' => 'wait', 'new' => 'wait',
        'confirmed' => 'info', 'submitted' => 'info', 'out_for_delivery' => 'info', 'contacted' => 'info',
        'delivered' => 'good', 'verified' => 'good', 'completed' => 'good',
        'cancelled' => 'bad', 'rejected' => 'bad',
    ];
    return '<span class="status status-' . ($kinds[$status] ?? 'wait') . '">' . e($label ?? nice($status)) . '</span>';
}

// Photo address, or '' when the photo file is missing (a placeholder is shown instead)
function photo(?string $path): string
{
    return ($path && is_file(__DIR__ . '/../' . $path)) ? url($path) : '';
}

// "0300-1234567" -> "923001234567" for a wa.me link
function wa_link(string $number, string $text = ''): string
{
    $n = ltrim(whatsapp_number($number), '+');
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

// ---------- Messages shown once on the next page ----------

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function show_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">' . e($msg)
               . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Form protection (every POST form must include csrf_field()) ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('This page was open for too long. Please go back, refresh the page and try again.');
    }
}

// ---------- Customer login ----------

// The logged-in customer, or null
function current_customer(PDO $pdo): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $cached = null;
    if (!empty($_SESSION['customer_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([(int) $_SESSION['customer_id']]);
        $cached = $stmt->fetch() ?: null;
        if (!$cached || str_starts_with($cached['phone'], 'del-')) {     // account was deleted
            unset($_SESSION['customer_id']);
            $cached = null;
        }
    }
    return $cached;
}

// Pages that need a login send the visitor to the login page and bring them back after
function require_login(PDO $pdo): array
{
    $customer = current_customer($pdo);
    if (!$customer) {
        $back = basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        flash('Please log in to continue.', 'info');
        redirect('login.php?next=' . rawurlencode($back));
    }
    return $customer;
}

// Only allow going back to a page of this website (never to another site)
function safe_next(?string $next, string $default = 'index.php'): string
{
    $next = (string) $next;
    return preg_match('~^[a-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.-]*)?$~', $next) ? $next : $default;
}

// ---------- Cart (kept in the visitor's session until they order) ----------

function cart_add(int $flower_id, int $qty): void
{
    $_SESSION['cart'][$flower_id] = max(1, min(99, ($_SESSION['cart'][$flower_id] ?? 0) + $qty));
}

function cart_set(int $flower_id, int $qty): void
{
    if ($qty < 1) {
        unset($_SESSION['cart'][$flower_id]);
    } else {
        $_SESSION['cart'][$flower_id] = min(99, $qty);
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

// How many items are in the cart (for the number next to the cart icon)
function cart_count(): int
{
    return (int) array_sum($_SESSION['cart'] ?? []);
}

// The cart with flower details. Items that were hidden or deleted are dropped,
// and quantities are lowered to what is in stock.
function cart_details(PDO $pdo): array
{
    $lines    = [];
    $subtotal = 0;
    $notes    = [];
    $find     = $pdo->prepare("SELECT * FROM flowers WHERE id = ? AND is_active = 1");

    foreach ($_SESSION['cart'] ?? [] as $flower_id => $qty) {
        $find->execute([(int) $flower_id]);
        $f = $find->fetch();
        if (!$f) {
            unset($_SESSION['cart'][$flower_id]);
            $notes[] = 'An item in your cart is no longer available and was removed.';
            continue;
        }
        $stock = (int) $f['stock'];
        if ($stock > 0 && $qty > $stock) {
            $qty = $stock;
            $_SESSION['cart'][$flower_id] = $qty;
            $notes[] = "Only $stock of {$f['name']} left, so the quantity was lowered.";
        }
        $lines[] = [
            'flower'     => $f,
            'quantity'   => (int) $qty,
            'line_total' => (float) $f['price'] * (int) $qty,
            'in_stock'   => $stock > 0,
        ];
        $subtotal += (float) $f['price'] * (int) $qty;
    }
    return ['lines' => $lines, 'subtotal' => $subtotal, 'notes' => array_unique($notes)];
}

// ---------- Shop data used on several pages ----------

function shop_categories(PDO $pdo): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = $pdo->query(
            "SELECT c.*, (SELECT COUNT(*) FROM flowers f WHERE f.category_id = c.id AND f.is_active = 1) AS item_count
             FROM categories c WHERE c.is_active = 1 ORDER BY c.name"
        )->fetchAll();
    }
    return $cats;
}

// Same-day delivery right now: open (with minutes left) or closed
function same_day_status(array $settings): array
{
    $cutoff = $settings['same_day_cutoff_time'] ?? '14:00';
    $left   = (int) floor((strtotime(date('Y-m-d') . ' ' . $cutoff) - time()) / 60);
    return [
        'open'         => $left > 0,
        'minutes_left' => max(0, $left),
        'cutoff_label' => date('g:i A', strtotime($cutoff)),
        'cities'       => same_day_cities($settings),
    ];
}

// One product card (used on the home page, shop page and product page)
function product_card(array $f): string
{
    $img   = photo($f['image']);
    $link  = url('product.php?id=' . (int) $f['id']);
    $old   = (float) ($f['old_price'] ?? 0);
    $price = (float) $f['price'];
    $sale  = $old > $price;
    $out   = (int) $f['stock'] < 1;

    ob_start(); ?>
    <article class="product">
      <a class="product-photo" href="<?= $link ?>" aria-label="<?= e($f['name']) ?>">
        <?php if ($img): ?>
          <img src="<?= e($img) ?>" alt="<?= e($f['name']) ?>" loading="lazy">
        <?php else: ?>
          <span class="no-photo"><i class="bi bi-flower1"></i></span>
        <?php endif; ?>
        <?php if ($out): ?>
          <span class="tag tag-out">Sold out</span>
        <?php elseif ($sale): ?>
          <span class="tag tag-sale">Save <?= rs($old - $price) ?></span>
        <?php endif; ?>
      </a>
      <h3 class="product-name"><a href="<?= $link ?>"><?= e($f['name']) ?></a></h3>
      <p class="product-price">
        <span class="now"><?= rs($price) ?></span>
        <?php if ($sale): ?><s class="was"><?= rs($old) ?></s><?php endif; ?>
      </p>
      <?php if ($out): ?>
        <a class="btn btn-outline-leaf btn-sm w-100" href="<?= $link ?>">View details</a>
      <?php else: ?>
        <form method="post" action="<?= url('cart.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
          <button class="btn btn-rose btn-sm w-100"><i class="bi bi-bag-plus"></i> Add to cart</button>
        </form>
      <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}
