<?php
// =====================================================
// index.php - Home page of the website
// =====================================================

require __DIR__ . '/site/_init.php';

$cats = array_slice(shop_categories($pdo), 0, 8);

// Top selling = flowers marked "Show in Top selling" in the admin panel.
// Until some are marked, the newest flowers are shown instead.
$featured = $pdo->query(
    "SELECT * FROM flowers WHERE is_active = 1 AND is_featured = 1 ORDER BY (stock > 0) DESC, id DESC LIMIT 8"
)->fetchAll();
$featured_title = 'Top selling';
if (!$featured) {
    $featured = $pdo->query("SELECT * FROM flowers WHERE is_active = 1 ORDER BY (stock > 0) DESC, id DESC LIMIT 8")->fetchAll();
    $featured_title = 'New in the shop';
}

$on_sale = $pdo->query(
    "SELECT * FROM flowers WHERE is_active = 1 AND old_price IS NOT NULL AND old_price > price
     ORDER BY (stock > 0) DESC, id DESC LIMIT 8"
)->fetchAll();

$event_types = $pdo->query("SELECT name FROM event_types WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

$sd          = same_day_status($settings);
$cities      = delivery_cities($settings);
$cities_text = implode(' & ', $cities);
$hero_title  = trim($settings['hero_title'] ?? '') ?: 'Fresh flowers, delivered to their door.';
$hero_text   = trim($settings['hero_text'] ?? '')
             ?: "Bouquets, baskets, gajray and gifts for birthdays, weddings and every day in between. Delivered in $cities_text.";
$hero_image  = photo($settings['hero_image'] ?? '');
$review_url  = trim($settings['google_review_url'] ?? '');

$page_title = "Flower delivery in $cities_text";
require __DIR__ . '/site/_header.php';
?>

<!-- ===== Hero ===== -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <h1><?= e($hero_title) ?></h1>
        <p class="hero-text"><?= e($hero_text) ?></p>

        <?php if ($sd['open'] && $sd['cities']): ?>
          <p class="today" data-minutes-left="<?= (int) $sd['minutes_left'] ?>">
            <span class="dot"></span>
            <span>Order within <b data-countdown><?= floor($sd['minutes_left'] / 60) ?> h <?= $sd['minutes_left'] % 60 ?> min</b> for delivery today</span>
          </p>
        <?php else: ?>
          <p class="today closed">
            <span class="dot"></span>
            <span>Same-day orders are closed for today. Order now for delivery from tomorrow.</span>
          </p>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-rose btn-lg" href="<?= url('shop.php') ?>">Shop flowers</a>
          <a class="btn btn-outline-leaf btn-lg" href="<?= url('events.php') ?>">Event decoration</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="arch">
          <?php if ($hero_image): ?>
            <img src="<?= e($hero_image) ?>" alt="Flowers by <?= e($shop_name) ?>">
          <?php else: ?>
            <div class="larian" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== Categories ===== -->
<?php if ($cats): ?>
<section class="section section-tint">
  <div class="container">
    <div class="section-head">
      <div><h2>Shop by category</h2></div>
      <a class="more-link" href="<?= url('shop.php') ?>">See everything</a>
    </div>
    <div class="cats">
      <?php foreach ($cats as $c): $img = photo($c['image'] ?? ''); ?>
        <a class="cat" href="<?= url('shop.php?cat=' . (int) $c['id']) ?>">
          <span class="arch">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-flower2"></i><?php endif; ?>
          </span>
          <span class="cat-name"><?= e($c['name']) ?></span>
          <span class="cat-count"><?= (int) $c['item_count'] ?> <?= (int) $c['item_count'] === 1 ? 'item' : 'items' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== Top selling ===== -->
<?php if ($featured): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div><h2><?= e($featured_title) ?></h2></div>
      <a class="more-link" href="<?= url('shop.php') ?>">Shop all</a>
    </div>
    <div class="products">
      <?php foreach ($featured as $f) echo product_card($f); ?>
    </div>
  </div>
</section>
<?php else: ?>
<section class="section">
  <div class="container">
    <div class="empty"><i class="bi bi-flower1"></i>The shop is being filled with fresh flowers. Please check back soon.</div>
  </div>
</section>
<?php endif; ?>

<!-- ===== On sale ===== -->
<?php if ($on_sale): ?>
<section class="section section-tint">
  <div class="container">
    <div class="section-head">
      <div><h2>On sale now</h2><p>Lower prices on these for a limited time.</p></div>
      <a class="more-link" href="<?= url('shop.php?sale=1') ?>">All offers</a>
    </div>
    <div class="products">
      <?php foreach ($on_sale as $f) echo product_card($f); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== Event decoration ===== -->
<section class="section section-mist">
  <div class="container">
    <div class="row align-items-center gy-4">
      <div class="col-lg-7">
        <h2>Decoration for your big day</h2>
        <p class="hero-text mt-2 mb-0">Stages, entrances, tables and cars dressed in fresh flowers. Tell us the date and place, and we will call you with a price.</p>
        <?php if ($event_types): ?>
          <ul class="event-types">
            <?php foreach ($event_types as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="mb-4"></div>
        <?php endif; ?>
        <a class="btn btn-leaf" href="<?= url('events.php') ?>">See decoration packages</a>
      </div>
      <div class="col-lg-5 d-none d-lg-block">
        <div class="arch" style="max-width:300px;margin-left:auto;aspect-ratio:1/1.1">
          <div class="larian" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== How ordering works ===== -->
<section class="section">
  <div class="container">
    <div class="section-head"><div><h2>How ordering works</h2></div></div>
    <ol class="steps">
      <li><strong>Choose your flowers</strong><span>Pick a bouquet or gift and add it to your cart.</span></li>
      <li><strong>Tell us where and when</strong><span>Enter the receiver's address in <?= e($cities_text) ?>, the day, and a gift message if you like.</span></li>
      <li><strong>We deliver</strong><span>Pay <?= ($settings['cod_enabled'] ?? '1') === '1' ? 'cash on delivery, or in advance' : 'in advance' ?> by NayaPay or Easypaisa. We bring the flowers to the door.</span></li>
    </ol>
    <?php if ($review_url !== ''): ?>
      <p class="mt-5 mb-0">
        <a class="btn btn-outline-leaf" href="<?= e($review_url) ?>" target="_blank" rel="noopener"><i class="bi bi-star-fill" style="color:var(--marigold)"></i> Read and write reviews on Google</a>
      </p>
    <?php endif; ?>
  </div>
</section>

<!-- ===== Questions ===== -->
<section class="section section-tint">
  <div class="container">
    <div class="section-head"><div><h2>Questions people ask</h2></div></div>
    <div class="accordion" id="faq">
      <?php
      $faqs = [
          ['Where do you deliver?',
           "We deliver in $cities_text. If you are not sure about your area, contact us before ordering."],
          ['Can I get flowers delivered today?',
           $sd['cities']
             ? 'Yes. Order before ' . $sd['cutoff_label'] . ' and choose same-day delivery at checkout. It is available in '
               . implode(' & ', $sd['cities']) . '. A few items need a day\'s notice; the product page tells you.'
             : 'Same-day delivery is not available right now. You can order for tomorrow or any day in the next 30 days.'],
          ['What does delivery cost?',
           'Standard delivery is ' . rs($settings['standard_delivery_charge'] ?? 0) . ' and same-day delivery is '
           . rs($settings['same_day_delivery_charge'] ?? 0) . '. You see the total before you place the order.'],
          ['How can I pay?',
           (($settings['cod_enabled'] ?? '1') === '1' ? 'Pay cash when the flowers arrive, or pay' : 'Pay')
           . ' in advance by NayaPay or Easypaisa. For advance payment you send the amount, then enter the Transaction ID and upload a screenshot on your order page.'],
          ['Can I choose the delivery day?',
           'Yes. At checkout you can pick any day from tomorrow up to 30 days ahead.'],
          ['Can I add a message for the receiver?',
           'Yes. Write your gift message at checkout and we will put it on a card with the flowers.'],
      ];
      foreach ($faqs as $i => [$q, $a]): ?>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($q) ?></button>
          </h3>
          <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faq">
            <div class="accordion-body"><?= e($a) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/site/_footer.php'; ?>
