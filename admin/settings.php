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
            'same_day_cities'          => implode(', ', array_filter(array_map('trim', explode(',', $_POST['same_day_cities'] ?? '')))),
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
        if ($new['same_day_cities'] === '')                             $errors[] = 'Enter at least one same-day city.';

        if ($errors) {
            foreach (array_unique($errors) as $err) flash($err, 'danger');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?"
            );
            $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $exists = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            foreach ($new as $key => $value) {
                $exists->execute([$key]);
                if ((int) $exists->fetchColumn() > 0) {
                    $stmt->execute([$value, $key]);
                } else {
                    $insert->execute([$key, $value]);
                }
            }
            flash('Settings saved. The app uses them right away.');
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
          <input name="same_day_cities" class="form-control" value="<?= $v('same_day_cities') ?>" placeholder="Multan, Lahore">
          <div class="form-text">Separate cities with commas.</div>
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
      <div><button class="btn btn-pink"><i class="bi bi-check-lg"></i> Save settings</button></div>
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
