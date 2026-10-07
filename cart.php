<?php
// =====================================================
// cart.php - The cart: add, change quantity, remove, go to checkout
// =====================================================

require __DIR__ . '/site/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $qty    = max(1, min(99, (int) ($_POST['quantity'] ?? 1)));

    if ($action === 'add' || $action === 'buy') {
        $stmt = $pdo->prepare("SELECT name, stock FROM flowers WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $flower = $stmt->fetch();
        if (!$flower) {
            flash('This item is no longer available.', 'warning');
        } elseif ((int) $flower['stock'] < 1) {
            flash($flower['name'] . ' is sold out.', 'warning');
        } else {
            cart_add($id, $qty);
            if ($action === 'buy') {
                redirect('checkout.php');          // "Order now" goes straight to checkout
            }
            flash($flower['name'] . ' added to your cart.');
        }
    } elseif ($action === 'update') {
        cart_set($id, (int) ($_POST['quantity'] ?? 1));
    } elseif ($action === 'remove') {
        cart_set($id, 0);
    }
    redirect('cart.php');
}

$cart = cart_details($pdo);
$has_sold_out = (bool) array_filter($cart['lines'], fn($l) => !$l['in_stock']);

$page_title = 'Your cart';
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head"><h1>Your cart</h1></div>

  <?php foreach ($cart['notes'] as $note): ?>
    <div class="alert alert-warning mt-3"><?= e($note) ?></div>
  <?php endforeach; ?>

  <?php if (!$cart['lines']): ?>
    <div class="empty">
      <i class="bi bi-bag"></i>
      Your cart is empty.
      <p class="mt-3"><a class="btn btn-rose" href="<?= url('shop.php') ?>">Shop flowers</a></p>
    </div>
  <?php else: ?>
    <div class="row g-4 mt-1">
      <div class="col-lg-8">
        <div class="panel">
          <?php foreach ($cart['lines'] as $l): $f = $l['flower']; $img = photo($f['image']); ?>
            <div class="cart-line">
              <a class="thumb" href="<?= url('product.php?id=' . (int) $f['id']) ?>">
                <?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><span class="no-photo"><i class="bi bi-flower1"></i></span><?php endif; ?>
              </a>
              <div>
                <a class="fw-semibold text-decoration-none" style="color:var(--ink)" href="<?= url('product.php?id=' . (int) $f['id']) ?>"><?= e($f['name']) ?></a>
                <div class="text-muted small"><?= rs($f['price']) ?> each</div>
                <?php if (!$l['in_stock']): ?><div class="mt-1"><?= status_badge('cancelled', 'Sold out') ?></div><?php endif; ?>
              </div>
              <div class="line-end d-flex align-items-center gap-3">
                <form method="post" data-autosubmit>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                  <div class="qty">
                    <button type="button" data-step="down" aria-label="Less">&minus;</button>
                    <input type="number" name="quantity" value="<?= (int) $l['quantity'] ?>" min="1" max="<?= max(1, min(99, (int) $f['stock'])) ?>" aria-label="Quantity of <?= e($f['name']) ?>">
                    <button type="button" data-step="up" aria-label="More">+</button>
                  </div>
                  <noscript><button class="btn btn-sm btn-outline-leaf">Update</button></noscript>
                </form>
                <strong class="text-nowrap"><?= rs($l['line_total']) ?></strong>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                  <button class="icon-btn" aria-label="Remove <?= e($f['name']) ?>" title="Remove"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="mt-3"><a href="<?= url('shop.php') ?>"><i class="bi bi-arrow-left"></i> Continue shopping</a></p>
      </div>

      <div class="col-lg-4">
        <div class="panel">
          <h2>Summary</h2>
          <div class="sum-row"><span>Items</span><span><?= rs($cart['subtotal']) ?></span></div>
          <div class="sum-row"><span>Delivery</span><span class="text-muted">from <?= rs($settings['standard_delivery_charge'] ?? 0) ?></span></div>
          <div class="sum-row total"><span>Subtotal</span><span><?= rs($cart['subtotal']) ?></span></div>
          <?php if ($has_sold_out): ?>
            <div class="alert alert-warning mt-3 mb-0">Remove the sold-out item to continue.</div>
          <?php else: ?>
            <a class="btn btn-rose btn-lg w-100 mt-3" href="<?= url('checkout.php') ?>">Go to checkout</a>
          <?php endif; ?>
          <p class="small text-muted mt-3 mb-0">You choose the delivery day and payment method on the next page.</p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
