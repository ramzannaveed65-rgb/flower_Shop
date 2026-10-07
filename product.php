<?php
// =====================================================
// product.php?id=3 - One flower: photo, price, details, Add to cart / Order now
// =====================================================

require __DIR__ . '/site/_init.php';

$stmt = $pdo->prepare(
    "SELECT f.*, c.name AS category_name FROM flowers f
     LEFT JOIN categories c ON c.id = f.category_id
     WHERE f.id = ? AND f.is_active = 1"
);
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    $page_title = 'Not found';
    require __DIR__ . '/site/_header.php';
    echo '<div class="container"><div class="empty"><i class="bi bi-flower1"></i>This item is no longer available.'
       . '<p class="mt-3"><a class="btn btn-rose" href="' . url('shop.php') . '">See all flowers</a></p></div></div>';
    require __DIR__ . '/site/_footer.php';
    exit;
}

$price = (float) $f['price'];
$old   = (float) ($f['old_price'] ?? 0);
$sale  = $old > $price;
$stock = (int) $f['stock'];
$img   = photo($f['image']);
$sd    = same_day_status($settings);

// More from the same category
$related = [];
if ($f['category_id']) {
    $stmt = $pdo->prepare(
        "SELECT * FROM flowers WHERE is_active = 1 AND category_id = ? AND id <> ? ORDER BY (stock > 0) DESC, id DESC LIMIT 4"
    );
    $stmt->execute([(int) $f['category_id'], (int) $f['id']]);
    $related = $stmt->fetchAll();
}

$page_title       = $f['name'];
$page_description = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $f['description'])), 0, 150)
                  ?: $f['name'] . ' from ' . $shop_name . '. ' . rs($price) . '.';
$page_image       = $img ? $f['image'] : null;
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head pb-3">
    <p class="crumbs mb-0">
      <a href="<?= url() ?>">Home</a> / <a href="<?= url('shop.php') ?>">Shop</a>
      <?php if ($f['category_id'] && $f['category_name']): ?> / <a href="<?= url('shop.php?cat=' . (int) $f['category_id']) ?>"><?= e($f['category_name']) ?></a><?php endif; ?>
    </p>
  </div>

  <div class="row g-4 g-lg-5">
    <div class="col-md-6">
      <div class="detail-photo">
        <?php if ($img): ?>
          <img src="<?= e($img) ?>" alt="<?= e($f['name']) ?>">
        <?php else: ?>
          <span class="no-photo" style="font-size:5rem"><i class="bi bi-flower1"></i></span>
        <?php endif; ?>
        <?php if ($sale && $stock > 0): ?><span class="tag tag-sale">Save <?= rs($old - $price) ?></span><?php endif; ?>
      </div>
    </div>

    <div class="col-md-6">
      <h1><?= e($f['name']) ?></h1>
      <p class="mt-3 mb-1">
        <span class="detail-price"><?= rs($price) ?></span>
        <?php if ($sale): ?><s class="was fs-5"><?= rs($old) ?></s><?php endif; ?>
      </p>

      <?php if ($stock < 1): ?>
        <p><?= status_badge('cancelled', 'Sold out') ?></p>
        <p class="text-muted">This one is sold out right now. Message us and we will tell you when it is back.</p>
      <?php elseif ($stock <= 3): ?>
        <p><?= status_badge('pending', "Only $stock left") ?></p>
      <?php endif; ?>

      <ul class="facts">
        <?php if ((int) $f['same_day_available'] && $sd['cities']): ?>
          <li><i class="bi bi-lightning-charge"></i>
            <span><?= $sd['open']
                ? 'Same-day delivery available. Order before ' . e($sd['cutoff_label']) . '.'
                : 'Same-day delivery available on orders placed before ' . e($sd['cutoff_label']) . '.' ?></span></li>
        <?php else: ?>
          <li><i class="bi bi-calendar-event"></i><span>Needs one day's notice. Earliest delivery is tomorrow.</span></li>
        <?php endif; ?>
        <li><i class="bi bi-truck"></i><span>Delivery in <?= e(implode(' & ', delivery_cities($settings))) ?>, from <?= rs($settings['standard_delivery_charge'] ?? 0) ?>.</span></li>
        <li><i class="bi bi-envelope-heart"></i><span>Add a free gift message at checkout.</span></li>
      </ul>

      <?php if ($stock > 0): ?>
        <form method="post" action="<?= url('cart.php') ?>" class="d-flex flex-wrap align-items-center gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
          <div class="qty">
            <button type="button" data-step="down" aria-label="Less">&minus;</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= min(99, $stock) ?>" aria-label="Quantity">
            <button type="button" data-step="up" aria-label="More">+</button>
          </div>
          <button name="action" value="add" class="btn btn-outline-leaf btn-lg"><i class="bi bi-bag-plus"></i> Add to cart</button>
          <button name="action" value="buy" class="btn btn-rose btn-lg">Order now</button>
        </form>
      <?php elseif (trim($settings['shop_whatsapp'] ?? '') !== ''): ?>
        <a class="btn btn-whatsapp" target="_blank" rel="noopener"
           href="<?= e(wa_link($settings['shop_whatsapp'], 'Hello, is "' . $f['name'] . '" available?')) ?>"><i class="bi bi-whatsapp"></i> Ask on WhatsApp</a>
      <?php endif; ?>

      <?php if (trim((string) $f['description']) !== ''): ?>
        <h2 class="mt-4 mb-2" style="font-size:1.35rem">About this item</h2>
        <p class="description"><?= e($f['description']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="section pb-0">
      <div class="section-head">
        <div><h2>More <?= e(mb_strtolower($f['category_name'] ?? 'like this')) ?></h2></div>
        <a class="more-link" href="<?= url('shop.php?cat=' . (int) $f['category_id']) ?>">See all</a>
      </div>
      <div class="products">
        <?php foreach ($related as $r) echo product_card($r); ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
