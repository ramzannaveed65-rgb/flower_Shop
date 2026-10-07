<?php
// =====================================================
// delete-account.php - Delete an account from the web (public page)
//
// Google Play asks for a web link where a customer can delete
// the account without opening the app:
//   https://YOUR-DOMAIN/delete-account.php
// The customer enters the mobile number and password of the account.
// =====================================================

require __DIR__ . '/site/_init.php';
require __DIR__ . '/config/account.php';

$contact = trim($settings['support_contact'] ?? '') ?: trim($settings['shop_phone'] ?? '');
$done    = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = normalize_phone((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($phone === '' || $password === '') {
        $error = 'Enter your mobile number and password.';
    } elseif (empty($_POST['confirm'])) {
        $error = 'Please tick the box to confirm.';
    } else {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();
        if (!$customer || !password_verify($password, $customer['password_hash'])) {
            sleep(2);     // slows down password guessing
            $error = 'Wrong mobile number or password. The account was not deleted.';
        } else {
            try {
                delete_customer_account($pdo, (int) $customer['id']);
                $done = true;
                unset($_SESSION['customer_id']);        // log out on this browser too
            } catch (Throwable $e) {
                $error = 'Could not delete the account. Please try again.';
            }
        }
    }
}

$page_title = 'Delete my account';
require __DIR__ . '/site/_header.php';
?>
<div class="container">
<div class="auth" style="max-width:560px">
<h1 class="mb-3">Delete my account</h1>

<?php if ($done): ?>
  <div class="alert alert-success"><b>Your account has been deleted.</b> You have been logged out.
    Thank you for using <?= e($shop_name) ?>.</div>
<?php else: ?>
  <p>Use this page to delete your <b><?= e($shop_name) ?></b> account. Enter the mobile number and password of the account.</p>

  <div class="alert alert-warning">
    <b>What is deleted:</b> your name, mobile number, address, city, password and cart.<br>
    <b>What is kept:</b> records of orders and bookings you already placed, for the shop's accounts.
    See the <a href="<?= url('privacy.php') ?>">Privacy Policy</a>.<br>
    <b>This cannot be undone.</b>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off" class="panel">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="phone">Mobile number of the account</label>
      <input class="form-control" type="tel" id="phone" name="phone" placeholder="03001234567" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" required>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
      <label class="form-check-label" for="confirm">I understand that my account will be deleted for good.</label>
    </div>
    <button class="btn btn-rose">Delete my account</button>
  </form>

  <?php if ($contact !== ''): ?>
    <p style="margin-top:18px">Forgot your password? Contact us and we will delete the account for you:
      <?= e($contact) ?></p>
  <?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/site/_footer.php'; ?>
