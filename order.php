<?php
// =====================================================
// order.php?id=12 - One order: status, items, delivery, and payment proof
// The customer only sees their own orders.
// =====================================================

require __DIR__ . '/site/_init.php';

$customer = require_login($pdo);
$id       = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$id, (int) $customer['id']]);
$row = $stmt->fetch();
if (!$row) {
    flash('Order not found.', 'warning');
    redirect('orders.php');
}

$error = '';
$form  = ['transaction_id' => '', 'sender_number' => ''];

// ----- Customer sends the proof of an advance payment -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['transaction_id'] = trim((string) ($_POST['transaction_id'] ?? ''));
    $form['sender_number']  = trim((string) ($_POST['sender_number'] ?? ''));
    try {
        submit_payment_proof($pdo, $row, $form['transaction_id'], $form['sender_number'], $_FILES['screenshot'] ?? null);
        flash('Payment sent. We will check it and confirm your order soon.');
        redirect('order.php?id=' . $id);
    } catch (ShopError $e) {
        $error = $e->getMessage();
    }
}

$o       = format_order($pdo, $row);
$is_cod  = $o['payment_method'] === 'cod';
$needs_payment = !$is_cod && $o['order_status'] !== 'cancelled'
              && in_array($o['payment_status'], ['unpaid', 'rejected'], true);
$pay_name   = payment_label($o['payment_method']);
$pay_number = trim($settings[$o['payment_method'] . '_number'] ?? '');
$pay_title  = trim($settings[$o['payment_method'] . '_title'] ?? '');
$review_url = trim($settings['google_review_url'] ?? '');

// The five steps an order goes through
$steps   = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'out_for_delivery' => 'On the way', 'delivered' => 'Delivered'];
$reached = array_search($o['order_status'], array_keys($steps), true);

$page_title = 'Order ' . $o['order_number'];
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head">
    <p class="crumbs"><a href="<?= url('orders.php') ?>">My orders</a> / <?= e($o['order_number']) ?></p>
    <h1>Order <span class="text-nowrap"><?= e($o['order_number']) ?></span></h1>
  </div>

  <?php if (!empty($_GET['placed'])): ?>
    <div class="alert alert-success mt-3">
      <strong>Thank you! Your order is placed.</strong>
      <?= $is_cod ? 'Please keep ' . rs($o['total']) . ' in cash ready for delivery.' : 'Send the payment below so we can confirm it.' ?>
    </div>
  <?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>

  <div class="row g-4 mt-1">
    <div class="col-lg-7">

      <?php if ($o['order_status'] === 'delivered' && $review_url !== ''): ?>
        <div class="panel" style="background:var(--mist);border-color:transparent">
          <h2>Happy with your order?</h2>
          <p>A review on Google helps other customers find us.</p>
          <a class="btn btn-leaf" href="<?= e($review_url) ?>" target="_blank" rel="noopener"><i class="bi bi-star-fill" style="color:var(--marigold)"></i> Rate us on Google</a>
        </div>
      <?php endif; ?>

      <!-- Status -->
      <div class="panel">
        <h2>Status</h2>
        <?php if ($o['order_status'] === 'cancelled'): ?>
          <p class="mb-0"><?= status_badge('cancelled') ?> This order was cancelled.</p>
        <?php else: ?>
          <ol class="track">
            <?php $n = 0; foreach ($steps as $key => $label): ?>
              <li class="<?= $n < $reached ? 'done' : ($n === $reached ? 'now' : '') ?>"><?= e($label) ?></li>
            <?php $n++; endforeach; ?>
          </ol>
        <?php endif; ?>
        <div class="sum-row mt-3">
          <span>Payment: <?= e($pay_name) ?></span>
          <span><?= $is_cod
              ? status_badge($o['order_status'] === 'delivered' ? 'verified' : 'pending', $o['order_status'] === 'delivered' ? 'Paid' : 'Pay on delivery')
              : status_badge($o['payment_status'], ['unpaid' => 'Payment needed', 'submitted' => 'Being checked', 'verified' => 'Paid', 'rejected' => 'Not accepted'][$o['payment_status']] ?? null) ?></span>
        </div>
        <?php if ($o['payment']): ?>
          <p class="small text-muted mb-0">Transaction ID: <?= e($o['payment']['transaction_id']) ?></p>
        <?php endif; ?>
        <?php if ($o['payment_status'] === 'rejected' && !empty($o['payment']['admin_note'])): ?>
          <div class="alert alert-danger mt-3 mb-0">Note from the shop: <?= e($o['payment']['admin_note']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Pay in advance -->
      <?php if ($needs_payment): ?>
        <div class="panel" id="pay">
          <h2><?= $o['payment_status'] === 'rejected' ? 'Send the payment again' : 'Pay for this order' ?></h2>
          <div class="pay-box mb-3">
            <div>Send <strong><?= rs($o['total']) ?></strong> by <?= e($pay_name) ?> to:</div>
            <div class="number"><?= e($pay_number !== '' ? $pay_number : 'Number coming soon') ?></div>
            <?php if ($pay_title !== ''): ?><div class="text-muted">Account name: <?= e($pay_title) ?></div><?php endif; ?>
          </div>
          <p>After sending the money, fill this in so we can match your payment.</p>
          <form method="post" enctype="multipart/form-data" action="<?= url('order.php?id=' . $id) ?>#pay">
            <?= csrf_field() ?>
            <div class="row">
              <div class="col-sm-6 mb-3">
                <label class="form-label" for="transaction_id">Transaction ID</label>
                <input class="form-control" id="transaction_id" name="transaction_id" value="<?= e($form['transaction_id']) ?>" placeholder="From the <?= e($pay_name) ?> message" required>
              </div>
              <div class="col-sm-6 mb-3">
                <label class="form-label" for="sender_number">Number you paid from <span class="text-muted fw-normal">(optional)</span></label>
                <input class="form-control" type="tel" id="sender_number" name="sender_number" value="<?= e($form['sender_number']) ?>" placeholder="03001234567">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="screenshot">Screenshot of the payment</label>
              <input class="form-control" type="file" id="screenshot" name="screenshot" accept="image/jpeg,image/png,image/webp" required>
              <div class="form-text">JPG or PNG, up to 5 MB.</div>
            </div>
            <button class="btn btn-rose">Send payment proof</button>
          </form>
        </div>
      <?php endif; ?>

      <!-- Delivery -->
      <div class="panel">
        <h2>Delivery</h2>
        <div class="sum-row"><span class="text-muted">When</span><span><?= e(nice_date($o['delivery_date'])) ?><?= $o['delivery_type'] === 'same_day' ? ' (same day)' : '' ?></span></div>
        <div class="sum-row"><span class="text-muted">To</span><span class="text-end"><?= e($o['receiver_name']) ?>, <?= e($o['receiver_phone']) ?></span></div>
        <div class="sum-row"><span class="text-muted">Address</span><span class="text-end"><?= e($o['address']) ?>, <?= e($o['city']) ?></span></div>
        <?php if (!empty($o['gift_message'])): ?>
          <div class="sum-row"><span class="text-muted">Gift message</span><span class="text-end"><?= e($o['gift_message']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="panel">
        <h2>Items</h2>
        <?php foreach ($o['items'] as $i): $img = photo($i['image']); ?>
          <div class="cart-line" style="grid-template-columns:56px 1fr auto">
            <span class="thumb" style="width:56px;height:56px">
              <?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><span class="no-photo"><i class="bi bi-flower1"></i></span><?php endif; ?>
            </span>
            <span><?= (int) $i['quantity'] ?> &times; <?= e($i['name']) ?></span>
            <strong class="text-nowrap"><?= rs($i['line_total']) ?></strong>
          </div>
        <?php endforeach; ?>
        <div class="sum-row mt-2"><span>Items</span><span><?= rs($o['subtotal']) ?></span></div>
        <div class="sum-row"><span>Delivery</span><span><?= rs($o['delivery_charge']) ?></span></div>
        <div class="sum-row total"><span>Total</span><span><?= rs($o['total']) ?></span></div>
      </div>
      <?php if (trim($settings['shop_whatsapp'] ?? '') !== ''): ?>
        <p class="mt-3"><a class="btn btn-whatsapp w-100" target="_blank" rel="noopener"
           href="<?= e(wa_link($settings['shop_whatsapp'], 'Hello, I have a question about my order ' . $o['order_number'] . '.')) ?>"><i class="bi bi-whatsapp"></i> Ask about this order</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
