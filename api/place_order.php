<?php
// =====================================================
// api/place_order.php - Place an order (needs login)
// Header: Authorization: Bearer <token>
//
// POST api/place_order.php
// Body (JSON):
// {
//   "source": "cart",                  // "cart" = checkout the cart
//                                      // "buy_now" = one flower, skips the cart
//   "flower_id": 1, "quantity": 1,     // only for buy_now
//
//   "receiver_name":  "Sara",
//   "receiver_phone": "03111234567",
//   "address":        "House 12, Street 4, Gulgasht Colony",
//   "city":           "Islamabad",        // must be one of the delivery cities
//   "delivery_type":  "standard",      // "standard" or "same_day"
//   "delivery_date":  "2026-10-02",    // optional: same_day = today, standard = tomorrow
//   "gift_message":   "Happy Birthday!",   // optional
//   "payment_method": "cod"            // "cod", "nayapay" or "easypaisa"
// }
//
// Rules:
//  - city:     only the delivery cities from Admin -> Settings
//  - same_day: only in same-day cities, only before the cutoff time,
//              and every flower must allow same-day delivery
//  - standard: delivery from tomorrow up to 30 days ahead
//  - stock is checked and reduced; the cart is emptied after checkout
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('POST');

$customer    = require_customer($pdo);
$customer_id = (int) $customer['id'];
$settings    = get_settings($pdo);
$in          = input();

// What is being bought: the whole cart, or one flower (Buy Now)
$source = $in['source'] ?? 'cart';

if ($source === 'buy_now') {
    $lines = [['flower_id' => (int) ($in['flower_id'] ?? 0), 'quantity' => (int) ($in['quantity'] ?? 1)]];
} elseif ($source === 'cart') {
    $stmt = $pdo->prepare(
        "SELECT ci.flower_id, ci.quantity
         FROM cart_items ci JOIN flowers f ON f.id = ci.flower_id
         WHERE ci.customer_id = ? AND f.is_active = 1"
    );
    $stmt->execute([$customer_id]);
    $lines = $stmt->fetchAll();
} else {
    fail('source must be cart or buy_now');
}

// All the rules live in config/orders.php (shared with the website)
try {
    $order_id = place_order($pdo, $customer, $settings, $in, $lines);
} catch (ShopError $e) {
    fail($e->getMessage(), $e->status);
}

// Empty the cart after a cart checkout
if ($source === 'cart') {
    $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?")->execute([$customer_id]);
}

$payment_method = $in['payment_method'];

// ----------------------------------------------------
// 5. Answer: the order + what to do next
// ----------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = format_order($pdo, $stmt->fetch());

$data = ['message' => 'Order placed', 'order' => $order];

if ($payment_method === 'cod') {
    $data['next_step'] = "Pay Rs " . number_format($order['total']) . " in cash when the flowers arrive.";
} else {
    $data['payment_instructions'] = [
        'method' => $payment_method,
        'number' => $settings[$payment_method . '_number'] ?? '',
        'title'  => $settings[$payment_method . '_title'] ?? '',
        'amount' => $order['total'],
    ];
    $label = $payment_method === 'nayapay' ? 'NayaPay' : 'Easypaisa';
    $data['next_step'] = 'Send Rs ' . number_format($order['total']) . " to the $label number above, "
        . 'then submit the Transaction ID and screenshot.';
}

// Answer the app first, then send the WhatsApp alert to the shop
respond_then($data, 201, function () use ($pdo, $settings, $order, $customer) {
    whatsapp_alert($pdo, $settings,
        order_alert_text($order, $customer, $settings['shop_name'] ?? 'Flower Shop'));
});
