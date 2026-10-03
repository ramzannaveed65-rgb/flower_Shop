<?php
// =====================================================
// admin/settings.php - Shop settings + change admin password
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- Shop settings -----
    if ($action === 'settings') {
        $errors = [];
        $new = [
            'shop_name'                => trim($_POST['shop_name'] ?? ''),
            'standard_delivery_charge' => trim($_POST['standard_delivery_charge'] ?? ''),
            'same_day_delivery_charge' => trim($_POST['same_day_delivery_charge'] ?? ''),
            'same_day_cutoff_time'     => trim($_POST['same_day_cutoff_time'] ?? ''),
            'delivery_cities'          => implode(', ', array_filter(array_map('trim', explode(',', $_POST['delivery_cities'] ?? '')))),
            'same_day_cities'          => implode(', ', array_filter(array_map('trim', explode(',', $_POST['same_day_cities'] ?? '')))),
            'google_review_url'        => trim($_POST['google_review_url'] ?? ''),
            'whatsapp_alerts_enabled'  => isset($_POST['whatsapp_alerts_enabled']) ? '1' : '0',
            'whatsapp_alert_phone'     => trim($_POST['whatsapp_alert_phone'] ?? ''),
            'whatsapp_alert_apikey'    => trim($_POST['whatsapp_alert_apikey'] ?? ''),
            'cod_enabled'              => isset($_POST['cod_enabled']) ? '1' : '0',
            'nayapay_number'          => trim($_POST['nayapay_number'] ?? ''),
            'nayapay_title'           => trim($_POST['nayapay_title'] ?? ''),
            'easypaisa_number'         => trim($_POST['easypaisa_number'] ?? ''),
            'easypaisa_title'          => trim($_POST['easypaisa_title'] ?? ''),
        ];

        if ($new['shop_name'] === '')                                   $errors[] = 'Enter the shop name.';
        foreach (['standard_delivery_charge', 'same_day_delivery_charge'] as $k) {
            if (!is_numeric($new[$k]) || (float) $new[$k] < 0)          $errors[] = 'Delivery charges must be 0 or more.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $new['same_day_cutoff_time'])) $errors[] = 'Cutoff time must look like 14:00.';
        if ($new['delivery_cities'] === '')                             $errors[] = 'Enter at least one delivery city.';
        if ($new['same_day_cities'] === '')                             $errors[] = 'Enter at least one same-day city.';

        // Same-day cities must be cities the shop delivers to
        $deliver = array_map('mb_strtolower', array_map('trim', explode(',', $new['delivery_cities'])));
        foreach (array_map('trim', explode(',', $new['same_day_cities'])) as $c) {
            if ($c !== '' && !in_array(mb_strtolower($c), $deliver, true)) {
                $errors[] = "Same-day city \"$c\" is not in the delivery cities. Add it there too, or remove it.";
            }
        }

        if ($new['google_review_url'] !== ''
            && (!preg_match('~^https://~i', $new['google_review_url']) || !filter_var($new['google_review_url'], FILTER_VALIDATE_URL))) {
            $errors[] = 'The Google review link must start with https://';
        }
        if ($new['whatsapp_alerts_enabled'] === '1'
            && (whatsapp_number($new['whatsapp_alert_phone']) === '' || $new['whatsapp_alert_apikey'] === '')) {
            $errors[] = 'To switch on WhatsApp alerts, enter the WhatsApp number and the API key.';
        }
        foreach ($new as $key => $value) {
            if (mb_strlen($value) > 255)                                $errors[] = 'One of the values is too long (max 255 characters).';
        }

        if ($errors) {
            foreach (array_unique($errors) as $err) flash($err, 'danger');
        } else {
            foreach ($new as $key => $value) {
                save_setting($pdo, $key, $value);
            }
            flash('Settings saved. The app uses them right away.');
        }
    }

    // ----- Send a test WhatsApp message -----
    elseif ($action === 'whatsapp_test') {
        $s = get_settings($pdo);
        [$ok, $error] = whatsapp_send($s,
            "*Test message*\n" . ($s['shop_name'] ?? 'Flower Shop')
            . "\nWhatsApp order alerts are working. New orders will arrive here.");
        save_setting($pdo, 'whatsapp_last_result', mb_substr(date('j M, g:i A') . ($ok ? ': sent' : ': FAILED - ' . $error), 0, 250));
        if ($ok) {
            flash('Test message sent. Check WhatsApp on ' . whatsapp_number($s['whatsapp_alert_phone'] ?? '') . ' (it can take a minute).');
        } else {
            flash('The test message was not sent: ' . $error, 'danger');
        }
    }

    // ----- Change password -----
    elseif ($action === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $pass1   = (string) ($_POST['new'] ?? '');
        $pass2   = (string) ($_POST['confirm'] ?? '');

        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $stmt->execute([$admin['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('Current password is wrong.', 'danger');
        } elseif (strlen($pass1) < 8) {
            flash('New password must be at least 8 characters.', 'danger');
        } elseif ($pass1 !== $pass2) {
            flash('The two new passwords do not match.', 'danger');
        } else {
            $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($pass1, PASSWORD_DEFAULT), $admin['id']]);
            flash('Password changed.');
        }
    }
    redirect('settings.php');
}

$s = get_settings($pdo);
$v = fn(string $key, string $default = '') => e($s[$key] ?? $default);

$page_title = 'Settings';
$active     = 'settings';
require __DIR__ . '/_header.php';
?>

<h3 class="fw-bold mb-3">Settings</h3>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" class="card card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="settings">

      <h6 class="fw-bold">Shop</h6>
      <div class="mb-3">
        <label class="form-label">Shop name</label>
        <input name="shop_name" class="form-control" value="<?= $v('shop_name') ?>" required>
      </div>

      <h6 class="fw-bold mt-2">Delivery</h6>
      <div class="mb-3">
        <label class="form-label">Delivery cities</label>
        <input name="delivery_cities" class="form-control" value="<?= e(implode(', ', delivery_cities($s))) ?>" placeholder="Islamabad, Rawalpindi" required>
        <div class="form-text">Customers can only order to these cities. Separate cities with commas.</div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Standard delivery charge (Rs)</label>
          <input name="standard_delivery_charge" type="number" min="0" class="form-control" value="<?= $v('standard_delivery_charge', '0') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Same-day delivery charge (Rs)</label>
          <input name="same_day_delivery_charge" type="number" min="0" class="form-control" value="<?= $v('same_day_delivery_charge', '0') ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Same-day orders accepted until</label>
          <input name="same_day_cutoff_time" type="time" class="form-control" value="<?= $v('same_day_cutoff_time', '14:00') ?>">
          <div class="form-text">After this time customers can only choose standard delivery.</div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Same-day cities</label>
          <input name="same_day_cities" class="form-control" value="<?= $v('same_day_cities') ?>" placeholder="Islamabad, Rawalpindi">
          <div class="form-text">Must also be in the delivery cities. Separate with commas.</div>
        </div>
      </div>

      <h6 class="fw-bold mt-2">Payment</h6>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="cod_enabled" id="cod" <?= ($s['cod_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="cod">Allow Cash on Delivery</label>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">NayaPay number / ID</label>
          <input name="nayapay_number" class="form-control" value="<?= $v('nayapay_number') ?>" placeholder="NayaPay number or ID, e.g. 0300-1234567">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">NayaPay account title</label>
          <input name="nayapay_title" class="form-control" value="<?= $v('nayapay_title') ?>" placeholder="Name on the account">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Easypaisa number</label>
          <input name="easypaisa_number" class="form-control" value="<?= $v('easypaisa_number') ?>" placeholder="0345-1234567">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Easypaisa account title</label>
          <input name="easypaisa_title" class="form-control" value="<?= $v('easypaisa_title') ?>" placeholder="Name on the account">
        </div>
      </div>

      <h6 class="fw-bold mt-2">Google reviews</h6>
      <div class="mb-3">
        <label class="form-label">Google review link</label>
        <input name="google_review_url" type="url" class="form-control" value="<?= $v('google_review_url') ?>" placeholder="https://g.page/r/XXXXXXXX/review">
        <div class="form-text">When an order is delivered, the app shows "Rate us on Google" and opens this link.
          Get it from your Google Business Profile: <b>Ask for reviews</b> &rarr; copy the link. Leave empty to hide the button.</div>
      </div>

      <h6 class="fw-bold mt-2">WhatsApp order alerts</h6>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="whatsapp_alerts_enabled" id="wa" <?= ($s['whatsapp_alerts_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
        <label class="form-check-label" for="wa">Send every new order and event booking to WhatsApp</label>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">WhatsApp number that gets the alerts</label>
          <input name="whatsapp_alert_phone" class="form-control" value="<?= $v('whatsapp_alert_phone') ?>" placeholder="0300-1234567">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">API key</label>
          <input name="whatsapp_alert_apikey" class="form-control" value="<?= $v('whatsapp_alert_apikey') ?>" placeholder="e.g. 1234567" autocomplete="off">
        </div>
      </div>
      <div class="alert alert-light border small">
        <b>How to get the API key (one time, 2 minutes):</b>
        <ol class="mb-0 ps-3">
          <li>On the phone that should get the alerts, save this number as a contact: <b>+34 684 770 005</b> (name it "Order Alerts").</li>
          <li>Send it this WhatsApp message: <b>I allow callmebot to send me messages</b></li>
          <li>It replies with your API key. Type the key above, switch the alerts on and press <b>Save settings</b>.</li>
          <li>Press <b>Send test message</b> below to check.</li>
        </ol>
      </div>
      <?php if (!empty($s['whatsapp_last_result'])): ?>
        <p class="small text-muted">Last alert: <?= e($s['whatsapp_last_result']) ?></p>
      <?php endif; ?>

      <div><button class="btn btn-pink"><i class="bi bi-check-lg"></i> Save settings</button></div>
    </form>

    <form method="post" class="mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="whatsapp_test">
      <button class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> Send test message</button>
      <span class="small text-muted ms-2">Save the settings first.</span>
    </form>
  </div>

  <div class="col-lg-4">
    <form method="post" class="card card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h6 class="fw-bold">Change admin password</h6>
      <p class="small text-muted">Logged in as <?= e($admin['email']) ?></p>
      <input type="password" name="current" class="form-control mb-2" placeholder="Current password" required>
      <input type="password" name="new" class="form-control mb-2" placeholder="New password (8+ characters)" required>
      <input type="password" name="confirm" class="form-control mb-3" placeholder="Repeat new password" required>
      <button class="btn btn-outline-dark">Change password</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
