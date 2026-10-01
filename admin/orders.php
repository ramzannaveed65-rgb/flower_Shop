<?php
// =====================================================
// admin/orders.php - All orders with filters
//   ?status=pending      ?payment=submitted   ?q=search   ?date=2026-10-01
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$status  = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$q       = trim($_GET['q'] ?? '');
$date    = $_GET['date'] ?? '';

$statuses = ['pending', 'confirmed', 'out_for_delivery', 'delivered', 'cancelled'];

$sql    = "SELECT o.*, (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count
           FROM orders o WHERE 1 = 1";
$params = [];

if (in_array($status, $statuses, true)) {
    $sql .= " AND o.order_status = ?";
    $params[] = $status;
}
if ($payment === 'submitted') {
    $sql .= " AND o.payment_status = 'submitted' AND o.order_status <> 'cancelled'";
}
if ($q !== '') {
    $sql .= " AND (o.order_number LIKE ? OR o.receiver_name LIKE ? OR o.receiver_phone LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $sql .= " AND o.delivery_date = ?";
    $params[] = $date;
}
$sql .= " ORDER BY o.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = $payment === 'submitted' ? 'Payments to verify' : 'Orders';
$active     = $payment === 'submitted' ? 'payments' : 'orders';
require __DIR__ . '/_header.php';
?>

<h3 class="fw-bold mb-3"><?= e($page_title) ?></h3>

<?php if ($payment === 'submitted'): ?>
  <div class="alert alert-info">
    These customers paid by NayaPay or Easypaisa. Open each order, check the screenshot and
    Transaction ID in your NayaPay/Easypaisa account, then click <b>Verify</b> or <b>Reject</b>.
  </div>
<?php endif; ?>

<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small">Search</label>
      <input name="q" class="form-control" placeholder="Order no, name or phone" value="<?= e($q) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label small">Status</label>
      <select name="status" class="form-select">
        <option value="">All</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $s === $status ? 'selected' : '' ?>><?= nice($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Payment</label>
      <select name="payment" class="form-select">
        <option value="">All</option>
        <option value="submitted" <?= $payment === 'submitted' ? 'selected' : '' ?>>Waiting for verification</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Delivery date</label>
      <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
    </div>
    <div class="col-md-3">
      <button class="btn btn-pink"><i class="bi bi-funnel"></i> Filter</button>
      <a href="orders.php" class="btn btn-outline-secondary">Clear</a>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Order</th><th>Placed</th><th>Receiver</th><th>Delivery</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="9" class="text-center text-muted py-5">No orders found</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="fw-semibold"><?= e($o['order_number']) ?></td>
          <td><small><?= e(substr($o['created_at'], 0, 16)) ?></small></td>
          <td><?= e($o['receiver_name']) ?><br><small class="text-muted"><?= e($o['receiver_phone']) ?></small></td>
          <td>
            <?= $o['delivery_type'] === 'same_day' ? '<span class="badge text-bg-danger">Same day</span>' : 'Standard' ?>
            <br><small><?= e($o['delivery_date']) ?> · <?= e($o['city']) ?></small>
          </td>
          <td><?= (int) $o['item_count'] ?></td>
          <td class="fw-semibold"><?= rs($o['total']) ?></td>
          <td><small><?= e(payment_label($o['payment_method'])) ?></small><br><?= badge($o['payment_status']) ?></td>
          <td><?= badge($o['order_status']) ?></td>
          <td><a class="btn btn-sm btn-outline-secondary" href="order_view.php?id=<?= (int) $o['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
