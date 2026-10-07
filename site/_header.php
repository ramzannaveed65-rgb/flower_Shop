<?php
// =====================================================
// site/_header.php - Top of every website page
// Set $page_title (and optionally $page_description) before including it.
// Variables here start with $h_ so they never clash with a page's own variables.
// =====================================================

$h_customer   = current_customer($pdo);
$h_nav_cats   = shop_categories($pdo);
$h_sd         = same_day_status($settings);
$h_logo       = photo($settings['site_logo'] ?? '');
$h_whatsapp   = trim($settings['shop_whatsapp'] ?? '');
$h_phone      = trim($settings['shop_phone'] ?? '');
$h_cities     = implode(' & ', delivery_cities($settings));
$h_title      = ($page_title ?? '') !== '' ? $page_title . ' | ' . $shop_name : $shop_name;
$h_desc       = $page_description ?? "Fresh flowers, bouquets and gifts delivered in $h_cities. Order online from $shop_name.";
$h_here       = basename($_SERVER['SCRIPT_NAME']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($h_title) ?></title>
  <meta name="description" content="<?= e($h_desc) ?>">
  <meta property="og:title" content="<?= e($h_title) ?>">
  <meta property="og:description" content="<?= e($h_desc) ?>">
  <meta property="og:type" content="website">
  <?php if (!empty($page_image)): ?><meta property="og:image" content="<?= e(BASE_URL . ltrim($page_image, '/')) ?>"><?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Rozha+One&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= url('assets/site.css') ?>?v=4" rel="stylesheet">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

<!-- Delivery line: tells the visitor at once if flowers can still arrive today -->
<div class="topline">
  <div class="container d-flex flex-wrap justify-content-center justify-content-md-between gap-2">
    <span>
      <i class="bi bi-truck"></i>
      <?php if ($h_sd['open'] && $h_sd['cities']): ?>
        Same-day delivery in <?= e(implode(' & ', $h_sd['cities'])) ?>. Order before <?= e($h_sd['cutoff_label']) ?>.
      <?php else: ?>
        Delivery in <?= e($h_cities) ?>. Order today for delivery from tomorrow.
      <?php endif; ?>
    </span>
    <?php if ($h_phone !== ''): ?>
      <a class="d-none d-md-inline" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $h_phone)) ?>"><i class="bi bi-telephone"></i> <?= e($h_phone) ?></a>
    <?php endif; ?>
  </div>
</div>

<header class="site-header sticky-top">
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="<?= url() ?>">
        <?php if ($h_logo): ?>
          <img src="<?= e($h_logo) ?>" alt="<?= e($shop_name) ?>" class="brand-logo">
        <?php else: ?>
          <span class="brand-name"><?= e($shop_name) ?></span>
        <?php endif; ?>
      </a>

      <div class="d-flex align-items-center gap-1 order-lg-3">
        <?php if ($h_customer): ?>
          <div class="dropdown">
            <button class="icon-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="My account">
              <i class="bi bi-person"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><span class="dropdown-item-text small text-muted">Hi, <?= e($h_customer['name']) ?></span></li>
              <li><a class="dropdown-item" href="<?= url('orders.php') ?>">My orders</a></li>
              <li><a class="dropdown-item" href="<?= url('bookings.php') ?>">My event bookings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="<?= url('logout.php') ?>">
                  <?= csrf_field() ?>
                  <button class="dropdown-item">Log out</button>
                </form>
              </li>
            </ul>
          </div>
        <?php else: ?>
          <a class="icon-btn" href="<?= url('login.php') ?>" aria-label="Log in"><i class="bi bi-person"></i></a>
        <?php endif; ?>
        <a class="icon-btn cart-btn" href="<?= url('cart.php') ?>" aria-label="Cart, <?= cart_count() ?> items">
          <i class="bi bi-bag"></i>
          <?php if (cart_count() > 0): ?><span class="cart-count"><?= cart_count() ?></span><?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainnav"
                aria-controls="mainnav" aria-expanded="false" aria-label="Menu">
          <i class="bi bi-list"></i>
        </button>
      </div>

      <div class="collapse navbar-collapse order-lg-2" id="mainnav">
        <ul class="navbar-nav mx-lg-auto">
          <li class="nav-item"><a class="nav-link <?= $h_here === 'index.php' ? 'active' : '' ?>" href="<?= url() ?>">Home</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= in_array($h_here, ['shop.php', 'product.php'], true) ? 'active' : '' ?>" href="<?= url('shop.php') ?>" data-bs-toggle="dropdown" aria-expanded="false">Shop</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('shop.php') ?>">Everything</a></li>
              <?php foreach ($h_nav_cats as $c): ?>
                <li><a class="dropdown-item" href="<?= url('shop.php?cat=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a></li>
              <?php endforeach; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('shop.php?sale=1') ?>">On sale</a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link <?= $h_here === 'events.php' ? 'active' : '' ?>" href="<?= url('events.php') ?>">Event decoration</a></li>
          <li class="nav-item"><a class="nav-link <?= $h_here === 'contact.php' ? 'active' : '' ?>" href="<?= url('contact.php') ?>">Contact</a></li>
        </ul>
        <form class="search" action="<?= url('shop.php') ?>" method="get" role="search">
          <i class="bi bi-search"></i>
          <input type="search" name="q" placeholder="Search flowers and gifts" aria-label="Search flowers and gifts" value="<?= e($_GET['q'] ?? '') ?>">
        </form>
      </div>
    </div>
  </nav>
  <div class="garland" aria-hidden="true"></div>
</header>

<main id="main">
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="container mt-3"><?= show_flash() ?></div>
<?php endif; ?>
