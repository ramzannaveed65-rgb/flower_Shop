<?php
// =====================================================
// admin/login.php - Shop owner login
// Default: admin@shop.com / admin123  (change it in Settings!)
// =====================================================

require __DIR__ . '/_init.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);           // new session id after login (security)
        $_SESSION['admin_id'] = (int) $admin['id'];
        redirect('index.php');
    }
    sleep(1);                                   // slows down password guessing
    $error = 'Wrong email or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login · Flower Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #fce4ec, #f8bbd0); min-height: 100vh; }
    .btn-pink { background: #c2185b; color: #fff; }
    .btn-pink:hover { background: #a0144b; color: #fff; }
  </style>
</head>
<body class="d-flex align-items-center">
  <div class="container" style="max-width: 400px">
    <div class="card shadow border-0">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <i class="bi bi-flower1" style="font-size: 3rem; color: #c2185b"></i>
          <h4 class="fw-bold mt-2">Flower Shop Admin</h4>
          <p class="text-muted small mb-0">Log in to manage flowers and orders</p>
        </div>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-pink w-100">Log in</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
