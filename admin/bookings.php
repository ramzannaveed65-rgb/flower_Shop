<?php
// =====================================================
// admin/bookings.php - Event decoration bookings
//   ?status=new   ?q=search   ?from=2026-10-01   ?package=2
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$statuses = ['new', 'contacted', 'confirmed', 'completed', 'cancelled'];
$status   = $_GET['status'] ?? '';
$q        = trim($_GET['q'] ?? '');
$when     = $_GET['when'] ?? '';
$package  = (int) ($_GET['package'] ?? 0);

$sql    = "SELECT * FROM event_bookings WHERE 1 = 1";
$params = [];

if (in_array($status, $statuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}
if ($q !== '') {
    $sql .= " AND (booking_number LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR venue_address LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($when === 'upcoming') {
    $sql .= " AND event_date >= ? AND status NOT IN ('cancelled', 'completed')";
    $params[] = date('Y-m-d');
}
if ($package > 0) {
    $sql .= " AND package_id = ?";
    $params[] = $package;
}
$sql .= $when === 'upcoming' ? " ORDER BY event_date, event_time" : " ORDER BY id DESC";
$sql .= " LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = 'Event bookings';
$active     = 'bookings';
require __DIR__ . '/_header.php';
?>

<h3 class="fw-bold mb-3">Event bookings</h3>

<div class="alert alert-light border small">
  <b>How it works:</b> a customer chooses a package (or a custom request) and sends the event date and venue.
  Call them, agree the final price, then mark the booking as <b>Contacted</b> → <b>Confirmed</b> → <b>Completed</b>.
  The price and note you save are shown to the customer on the website.
</div>

<form class="card card-body mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small">Search</label>
      <input name="q" class="form-control" placeholder="Booking no, name, phone or venue" value="<?= e($q) ?>">
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
    <div class="col-md-3">
      <label class="form-label small">Show</label>
      <select name="when" class="form-select">
        <option value="">Newest first</option>
        <option value="upcoming" <?= $when === 'upcoming' ? 'selected' : '' ?>>Upcoming events (by date)</option>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-pink"><i class="bi bi-funnel"></i> Filter</button>
      <a href="bookings.php" class="btn btn-outline-secondary">Clear</a>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Booking</th><th>Event</th><th>Event date</th><th>Venue</th><th>Customer</th><th>Price</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$bookings): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">No bookings found</td></tr>
      <?php endif; ?>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="fw-semibold"><?= e($b['booking_number']) ?><br><small class="text-muted fw-normal"><?= e(substr($b['created_at'], 0, 16)) ?></small></td>
          <td><span class="badge text-bg-light border"><?= e($b['event_type']) ?></span><br><small><?= e($b['package_name'] ?? 'Custom request') ?></small></td>
          <td class="text-nowrap"><?= e(date('D, j M Y', strtotime($b['event_date']))) ?>
            <?php if ($b['event_time']): ?><br><small><?= e(date('g:i A', strtotime($b['event_time']))) ?></small><?php endif; ?></td>
          <td><small><?= e(mb_strimwidth($b['venue_address'], 0, 40, '…')) ?><br><?= e($b['city']) ?></small></td>
          <td><?= e($b['contact_name']) ?><br><small class="text-muted"><?= e($b['contact_phone']) ?></small></td>
          <td class="text-nowrap">
            <?php if ($b['quoted_price'] !== null): ?><b><?= rs($b['quoted_price']) ?></b>
            <?php elseif ($b['starting_price'] !== null): ?><small class="text-muted">from <?= rs($b['starting_price']) ?></small>
            <?php else: ?><small class="text-muted">—</small><?php endif; ?>
          </td>
          <td><?= badge($b['status']) ?></td>
          <td><a class="btn btn-sm btn-outline-secondary" href="booking_view.php?id=<?= (int) $b['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
