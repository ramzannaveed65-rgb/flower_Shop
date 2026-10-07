<?php
// =====================================================
// login.php - Customer login with mobile number + password
// login.php?next=checkout.php  goes back to that page after logging in
// =====================================================

require __DIR__ . '/site/_init.php';

$next = safe_next($_REQUEST['next'] ?? null, 'index.php');
if (current_customer($pdo)) {
    redirect($next);
}

$phone = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = normalize_phone((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM customers WHERE phone = ?");
    $stmt->execute([$phone]);
    $found = $stmt->fetch();

    // Same message for a wrong number or a wrong password (don't reveal which one)
    if ($phone === '' || !$found || !password_verify($password, $found['password_hash'])) {
        sleep(1);                                   // slows down password guessing
        $error = 'Wrong mobile number or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $found['id'];
        flash('Welcome back, ' . $found['name'] . '.');
        redirect($next);
    }
}

$page_title = 'Log in';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="auth">
    <h1 class="mb-2">Log in</h1>
    <p class="text-muted">Use the mobile number you signed up with.</p>

    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="panel">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="mb-3">
        <label class="form-label" for="phone">Mobile number</label>
        <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="03001234567" autocomplete="tel" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-rose btn-lg w-100">Log in</button>
    </form>

    <p class="mt-3">New here? <a href="<?= url('register.php?next=' . rawurlencode($next)) ?>">Create an account</a></p>
  </div>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
