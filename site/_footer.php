<?php
// =====================================================
// site/_footer.php - Bottom of every website page
// =====================================================

$f_phone   = trim($settings['shop_phone'] ?? '');
$f_wa      = trim($settings['shop_whatsapp'] ?? '');
$f_email   = trim($settings['shop_email'] ?? '');
$f_address = trim($settings['shop_address'] ?? '');
$f_hours   = trim($settings['opening_hours'] ?? '');
$f_fb      = trim($settings['facebook_url'] ?? '');
$f_ig      = trim($settings['instagram_url'] ?? '');
?>
</main>

<footer class="site-footer">
  <div class="garland" aria-hidden="true"></div>
  <div class="container">
    <div class="row gy-4">
      <div class="col-lg-4">
        <p class="footer-brand"><?= e($shop_name) ?></p>
        <p class="footer-text">Fresh flowers, bouquets, gifts and event decoration, delivered in
          <?= e(implode(' and ', delivery_cities($settings))) ?>.</p>
        <?php if ($f_fb !== '' || $f_ig !== ''): ?>
          <p class="social">
            <?php if ($f_fb !== ''): ?><a href="<?= e($f_fb) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a><?php endif; ?>
            <?php if ($f_ig !== ''): ?><a href="<?= e($f_ig) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="footer-head">Shop</h2>
        <ul class="footer-links">
          <li><a href="<?= url('shop.php') ?>">Everything</a></li>
          <?php foreach (array_slice(shop_categories($pdo), 0, 6) as $c): ?>
            <li><a href="<?= url('shop.php?cat=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="col-6 col-lg-3">
        <h2 class="footer-head">Help</h2>
        <ul class="footer-links">
          <li><a href="<?= url('events.php') ?>">Event decoration</a></li>
          <li><a href="<?= url('orders.php') ?>">My orders</a></li>
          <li><a href="<?= url('contact.php') ?>">Contact us</a></li>
          <li><a href="<?= url('privacy.php') ?>">Privacy Policy</a></li>
          <li><a href="<?= url('delete-account.php') ?>">Delete my account</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <h2 class="footer-head">Contact</h2>
        <ul class="footer-links footer-contact">
          <?php if ($f_phone !== ''): ?><li><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $f_phone)) ?>"><?= e($f_phone) ?></a></li><?php endif; ?>
          <?php if ($f_wa !== ''): ?><li><i class="bi bi-whatsapp"></i> <a href="<?= e(wa_link($f_wa)) ?>" target="_blank" rel="noopener">WhatsApp us</a></li><?php endif; ?>
          <?php if ($f_email !== ''): ?><li><i class="bi bi-envelope"></i> <a href="mailto:<?= e($f_email) ?>"><?= e($f_email) ?></a></li><?php endif; ?>
          <?php if ($f_address !== ''): ?><li><i class="bi bi-geo-alt"></i> <?= e($f_address) ?></li><?php endif; ?>
          <?php if ($f_hours !== ''): ?><li><i class="bi bi-clock"></i> <?= e($f_hours) ?></li><?php endif; ?>
          <?php if ($f_phone === '' && $f_wa === '' && $f_email === '' && $f_address === ''): ?>
            <li><a href="<?= url('contact.php') ?>">Contact page</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <p class="copyright">&copy; <?= date('Y') ?> <?= e($shop_name) ?>. All rights reserved.</p>
  </div>
</footer>

<?php if ($f_wa !== '' && empty($hide_wa_float)): ?>
  <a class="wa-float" href="<?= e(wa_link($f_wa, "Hello $shop_name, I want to order flowers.")) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <i class="bi bi-whatsapp"></i>
  </a>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/site.js') ?>?v=4"></script>
</body>
</html>
