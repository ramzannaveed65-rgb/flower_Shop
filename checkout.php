<?php
// =====================================================
// checkout.php - Delivery details, delivery day, payment method, place the order
// Needs a login. The order rules live in config/orders.php.
// =====================================================

require __DIR__ . '/site/_init.php';

$customer = require_login($pdo);
$cart     = cart_details($pdo);

if (!$cart['lines']) {
    flash('Your cart is empty.', 'info');
    redirect('cart.php');
}
if (array_filter($cart['lines'], fn($l) => !$l['in_stock'])) {
    flash('An item in your cart is sold out. Please remove it to continue.', 'warning');
    redirect('cart.php');
}

$cities      = delivery_cities($settings);
$sd          = same_day_status($settings);
$cod         = ($settings['cod_enabled'] ?? '1') === '1';
$tomorrow    = date('Y-m-d', strtotime('+1 day'));
$max_date    = date('Y-m-d', strtotime('+30 days'));
$standard    = (float) ($settings['standard_delivery_charge'] ?? 0);
$same_day    = (float) ($settings['same_day_delivery_charge'] ?? 0);
// Same-day needs every item in the cart to allow it
$items_ok    = !array_filter($cart['lines'], fn($l) => !(int) $l['flower']['same_day_available']);

// Start with the customer's own details
$my_city = '';
foreach ($cities as $c) {
    if (mb_strtolower($c) === mb_strtolower((string) $customer['city'])) $my_city = $c;
}
if ($my_city === '' && count($cities) === 1) {
    $my_city = $cities[0];
}
$form = [
    'receiver_name'  => $customer['name'],
    'receiver_phone' => $customer['phone'],
    'address'        => (string) $customer['address'],
    'city'           => $my_city,
    'delivery_type'  => 'standard',
    'delivery_date'  => $tomorrow,
    'gift_message'   => '',
    'payment_method' => $cod ? 'cod' : 'nayapay',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $lines = array_map(fn($l) => ['flower_id' => (int) $l['flower']['id'], 'quantity' => (int) $l['quantity']], $cart['lines']);

    try {
        $order_id = place_order($pdo, $customer, $settings, $form, $lines);
        cart_clear();

        // Send the customer to the order page first, then alert the shop on WhatsApp
        redirect_then('order.php?id=' . $order_id . '&placed=1', function () use ($pdo, $settings, $order_id, $customer, $shop_name) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            whatsapp_alert($pdo, $settings, order_alert_text(format_order($pdo, $stmt->fetch()), $customer, $shop_name));
        });
    } catch (ShopError $e) {
        $error = $e->getMessage();
    }
}

$type_now  = $form['delivery_type'] === 'same_day' ? 'same_day' : 'standard';
$delivery  = $type_now === 'same_day' ? $same_day : $standard;

$page_title    = 'Checkout';
$hide_wa_float = true;          // keeps the WhatsApp button from covering "Place order" on phones
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head"><h1>Checkout</h1></div>

  <?php if ($error !== ''): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="row g-4 mt-1" data-checkout
        data-same-day-cities="<?= e(json_encode($sd['cities'])) ?>" data-same-day-open="<?= $sd['open'] ? 1 : 0 ?>"
        data-standard="<?= $standard ?>" data-same-day="<?= $same_day ?>" data-subtotal="<?= $cart['subtotal'] ?>">
    <?= csrf_field() ?>

    <div class="col-lg-7">
      <!-- Who gets the flowers -->
      <div class="panel">
        <h2>Deliver to</h2>
        <div class="row">
          <div class="col-sm-6 mb-3">
            <label class="form-label" for="receiver_name">Receiver's name</label>
            <input class="form-control" id="receiver_name" name="receiver_name" value="<?= e($form['receiver_name']) ?>" required>
          </div>
          <div class="col-sm-6 mb-3">
            <label class="form-label" for="receiver_phone">Receiver's mobile number</label>
            <input class="form-control" type="tel" id="receiver_phone" name="receiver_phone" value="<?= e($form['receiver_phone']) ?>" placeholder="03001234567" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="address">Full address</label>
          <textarea class="form-control" id="address" name="address" rows="2" placeholder="House, street, sector or area" required><?= e($form['address']) ?></textarea>
        </div>
        <fieldset>
          <legend class="form-label fs-6">City</legend>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($cities as $c): ?>
              <label class="choice mb-0"><input type="radio" name="city" value="<?= e($c) ?>" <?= $form['city'] === $c ? 'checked' : '' ?> required> <?= e($c) ?></label>
            <?php endforeach; ?>
          </div>
          <div class="form-text">We deliver only in <?= e(implode(' and ', $cities)) ?>.</div>
        </fieldset>
      </div>

      <!-- When -->
      <div class="panel">
        <h2>Delivery</h2>
        <label class="choice">
          <input type="radio" name="delivery_type" value="standard" <?= $type_now === 'standard' ? 'checked' : '' ?>>
          <strong>Standard</strong> <span class="choice-price"><?= rs($standard) ?></span>
          <small>Pick any day from tomorrow.</small>
        </label>
        <label class="choice">
          <input type="radio" name="delivery_type" value="same_day" data-items-ok="<?= $items_ok ? 1 : 0 ?>" <?= $type_now === 'same_day' ? 'checked' : '' ?> <?= ($sd['open'] && $items_ok) ? '' : 'disabled' ?>>
          <strong>Same day</strong> <span class="choice-price"><?= rs($same_day) ?></span>
          <small>
            <?php if (!$sd['cities']): ?>Not available right now.
            <?php elseif (!$items_ok): ?>An item in your cart needs one day's notice.
            <?php elseif (!$sd['open']): ?>Closed for today. Same-day orders are taken until <?= e($sd['cutoff_label']) ?>.
            <?php else: ?>Delivered today in <?= e(implode(' and ', $sd['cities'])) ?>. Order before <?= e($sd['cutoff_label']) ?>.
            <?php endif; ?>
          </small>
        </label>
        <div class="mt-3" data-date-row <?= $type_now === 'same_day' ? 'hidden' : '' ?>>
          <label class="form-label" for="delivery_date">Delivery date</label>
          <input class="form-control" style="max-width:240px" type="date" id="delivery_date" name="delivery_date"
                 value="<?= e($form['delivery_date'] ?: $tomorrow) ?>" min="<?= $tomorrow ?>" max="<?= $max_date ?>">
        </div>
      </div>

      <!-- Gift message -->
      <div class="panel">
        <h2><label for="gift_message">Gift message <span class="text-muted fw-normal">(optional)</span></label></h2>
        <textarea class="form-control" id="gift_message" name="gift_message" rows="2" maxlength="255" placeholder="Happy Birthday! Love, Ali"><?= e($form['gift_message']) ?></textarea>
        <div class="form-text">We write it on a card and send it with the flowers.</div>
      </div>

      <!-- Payment -->
      <div class="panel">
        <h2>Payment</h2>
        <?php if ($cod): ?>
          <label class="choice">
            <input type="radio" name="payment_method" value="cod" <?= $form['payment_method'] === 'cod' ? 'checked' : '' ?>>
            <strong>Cash on Delivery</strong>
            <small>Pay when the flowers arrive.</small>
          </label>
        <?php endif; ?>
        <label class="choice">
          <input type="radio" name="payment_method" value="nayapay" <?= $form['payment_method'] === 'nayapay' ? 'checked' : '' ?>>
          <strong>NayaPay</strong>
          <small>Pay in advance. You get the account number on the next page and upload a screenshot.</small>
        </label>
        <label class="choice mb-0">
          <input type="radio" name="payment_method" value="easypaisa" <?= $form['payment_method'] === 'easypaisa' ? 'checked' : '' ?>>
          <strong>Easypaisa</strong>
          <small>Pay in advance. You get the account number on the next page and upload a screenshot.</small>
        </label>
      </div>
    </div>

    <!-- Summary -->
    <div class="col-lg-5">
      <div class="panel" style="position:sticky;top:96px">
        <h2>Your order</h2>
        <?php foreach ($cart['lines'] as $l): ?>
          <div class="sum-row"><span><?= (int) $l['quantity'] ?> &times; <?= e($l['flower']['name']) ?></span><span class="text-nowrap"><?= rs($l['line_total']) ?></span></div>
        <?php endforeach; ?>
        <hr>
        <div class="sum-row"><span>Items</span><span><?= rs($cart['subtotal']) ?></span></div>
        <div class="sum-row"><span>Delivery</span><span data-delivery><?= rs($delivery) ?></span></div>
        <div class="sum-row total"><span>Total</span><span data-total><?= rs($cart['subtotal'] + $delivery) ?></span></div>
        <button class="btn btn-rose btn-lg w-100 mt-3">Place order</button>
        <p class="small text-muted mt-3 mb-0"><a href="<?= url('cart.php') ?>">Change the items in your cart</a></p>
      </div>
    </div>
  </form>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
