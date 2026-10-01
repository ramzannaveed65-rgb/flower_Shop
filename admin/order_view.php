<?php
// =====================================================
// admin/order_view.php?id=7 - One order: details, status, payment check
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$id = (int) ($_GET['id'] ?? 0);

function load_order(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT o.*, c.name AS customer_name, c.phone AS customer_phone
         FROM orders o JOIN customers c ON c.id = o.customer_id
         WHERE o.id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$order = load_order($pdo, $id);
if (!$order) {
    flash('Order not found', 'danger');
    redirect('orders.php');
}

// Allowed next steps for each status
$next_status = [
    'pending'          => ['confirmed', 'cancelled'],
    'confirmed'        => ['out_for_delivery', 'cancelled'],
    'out_for_delivery' => ['delivered', 'cancelled'],
    'delivered'        => [],
    'cancelled'        => [],
];

// ----------------------------------------------------
// Actions (forms on this page post back here)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        // ----- Change order status -----
        if ($action === 'status') {
            $new = $_POST['status'] ?? '';
            if (!in_array($new, $next_status[$order['order_status']] ?? [], true)) {
                throw new RuntimeException('That status change is not allowed.');
            }
            // Online payment must be verified before the order is confirmed
            if ($new === 'confirmed' && $order['payment_method'] !== 'cod' && $order['payment_status'] !== 'verified') {
                throw new RuntimeException('Verify the payment first, then confirm the order.');
            }

            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$new, $id]);

            if ($new === 'cancelled') {
                restock_order($pdo, $id);        // flowers go back into stock
            }
            if ($new === 'delivered' && $order['payment_method'] === 'cod') {
                // Cash collected on delivery
                $pdo->prepare("UPDATE orders SET payment_status = 'verified' WHERE id = ?")->execute([$id]);
            }
            flash('Order is now: ' . nice($new));
        }

        // ----- Verify or reject the latest payment proof -----
        elseif ($action === 'verify' || $action === 'reject') {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? AND status = 'submitted' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            if (!$payment) {
                throw new RuntimeException('There is no payment waiting for verification.');
            }

            if ($action === 'verify') {
                $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, admin_note = NULL WHERE id = ?")
                    ->execute([$admin['id'], $payment['id']]);
                $pdo->prepare("UPDATE orders SET payment_status = 'verified' WHERE id = ?")->execute([$id]);
                // A paid order is confirmed automatically
                if ($order['order_status'] === 'pending') {
                    $pdo->prepare("UPDATE orders SET order_status = 'confirmed' WHERE id = ?")->execute([$id]);
                }
                flash('Payment verified and order confirmed.');
            } else {
                $note = trim($_POST['note'] ?? '');
                if ($note === '') {
                    throw new RuntimeException('Write a reason so the customer knows what was wrong.');
                }
                $pdo->prepare("UPDATE payments SET status = 'rejected', verified_by = ?, admin_note = ? WHERE id = ?")
                    ->execute([$admin['id'], mb_substr($note, 0, 255), $payment['id']]);
                $pdo->prepare("UPDATE orders SET payment_status = 'rejected' WHERE id = ?")->execute([$id]);
                flash('Payment rejected. The customer can submit a new one in the app.', 'warning');
            }
        } else {
            throw new RuntimeException('Unknown action.');
        }

        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'danger');
    }
    redirect('order_view.php?id=' . $id);
}

// ----------------------------------------------------
// Data for the page
// ----------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT oi.*, f.image FROM order_items oi LEFT JOIN flowers f ON f.id = oi.flower_id WHERE oi.order_id = ? ORDER BY oi.id"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT p.*, a.name AS admin_name FROM payments p LEFT JOIN admins a ON a.id = p.verified_by
     WHERE p.order_id = ? ORDER BY p.id DESC"
);
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$page_title = $order['order_number'];
$active     = 'orders';
require __DIR__ . '/_header.php';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <a href="orders.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h3 class="fw-bold mb-0"><?= e($order['order_number']) ?></h3>
  <?= badge($order['order_status']) ?>
  <span class="text-muted ms-2">Placed <?= e(substr($order['created_at'], 0, 16)) ?></span>
</div>

<div class="row g-3">
  <!-- Left: items + delivery -->
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Items</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $it): ?>
          <li class="list-group-item d-flex align-items-center gap-3">
            <?php if (!empty($it['image']) && is_file(__DIR__ . '/../' . $it['image'])): ?>
              <img src="../<?= e($it['image']) ?>" class="thumb" alt="">
            <?php else: ?>
              <div class="thumb-ph"><i class="bi bi-flower1"></i></div>
            <?php endif; ?>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= e($it['flower_name']) ?></div>
              <small class="text-muted"><?= (int) $it['quantity'] ?> × <?= rs($it['price']) ?></small>
            </div>
            <div class="fw-semibold"><?= rs($it['price'] * $it['quantity']) ?></div>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between"><span>Flowers</span><span><?= rs($order['subtotal']) ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><span>Delivery (<?= $order['delivery_type'] === 'same_day' ? 'same day' : 'standard' ?>)</span><span><?= rs($order['delivery_charge']) ?></span></li>
        <li class="list-group-item d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= rs($order['total']) ?></span></li>
      </ul>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold">Delivery</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Delivery</dt>
          <dd class="col-sm-8">
            <?= $order['delivery_type'] === 'same_day' ? '<span class="badge text-bg-danger">Same day</span>' : 'Standard' ?>
            on <b><?= e(date('D, j M Y', strtotime($order['delivery_date']))) ?></b>
          </dd>
          <dt class="col-sm-4">Receiver</dt>
          <dd class="col-sm-8"><?= e($order['receiver_name']) ?></dd>
          <dt class="col-sm-4">Receiver mobile</dt>
          <dd class="col-sm-8"><a href="tel:<?= e($order['receiver_phone']) ?>"><?= e($order['receiver_phone']) ?></a></dd>
          <dt class="col-sm-4">Address</dt>
          <dd class="col-sm-8"><?= nl2br(e($order['address'])) ?>, <?= e($order['city']) ?></dd>
          <?php if (!empty($order['gift_message'])): ?>
            <dt class="col-sm-4">Gift card message</dt>
            <dd class="col-sm-8"><div class="p-2 bg-light rounded fst-italic">"<?= e($order['gift_message']) ?>"</div></dd>
          <?php endif; ?>
          <dt class="col-sm-4">Ordered by</dt>
          <dd class="col-sm-8 mb-0"><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <!-- Right: status + payment -->
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Order status</div>
      <div class="card-body">
        <p class="mb-2">Current: <?= badge($order['order_status']) ?></p>
        <?php $options = $next_status[$order['order_status']]; ?>
        <?php if ($options): ?>
          <form method="post" class="d-flex flex-wrap gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <?php foreach ($options as $s): ?>
              <button name="status" value="<?= $s ?>"
                class="btn <?= $s === 'cancelled' ? 'btn-outline-danger' : 'btn-pink' ?>"
                <?= $s === 'cancelled' ? "onclick=\"return confirm('Cancel this order? Flowers will go back into stock.')\"" : '' ?>>
                <?= $s === 'cancelled' ? '<i class="bi bi-x-circle"></i> Cancel order' : 'Mark as ' . nice($s) ?>
              </button>
            <?php endforeach; ?>
          </form>
          <?php if ($order['order_status'] === 'pending' && $order['payment_method'] !== 'cod' && $order['payment_status'] !== 'verified'): ?>
            <p class="small text-muted mt-2 mb-0">This order is paid online: verify the payment below and it will be confirmed automatically.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-0">This order is finished. No more changes.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span>Payment · <?= e(payment_label($order['payment_method'])) ?></span>
        <?= badge($order['payment_status']) ?>
      </div>
      <div class="card-body">
        <?php if ($order['payment_method'] === 'cod'): ?>
          <p class="mb-0">Collect <b><?= rs($order['total']) ?></b> in cash on delivery.
            <?= $order['payment_status'] === 'verified' ? '<br><span class="text-success">Cash received.</span>' : '' ?></p>
        <?php elseif (!$payments): ?>
          <p class="text-muted mb-0">The customer has not submitted the payment proof yet.</p>
        <?php endif; ?>

        <?php foreach ($payments as $i => $p): ?>
          <div class="<?= $i > 0 ? 'border-top pt-3 mt-3 opacity-75' : '' ?>">
            <?php if ($i === 1): ?><p class="small text-muted mb-2">Earlier attempts:</p><?php endif; ?>
            <div class="d-flex gap-3">
              <?php if ($p['screenshot']): ?>
                <a href="../<?= e($p['screenshot']) ?>" target="_blank" title="Open full size">
                  <img src="../<?= e($p['screenshot']) ?>" style="width:110px;height:160px;object-fit:cover" class="rounded border" alt="Payment screenshot">
                </a>
              <?php endif; ?>
              <div class="small">
                <div>Status: <?= badge($p['status']) ?></div>
                <div class="mt-1">Transaction ID:<br><b class="fs-6 font-monospace"><?= e($p['transaction_id']) ?></b></div>
                <div class="mt-1">Amount: <b><?= rs($p['amount']) ?></b></div>
                <?php if ($p['sender_number']): ?><div>From: <?= e($p['sender_number']) ?></div><?php endif; ?>
                <div class="text-muted">Sent <?= e(substr($p['created_at'], 0, 16)) ?></div>
                <?php if ($p['admin_name']): ?><div class="text-muted">Checked by <?= e($p['admin_name']) ?></div><?php endif; ?>
                <?php if ($p['admin_note']): ?><div class="text-danger">Note: <?= e($p['admin_note']) ?></div><?php endif; ?>
              </div>
            </div>

            <?php if ($p['status'] === 'submitted' && $order['order_status'] !== 'cancelled'): ?>
              <div class="alert alert-light border small mt-3 mb-2">
                Open your <?= e(payment_label($order['payment_method'])) ?> account and check that
                <b><?= rs($p['amount']) ?></b> arrived with Transaction ID <b><?= e($p['transaction_id']) ?></b>.
              </div>
              <form method="post" class="mb-2">
                <?= csrf_field() ?>
                <button name="action" value="verify" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> Verify payment</button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <div class="input-group">
                  <input name="note" class="form-control" placeholder="Reason, e.g. TID not found" required>
                  <button name="action" value="reject" class="btn btn-outline-danger">Reject</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
