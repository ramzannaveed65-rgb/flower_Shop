<?php
// =====================================================
// contact.php - How to reach the shop
// The details come from Admin -> Settings -> Website.
// =====================================================

require __DIR__ . '/site/_init.php';

$c_phone   = trim($settings['shop_phone'] ?? '');
$c_wa      = trim($settings['shop_whatsapp'] ?? '');
$c_email   = trim($settings['shop_email'] ?? '');
$c_address = trim($settings['shop_address'] ?? '');
$c_hours   = trim($settings['opening_hours'] ?? '');
$any       = $c_phone !== '' || $c_wa !== '' || $c_email !== '' || $c_address !== '';

$page_title = 'Contact us';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head">
    <h1>Contact us</h1>
    <p>Questions about an order, a delivery area or event decoration? We are happy to help.</p>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-7">
      <div class="panel">
        <?php if ($any): ?>
          <ul class="contact-list">
            <?php if ($c_wa !== ''): ?>
              <li><i class="bi bi-whatsapp"></i><div><small>WhatsApp</small>
                <a href="<?= e(wa_link($c_wa, "Hello $shop_name")) ?>" target="_blank" rel="noopener"><?= e($c_wa) ?></a></div></li>
            <?php endif; ?>
            <?php if ($c_phone !== ''): ?>
              <li><i class="bi bi-telephone"></i><div><small>Phone</small>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c_phone)) ?>"><?= e($c_phone) ?></a></div></li>
            <?php endif; ?>
            <?php if ($c_email !== ''): ?>
              <li><i class="bi bi-envelope"></i><div><small>Email</small><a href="mailto:<?= e($c_email) ?>"><?= e($c_email) ?></a></div></li>
            <?php endif; ?>
            <?php if ($c_address !== ''): ?>
              <li><i class="bi bi-geo-alt"></i><div><small>Shop</small><?= e($c_address) ?></div></li>
            <?php endif; ?>
            <?php if ($c_hours !== ''): ?>
              <li><i class="bi bi-clock"></i><div><small>Open</small><?= e($c_hours) ?></div></li>
            <?php endif; ?>
          </ul>
        <?php else: ?>
          <p class="mb-0 text-muted">Our contact details will be here soon.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="panel" style="background:var(--mist);border-color:transparent">
        <h2>Where we deliver</h2>
        <p class="mb-2"><?= e(implode(' and ', delivery_cities($settings))) ?>.</p>
        <p class="mb-0 text-muted">Not sure about your area? Message us before you order.</p>
      </div>
      <?php if ($c_wa !== ''): ?>
        <p class="mt-3"><a class="btn btn-whatsapp btn-lg w-100" href="<?= e(wa_link($c_wa, "Hello $shop_name")) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
