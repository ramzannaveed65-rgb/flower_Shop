<?php
// =====================================================
// admin/_header.php - Top of every admin page (menu)
// Before including, set:  $page_title  and  $active  ('dashboard', 'orders', ...)
// =====================================================

$count_pending  = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
$count_payments = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'submitted' AND order_status <> 'cancelled'")->fetchColumn();
$count_bookings = (int) $pdo->query("SELECT COUNT(*) FROM event_bookings WHERE status = 'new'")->fetchColumn();
$shop_name      = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'shop_name'")->fetchColumn() ?: 'Flower Shop';

function nav_item(string $key, string $href, string $icon, string $label, string $active, int $count = 0): string
{
    $cls   = $key === $active ? 'nav-link active' : 'nav-link';
    $badge = $count > 0 ? ' <span class="badge rounded-pill text-bg-light">' . $count . '</span>' : '';
    return '<li class="nav-item"><a class="' . $cls . '" href="' . $href . '"><i class="bi bi-' . $icon . '"></i> '
         . $label . $badge . '</a></li>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Admin') ?> · <?= e($shop_name) ?> Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: #f5f5f7; }
    .navbar { background: #c2185b; }
    .navbar .nav-link { color: rgba(255,255,255,.85); }
    .navbar .nav-link.active, .navbar .nav-link:hover { color: #fff; }
    .navbar .nav-link.active { font-weight: 600; border-bottom: 2px solid #fff; }
    .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .stat .num { font-size: 1.8rem; font-weight: 700; }
    .thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; background: #fce4ec; }
    .thumb-ph { width: 56px; height: 56px; border-radius: 8px; background: #fce4ec; color: #c2185b;
                display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .btn-pink { background: #c2185b; color: #fff; }
    .btn-pink:hover { background: #a0144b; color: #fff; }
    .table td, .table th { vertical-align: middle; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-flower1"></i> <?= e($shop_name) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <?= nav_item('dashboard', 'index.php', 'speedometer2', 'Dashboard', $active ?? '') ?>
        <?= nav_item('orders', 'orders.php', 'receipt', 'Orders', $active ?? '', $count_pending) ?>
        <?= nav_item('payments', 'orders.php?payment=submitted', 'cash-coin', 'Payments', $active ?? '', $count_payments) ?>
        <?= nav_item('bookings', 'bookings.php', 'calendar-heart', 'Bookings', $active ?? '', $count_bookings) ?>
        <?= nav_item('flowers', 'flowers.php', 'flower2', 'Flowers', $active ?? '') ?>
        <?= nav_item('events', 'events.php', 'balloon-heart', 'Event packages', $active ?? '') ?>
        <?= nav_item('categories', 'categories.php', 'tags', 'Categories', $active ?? '') ?>
        <?= nav_item('settings', 'settings.php', 'gear', 'Settings', $active ?? '') ?>
      </ul>
      <a href="../" target="_blank" class="btn btn-sm btn-outline-light me-3"><i class="bi bi-box-arrow-up-right"></i> View website</a>
      <span class="navbar-text text-white me-3"><i class="bi bi-person-circle"></i> <?= e($admin['name']) ?></span>
      <a href="logout.php" class="btn btn-sm btn-light">Logout</a>
    </div>
  </div>
</nav>
<main class="container-fluid px-4 pb-5">
<?= show_flash() ?>
