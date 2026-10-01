<?php
// =====================================================
// admin/booking_view.php?id=4 - One event booking
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$id   = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT b.*, c.name AS customer_name, c.phone AS customer_phone
     FROM event_bookings b JOIN customers c ON c.id = b.customer_id WHERE b.id = ?"
);
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) {
    flash('Booking not found', 'danger');
    redirect('bookings.php');
}

$next_status = [
    'new'       => ['contacted', 'confirmed', 'cancelled'],
    'contacted' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'status') {
        $new = $_POST['status'] ?? '';
        if (!in_array($new, $next_status[$b['status']] ?? [], true)) {
            flash('That status change is not allowed.', 'danger');
        } elseif ($new === 'confirmed' && $b['quoted_price'] === null) {
            flash('Save the agreed price first, then confirm the booking.', 'danger');
        } else {
            $pdo->prepare("UPDATE event_bookings SET status = ? WHERE id = ?")->execute([$new, $id]);
            flash('Booking is now: ' . nice($new));
        }
    } elseif ($action === 'price') {
        $price = trim($_POST['quoted_price'] ?? '');
        $note  = trim($_POST['shop_note'] ?? '');
        if ($price !== '' && (!is_numeric($price) || (float) $price <= 0)) {
            flash('Enter a valid price.', 'danger');
        } else {
            $pdo->prepare("UPDATE event_bookings SET quoted_price = ?, shop_note = ? WHERE id = ?")
                ->execute([$price === '' ? null : (float) $price, $note === '' ? null : mb_substr($note, 0, 255), $id]);
            flash('Saved. The customer can see this in the app.');
        }
    }
    redirect('booking_view.php?id=' . $id);
}

$page_title = $b['booking_number'];
$active     = 'bookings';
require __DIR__ . '/_header.php';

$wa_phone = '92' . substr($b['contact_phone'], 1);   // 03001234567 -> 923001234567 for WhatsApp
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <a href="bookings.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h3 class="fw-bold mb-0"><?= e($b['booking_number']) ?></h3>
  <?= badge($b['status']) ?>
  <span class="text-muted ms-2">Requested <?= e(substr($b['created_at'], 0, 16)) ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Event</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Event type</dt>
          <dd class="col-sm-8"><span class="badge text-bg-light border fs-6"><?= e($b['event_type']) ?></span></dd>
          <dt class="col-sm-4">Package</dt>
          <dd class="col-sm-8"><?= e($b['package_name'] ?? 'Custom request (no package)') ?>
            <?php if ($b['starting_price'] !== null): ?><span class="text-muted">· from <?= rs($b['starting_price']) ?></span><?php endif; ?></dd>
          <dt class="col-sm-4">Date</dt>
          <dd class="col-sm-8 fw-bold"><?= e(date('l, j F Y', strtotime($b['event_date']))) ?>
            <?= $b['event_time'] ? ' at ' . e(date('g:i A', strtotime($b['event_time']))) : '' ?></dd>
          <dt class="col-sm-4">Venue</dt>
          <dd class="col-sm-8"><?= nl2br(e($b['venue_address'])) ?>, <?= e($b['city']) ?></dd>
          <dt class="col-sm-4">Guests</dt>
          <dd class="col-sm-8"><?= $b['guests'] ? (int) $b['guests'] : '—' ?></dd>
          <dt class="col-sm-4">Customer notes</dt>
          <dd class="col-sm-8 mb-0"><?= $b['notes'] ? '<div class="p-2 bg-light rounded">' . nl2br(e($b['notes'])) . '</div>' : '—' ?></dd>
        </dl>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold">Contact</div>
      <div class="card-body">
        <p class="mb-1"><b><?= e($b['contact_name']) ?></b> · <?= e($b['contact_phone']) ?></p>
        <p class="small text-muted">Account: <?= e($b['customer_name']) ?> (<?= e($b['customer_phone']) ?>)</p>
        <a href="tel:<?= e($b['contact_phone']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-telephone"></i> Call</a>
        <a href="https://wa.me/<?= e($wa_phone) ?>?text=<?= rawurlencode('Assalam o Alaikum ' . $b['contact_name'] . ', this is about your ' . $b['event_type'] . ' decoration booking ' . $b['booking_number'] . '.') ?>"
           target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Agreed price &amp; note to customer</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="price">
          <label class="form-label">Final price (Rs)</label>
          <input name="quoted_price" type="number" min="1" step="1" class="form-control mb-2"
                 value="<?= $b['quoted_price'] !== null ? e((float) $b['quoted_price']) : '' ?>" placeholder="e.g. 52000">
          <label class="form-label">Note (customer sees this)</label>
          <textarea name="shop_note" rows="3" class="form-control mb-2" maxlength="255"
                    placeholder="e.g. Team will arrive at 3 PM. Rs 10,000 advance by NayaPay."><?= e($b['shop_note'] ?? '') ?></textarea>
          <button class="btn btn-outline-dark w-100">Save</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold">Booking status</div>
      <div class="card-body">
        <p class="mb-2">Current: <?= badge($b['status']) ?></p>
        <?php $options = $next_status[$b['status']]; ?>
        <?php if ($options): ?>
          <form method="post" class="d-flex flex-wrap gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <?php foreach ($options as $s): ?>
              <button name="status" value="<?= $s ?>"
                class="btn <?= $s === 'cancelled' ? 'btn-outline-danger' : 'btn-pink' ?>"
                <?= $s === 'cancelled' ? "onclick=\"return confirm('Cancel this booking?')\"" : '' ?>>
                <?= $s === 'cancelled' ? '<i class="bi bi-x-circle"></i> Cancel' : 'Mark as ' . nice($s) ?>
              </button>
            <?php endforeach; ?>
          </form>
          <?php if ($b['quoted_price'] === null && in_array('confirmed', $options, true)): ?>
            <p class="small text-muted mt-2 mb-0">Save the agreed price above before confirming.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted mb-0">This booking is finished. No more changes.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
