<?php
// =====================================================
// bookings.php - The logged-in customer's event booking requests
// =====================================================

require __DIR__ . '/site/_init.php';

$customer = require_login($pdo);

// ----- Cancel a request (only before the shop has confirmed it) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE id = ? AND customer_id = ?");
    $stmt->execute([(int) ($_POST['id'] ?? 0), (int) $customer['id']]);
    $b = $stmt->fetch();
    if (!$b) {
        flash('Booking not found.', 'warning');
    } elseif (!in_array($b['status'], ['new', 'contacted'], true)) {
        flash('This booking is already ' . $b['status'] . '. Please call us to change it.', 'warning');
    } else {
        $pdo->prepare("UPDATE event_bookings SET status = 'cancelled' WHERE id = ?")->execute([(int) $b['id']]);
        flash('Booking request cancelled.', 'info');
    }
    redirect('bookings.php');
}

$stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([(int) $customer['id']]);
$bookings = array_map('format_booking', $stmt->fetchAll());

$labels = ['new' => 'Request sent', 'contacted' => 'We called you', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

$page_title = 'My event bookings';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head"><h1>My event bookings</h1></div>

  <?php if (!$bookings): ?>
    <div class="empty">
      <i class="bi bi-stars"></i>
      You have not sent a booking request yet.
      <p class="mt-3"><a class="btn btn-rose" href="<?= url('events.php') ?>">See decoration packages</a></p>
    </div>
  <?php else: ?>
    <div class="mt-3" style="max-width:820px">
      <?php foreach ($bookings as $b): ?>
        <div class="panel mb-3">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <strong><?= e($b['event_type']) ?><?= $b['package_name'] ? ': ' . e($b['package_name']) : ' (custom request)' ?></strong>
              <div class="text-muted small"><?= e($b['booking_number']) ?></div>
            </div>
            <?= status_badge($b['status'], $labels[$b['status']] ?? null) ?>
          </div>
          <div class="sum-row mt-3"><span class="text-muted">When</span><span><?= e(nice_date($b['event_date'])) ?><?= $b['event_time'] ? ', ' . e(date('g:i A', strtotime($b['event_time']))) : '' ?></span></div>
          <div class="sum-row"><span class="text-muted">Where</span><span class="text-end"><?= e($b['venue_address']) ?>, <?= e($b['city']) ?></span></div>
          <?php if ($b['guests'] !== null): ?><div class="sum-row"><span class="text-muted">Guests</span><span><?= (int) $b['guests'] ?></span></div><?php endif; ?>
          <div class="sum-row"><span class="text-muted">Price</span>
            <span><?= $b['quoted_price'] !== null
                ? '<strong>' . rs($b['quoted_price']) . '</strong>'
                : ($b['starting_price'] !== null ? 'From ' . rs($b['starting_price']) . ', to be agreed' : 'To be agreed') ?></span></div>
          <?php if (!empty($b['shop_note'])): ?>
            <div class="alert alert-info mt-3 mb-0">Note from the shop: <?= e($b['shop_note']) ?></div>
          <?php endif; ?>
          <?php if (in_array($b['status'], ['new', 'contacted'], true)): ?>
            <form method="post" class="mt-3" onsubmit="return confirm('Cancel this booking request?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-sm btn-outline-leaf">Cancel request</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
