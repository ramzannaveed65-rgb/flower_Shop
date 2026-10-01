<?php
// =====================================================
// admin/index.php - Dashboard
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$today       = date('Y-m-d');
$today_start = $today . ' 00:00:00';
$month_start = date('Y-m-01') . ' 00:00:00';

function one(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

$orders_today   = (int) one($pdo, "SELECT COUNT(*) FROM orders WHERE created_at >= ?", [$today_start]);
$sales_today    = (float) one($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND order_status <> 'cancelled'", [$today_start]);
$sales_month    = (float) one($pdo, "SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND order_status <> 'cancelled'", [$month_start]);
$pending        = (int) one($pdo, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
$to_verify      = (int) one($pdo, "SELECT COUNT(*) FROM orders WHERE payment_status = 'submitted' AND order_status <> 'cancelled'");
$customers      = (int) one($pdo, "SELECT COUNT(*) FROM customers");
$new_bookings   = (int) one($pdo, "SELECT COUNT(*) FROM event_bookings WHERE status = 'new'");

// Event decorations in the next 14 days
$stmt = $pdo->prepare(
    "SELECT * FROM event_bookings
     WHERE event_date BETWEEN ? AND ? AND status NOT IN ('cancelled', 'completed')
     ORDER BY event_date, event_time"
);
$stmt->execute([$today, date('Y-m-d', strtotime('+14 days'))]);
$upcoming_events = $stmt->fetchAll();

// Deliveries due today (not delivered or cancelled yet)
$stmt = $pdo->prepare(
    "SELECT * FROM orders
     WHERE delivery_date <= ? AND order_status NOT IN ('delivered', 'cancelled')
     ORDER BY delivery_date, delivery_type DESC, id"
);
$stmt->execute([$today]);
$due = $stmt->fetchAll();

$recent    = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 8")->fetchAll();
$low_stock = $pdo->query("SELECT id, name, stock FROM flowers WHERE is_active = 1 AND stock <= 3 ORDER BY stock, name")->fetchAll();

$page_title = 'Dashboard';
$active     = 'dashboard';
require __DIR__ . '/_header.php';
?>

<h3 class="fw-bold mb-3">Dashboard</h3>

<div class="row g-3 mb-4">
  <?php
  $stats = [
      ['Orders today', $orders_today, 'bag', 'orders.php'],
      ['Sales today', rs($sales_today), 'cash-stack', 'orders.php'],
      ['Sales this month', rs($sales_month), 'graph-up', 'orders.php'],
      ['Pending orders', $pending, 'hourglass-split', 'orders.php?status=pending'],
      ['Payments to verify', $to_verify, 'shield-check', 'orders.php?payment=submitted'],
      ['New event bookings', $new_bookings, 'calendar-heart', 'bookings.php?status=new'],
  ];
  foreach ($stats as [$label, $value, $icon, $link]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <a href="<?= $link ?>" class="text-decoration-none text-reset">
        <div class="card stat h-100">
          <div class="card-body">
            <div class="text-muted small"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></div>
            <div class="num"><?= e($value) ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">
        <i class="bi bi-truck"></i> Deliveries due today
        <span class="badge text-bg-secondary"><?= count($due) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Order</th><th>Deliver to</th><th>Type</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if (!$due): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">Nothing to deliver today</td></tr>
          <?php endif; ?>
          <?php foreach ($due as $o): ?>
            <tr>
              <td><?= e($o['order_number']) ?>
                <?php if ($o['delivery_date'] < $today): ?><br><span class="badge text-bg-danger">Late</span><?php endif; ?>
              </td>
              <td><?= e($o['receiver_name']) ?><br><small class="text-muted"><?= e($o['city']) ?> · <?= e($o['receiver_phone']) ?></small></td>
              <td><?= $o['delivery_type'] === 'same_day' ? '<span class="badge text-bg-danger">Same day</span>' : 'Standard' ?></td>
              <td><?= rs($o['total']) ?></td>
              <td><?= e(payment_label($o['payment_method'])) ?><br><?= badge($o['payment_status']) ?></td>
              <td><?= badge($o['order_status']) ?></td>
              <td><a class="btn btn-sm btn-outline-secondary" href="order_view.php?id=<?= (int) $o['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span><i class="bi bi-clock-history"></i> Latest orders</span>
        <a href="orders.php" class="small">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Order</th><th>Placed</th><th>Receiver</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">No orders yet</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $o): ?>
            <tr style="cursor:pointer" onclick="location='order_view.php?id=<?= (int) $o['id'] ?>'">
              <td><?= e($o['order_number']) ?></td>
              <td><small><?= e(substr($o['created_at'], 0, 16)) ?></small></td>
              <td><?= e($o['receiver_name']) ?></td>
              <td><?= rs($o['total']) ?></td>
              <td><?= badge($o['payment_status']) ?></td>
              <td><?= badge($o['order_status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span><i class="bi bi-balloon-heart"></i> Events in the next 14 days</span>
        <a href="bookings.php?when=upcoming" class="small">All</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!$upcoming_events): ?>
          <li class="list-group-item text-muted">No events coming up</li>
        <?php endif; ?>
        <?php foreach ($upcoming_events as $ev): ?>
          <a href="booking_view.php?id=<?= (int) $ev['id'] ?>" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between">
              <b><?= e(date('D j M', strtotime($ev['event_date']))) ?><?= $ev['event_time'] ? ' · ' . e(date('g:i A', strtotime($ev['event_time']))) : '' ?></b>
              <?= badge($ev['status']) ?>
            </div>
            <small><?= e($ev['event_type']) ?> · <?= e($ev['package_name'] ?? 'Custom') ?><br>
              <span class="text-muted"><?= e(mb_strimwidth($ev['venue_address'], 0, 35, '…')) ?>, <?= e($ev['city']) ?></span></small>
          </a>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold"><i class="bi bi-exclamation-triangle"></i> Low stock (3 or less)</div>
      <ul class="list-group list-group-flush">
        <?php if (!$low_stock): ?>
          <li class="list-group-item text-muted">All flowers have enough stock</li>
        <?php endif; ?>
        <?php foreach ($low_stock as $f): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <a href="flower_edit.php?id=<?= (int) $f['id'] ?>"><?= e($f['name']) ?></a>
            <span class="badge <?= $f['stock'] == 0 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= (int) $f['stock'] ?> left</span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
