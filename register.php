<?php
// =====================================================
// register.php - Create a customer account
// =====================================================

require __DIR__ . '/site/_init.php';

$next = safe_next($_REQUEST['next'] ?? null, 'index.php');
if (current_customer($pdo)) {
    redirect($next);
}

$cities = delivery_cities($settings);
$form   = ['name' => '', 'phone' => '', 'city' => '', 'address' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']    = trim((string) ($_POST['name'] ?? ''));
    $form['phone']   = normalize_phone((string) ($_POST['phone'] ?? ''));
    $form['city']    = trim((string) ($_POST['city'] ?? ''));
    $form['address'] = trim((string) ($_POST['address'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');

    if (mb_strlen($form['name']) < 3)                    $errors[] = 'Enter your full name.';
    if (!preg_match('/^03\d{9}$/', $form['phone']))      $errors[] = 'Enter a valid mobile number, for example 03001234567.';
    if (strlen($password) < 6)                           $errors[] = 'The password must be at least 6 characters.';

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->execute([$form['phone']]);
        if ($stmt->fetch()) {
            $errors[] = 'This mobile number already has an account. Please log in.';
        }
    }

    if (!$errors) {
        $pdo->prepare(
            "INSERT INTO customers (name, phone, password_hash, address, city) VALUES (?, ?, ?, ?, ?)"
        )->execute([
            $form['name'], $form['phone'], password_hash($password, PASSWORD_DEFAULT),
            $form['address'] !== '' ? $form['address'] : null,
            $form['city'] !== '' ? $form['city'] : null,
        ]);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $pdo->lastInsertId();
        flash('Your account is ready. Welcome, ' . $form['name'] . '.');
        redirect($next);
    }
}

$page_title = 'Create an account';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="auth">
    <h1 class="mb-2">Create an account</h1>
    <p class="text-muted">It takes a minute. You need it to place and follow your orders.</p>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" class="panel">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="mb-3">
        <label class="form-label" for="name">Full name</label>
        <input class="form-control" id="name" name="name" value="<?= e($form['name']) ?>" autocomplete="name" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="phone">Mobile number</label>
        <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="03001234567" autocomplete="tel" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" minlength="6" autocomplete="new-password" required>
        <div class="form-text">At least 6 characters.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="city">City <span class="text-muted fw-normal">(optional)</span></label>
        <select class="form-select" id="city" name="city">
          <option value="">Choose</option>
          <?php foreach ($cities as $c): ?>
            <option <?= $form['city'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="address">Address <span class="text-muted fw-normal">(optional, saves time at checkout)</span></label>
        <textarea class="form-control" id="address" name="address" rows="2" autocomplete="street-address"><?= e($form['address']) ?></textarea>
      </div>
      <button class="btn btn-rose btn-lg w-100">Create account</button>
      <p class="small text-muted mt-3 mb-0">By creating an account you agree to our <a href="<?= url('privacy.php') ?>">Privacy Policy</a>.</p>
    </form>

    <p class="mt-3">Already have an account? <a href="<?= url('login.php?next=' . rawurlencode($next)) ?>">Log in</a></p>
  </div>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
