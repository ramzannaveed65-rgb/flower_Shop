<?php
// =====================================================
// admin/_init.php - Shared code for every admin page
// Every page starts with:  require __DIR__ . '/_init.php';
// =====================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/notify.php';

session_name('flower_admin');
session_start();

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

// "out_for_delivery" -> "Out for delivery"
function nice(string $s): string
{
    return ucfirst(str_replace('_', ' ', $s));
}

function payment_label(string $method): string
{
    return ['cod' => 'Cash on Delivery', 'nayapay' => 'NayaPay', 'easypaisa' => 'Easypaisa'][$method] ?? $method;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// Coloured Bootstrap badge for any status
function badge(string $status): string
{
    $colors = [
        'pending'          => 'warning',
        'unpaid'           => 'warning',
        'confirmed'        => 'primary',
        'submitted'        => 'info',
        'out_for_delivery' => 'info',
        'delivered'        => 'success',
        'verified'         => 'success',
        'cancelled'        => 'danger',
        'rejected'         => 'danger',
        'new'              => 'warning',
        'contacted'        => 'info',
        'completed'        => 'success',
    ];
    $c = $colors[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $c . '">' . e(nice($status)) . '</span>';
}

// ---------- Flash messages (shown once on the next page) ----------

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function show_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show">' . e($msg)
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- CSRF protection (every POST form must include csrf_field()) ----------

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
        exit('This form has expired. Please go back, refresh the page and try again.');
    }
}

// ---------- Login check ----------

function require_admin(PDO $pdo): array
{
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
    $stmt = $pdo->prepare("SELECT id, name, email FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        session_destroy();
        redirect('login.php');
    }
    return $admin;
}

// ---------- Settings ----------

function get_settings(PDO $pdo): array
{
    return $pdo->query("SELECT setting_key, setting_value FROM settings")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
}

// ---------- Image upload (flower photos) ----------
// Returns the saved path like "uploads/flowers/rose_ab12cd.jpg"
// $subfolder: "flowers" for flower photos, "events" for event package photos
function save_image(array $file, string $prefix, string $subfolder = 'flowers'): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed. Try a smaller image (max 5 MB).');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Photo is too big (max 5 MB).');
    }
    $info    = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Photo must be a JPG, PNG or WEBP image.');
    }

    $folder = __DIR__ . '/../uploads/' . $subfolder . '/';
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new RuntimeException("Could not create the uploads/$subfolder folder.");
    }

    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($prefix)), '-') ?: 'flower';
    $name = substr($slug, 0, 40) . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info[2]];

    if (!move_uploaded_file($file['tmp_name'], $folder . $name)) {
        throw new RuntimeException('Could not save the photo.');
    }
    return "uploads/$subfolder/" . $name;
}

// Delete an old photo (only files we uploaded into uploads/flowers/ or uploads/events/)
function delete_image(?string $path): void
{
    if ($path && (str_starts_with($path, 'uploads/flowers/') || str_starts_with($path, 'uploads/events/'))) {
        @unlink(__DIR__ . '/../' . $path);
    }
}

// ---------- Orders ----------

// Put the items of an order back into stock (used when cancelling)
function restock_order(PDO $pdo, int $order_id): void
{
    $items = $pdo->prepare("SELECT flower_id, quantity FROM order_items WHERE order_id = ? AND flower_id IS NOT NULL");
    $items->execute([$order_id]);
    $add = $pdo->prepare("UPDATE flowers SET stock = stock + ? WHERE id = ?");
    foreach ($items->fetchAll() as $it) {
        $add->execute([(int) $it['quantity'], (int) $it['flower_id']]);
    }
}
