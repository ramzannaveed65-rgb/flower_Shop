<?php
// =====================================================
// book-event.php?package=2 - Request an event decoration package
// book-event.php           - Custom request (choose the event type)
// Needs a login. The rules live in config/bookings.php.
// =====================================================

require __DIR__ . '/site/_init.php';

$customer = require_login($pdo);

$package = null;
$pid     = (int) ($_REQUEST['package'] ?? 0);
if ($pid > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, t.name AS event_type FROM event_packages p
         LEFT JOIN event_types t ON t.id = p.event_type_id
         WHERE p.id = ? AND p.is_active = 1"
    );
    $stmt->execute([$pid]);
    $package = $stmt->fetch();
    if (!$package) {
        flash('That package is no longer available.', 'warning');
        redirect('events.php');
    }
}
$types = $pdo->query("SELECT * FROM event_types WHERE is_active = 1 ORDER BY id")->fetchAll();

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$max_date = date('Y-m-d', strtotime('+1 year'));
$form = [
    'event_type_id' => '', 'event_date' => '', 'event_time' => '', 'venue_address' => '',
    'city' => (string) $customer['city'], 'guests' => '',
    'contact_name' => $customer['name'], 'contact_phone' => $customer['phone'], 'notes' => '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    try {
        $booking_id = create_booking($pdo, $customer, $form + ['package_id' => $package ? (int) $package['id'] : 0]);
        flash('Your request is sent. We will call you to confirm the details and the price.');

        // Send the customer on first, then alert the shop on WhatsApp
        redirect_then('bookings.php', function () use ($pdo, $settings, $booking_id, $shop_name) {
            $stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            whatsapp_alert($pdo, $settings, booking_alert_text(format_booking($stmt->fetch()), $shop_name));
        });
    } catch (ShopError $e) {
        $error = $e->getMessage();
    }
}

$page_title = $package ? 'Request ' . $package['name'] : 'Custom decoration request';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head">
    <p class="crumbs"><a href="<?= url('events.php') ?>">Event decoration</a> / Request</p>
    <h1><?= $package ? e($package['name']) : 'Custom decoration request' ?></h1>
    <?php if ($package): ?>
      <p><?= $package['event_type'] ? e($package['event_type']) . ', from ' : 'From ' ?><?= rs($package['starting_price']) ?>. The final price is agreed on the phone.</p>
    <?php else: ?>
      <p>Tell us about your event. We call you back with ideas and a price.</p>
    <?php endif; ?>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="mt-3" style="max-width:760px">
    <?= csrf_field() ?>
    <?php if ($package): ?><input type="hidden" name="package" value="<?= (int) $package['id'] ?>"><?php endif; ?>

    <div class="panel">
      <h2>The event</h2>
      <?php if (!$package): ?>
        <div class="mb-3">
          <label class="form-label" for="event_type_id">Type of event</label>
          <select class="form-select" id="event_type_id" name="event_type_id" required>
            <option value="">Choose</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $form['event_type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="row">
        <div class="col-sm-4 mb-3">
          <label class="form-label" for="event_date">Date</label>
          <input class="form-control" type="date" id="event_date" name="event_date" value="<?= e($form['event_date']) ?>" min="<?= $tomorrow ?>" max="<?= $max_date ?>" required>
        </div>
        <div class="col-sm-4 mb-3">
          <label class="form-label" for="event_time">Start time <span class="text-muted fw-normal">(optional)</span></label>
          <input class="form-control" type="time" id="event_time" name="event_time" value="<?= e($form['event_time']) ?>">
        </div>
        <div class="col-sm-4 mb-3">
          <label class="form-label" for="guests">Guests <span class="text-muted fw-normal">(optional)</span></label>
          <input class="form-control" type="number" id="guests" name="guests" min="1" max="10000" value="<?= e($form['guests']) ?>">
        </div>
      </div>
      <div class="row">
        <div class="col-sm-8 mb-3">
          <label class="form-label" for="venue_address">Venue or hall address</label>
          <input class="form-control" id="venue_address" name="venue_address" value="<?= e($form['venue_address']) ?>" required>
        </div>
        <div class="col-sm-4 mb-3">
          <label class="form-label" for="city">City</label>
          <input class="form-control" id="city" name="city" value="<?= e($form['city']) ?>" required>
        </div>
      </div>
      <div>
        <label class="form-label" for="notes">Anything we should know <span class="text-muted fw-normal">(optional)</span></label>
        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Colours, theme, what should be decorated"><?= e($form['notes']) ?></textarea>
      </div>
    </div>

    <div class="panel">
      <h2>Who should we call?</h2>
      <div class="row">
        <div class="col-sm-6 mb-3 mb-sm-0">
          <label class="form-label" for="contact_name">Name</label>
          <input class="form-control" id="contact_name" name="contact_name" value="<?= e($form['contact_name']) ?>" required>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="contact_phone">Mobile number</label>
          <input class="form-control" type="tel" id="contact_phone" name="contact_phone" value="<?= e($form['contact_phone']) ?>" placeholder="03001234567" required>
        </div>
      </div>
    </div>

    <button class="btn btn-rose btn-lg mt-3">Send booking request</button>
    <p class="small text-muted mt-2">No payment now. We call you first.</p>
  </form>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
