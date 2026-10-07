<?php
// =====================================================
// orders.php - The logged-in customer's orders, newest first
// =====================================================

require __DIR__ . '/site/_init.php';

$customer = require_login($pdo);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([(int) $customer['id']]);
$orders = array_map(fn($row) => format_order($pdo, $row), $stmt->fetchAll());

$page_title = 'My orders';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head"><h1>My orders</h1></div>

  <?php if (!$orders): ?>
    <div class="empty">
      <i class="bi bi-receipt"></i>
      You have not ordered anything yet.
      <p class="mt-3"><a class="btn btn-rose" href="<?= url('shop.php') ?>">Shop flowers</a></p>
    </div>
  <?php else: ?>
    <div class="mt-3" style="max-width:820px">
      <?php foreach ($orders as $o):
          $needs = $o['payment_method'] !== 'cod' && $o['order_status'] !== 'cancelled' && in_array($o['payment_status'], ['unpaid', 'rejected'], true); ?>
        <a class="panel d-block text-decoration-none mb-3" style="color:var(--ink)" href="<?= url('order.php?id=' . $o['id']) ?>">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <strong><?= e($o['order_number']) ?></strong>
              <div class="text-muted small">Delivery <?= e(nice_date($o['delivery_date'])) ?> to <?= e($o['receiver_name']) ?></div>
            </div>
            <?= status_badge($o['order_status'], ['pending' => 'Placed', 'out_for_delivery' => 'On the way'][$o['order_status']] ?? null) ?>
          </div>
          <div class="mt-2"><?= e(implode(', ', array_map(fn($i) => $i['quantity'] . ' × ' . $i['name'], $o['items']))) ?></div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span><strong><?= rs($o['total']) ?></strong> <span class="text-muted">by <?= e(payment_label($o['payment_method'])) ?></span></span>
            <?php if ($needs): ?><?= status_badge('unpaid', 'Payment needed') ?><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
