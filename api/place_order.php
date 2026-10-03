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

// ----------------------------------------------------
// 1. Delivery details
// ----------------------------------------------------
$receiver_name  = trim($in['receiver_name'] ?? $customer['name']);
$receiver_phone = normalize_phone($in['receiver_phone'] ?? $customer['phone']);
$address        = trim($in['address'] ?? '');
$city           = trim($in['city'] ?? '');
$gift_message   = trim($in['gift_message'] ?? '');
$delivery_type  = $in['delivery_type'] ?? 'standard';
$payment_method = $in['payment_method'] ?? '';

if (mb_strlen($receiver_name) < 3)                  fail('Enter the receiver\'s name');
if (!preg_match('/^03\d{9}$/', $receiver_phone))    fail('Enter a valid receiver mobile number, e.g. 03001234567');
if (mb_strlen($address) < 10)                       fail('Enter the full delivery address');
if ($city === '')                                   fail('Choose the city');
if (mb_strlen($gift_message) > 255)                 fail('Gift message is too long (max 255 characters)');
if (!in_array($delivery_type, ['standard', 'same_day'], true)) fail('delivery_type must be standard or same_day');
if (!in_array($payment_method, ['cod', 'nayapay', 'easypaisa'], true)) fail('Choose a payment method: cod, nayapay or easypaisa');

if ($payment_method === 'cod' && ($settings['cod_enabled'] ?? '1') !== '1') {
    fail('Cash on Delivery is not available right now. Please pay with NayaPay or Easypaisa');
}

// Delivery area: only the cities set in Admin -> Settings
$allowed = delivery_cities($settings);
$match   = null;
foreach ($allowed as $c) {
    if (mb_strtolower($c) === mb_strtolower($city)) {
        $match = $c;
        break;
    }
}
if ($allowed && $match === null) {
    fail('Sorry, we only deliver in ' . implode(' / ', $allowed) . ' right now');
}
if ($match !== null) {
    $city = $match;       // save the city exactly as written in the settings
}

// ----------------------------------------------------
// 2. Delivery date rules
// ----------------------------------------------------
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$max_date = date('Y-m-d', strtotime('+30 days'));

if ($delivery_type === 'same_day') {
    $cities = array_map(fn($c) => mb_strtolower(trim($c)), explode(',', $settings['same_day_cities'] ?? ''));
    if (!in_array(mb_strtolower($city), $cities, true)) {
        fail('Same-day delivery is only available in: ' . ($settings['same_day_cities'] ?? ''));
    }
    $cutoff = $settings['same_day_cutoff_time'] ?? '14:00';
    if (date('H:i') >= $cutoff) {
        fail('Same-day orders must be placed before ' . date('g:i A', strtotime($cutoff))
             . '. Please choose standard delivery');
    }
    $delivery_date = $today;
} else {
    $delivery_date = $in['delivery_date'] ?? $tomorrow;
    $d = DateTime::createFromFormat('Y-m-d', $delivery_date);
    if (!$d || $d->format('Y-m-d') !== $delivery_date) fail('delivery_date must look like 2026-10-02');
    if ($delivery_date < $tomorrow)  fail('Standard delivery starts from tomorrow. For today, choose same-day delivery');
    if ($delivery_date > $max_date)  fail('Delivery date can be at most 30 days ahead');
}

// ----------------------------------------------------
// 3. Items: from the cart, or one flower (Buy Now)
// ----------------------------------------------------
$source = $in['source'] ?? 'cart';

if ($source === 'buy_now') {
    $qty = (int) ($in['quantity'] ?? 1);
    if ($qty < 1) fail('quantity must be at least 1');
    $stmt = $pdo->prepare("SELECT *, ? AS quantity FROM flowers WHERE id = ? AND is_active = 1");
    $stmt->execute([$qty, (int) ($in['flower_id'] ?? 0)]);
    $items = $stmt->fetchAll();
    if (!$items) fail('Flower not found', 404);
} elseif ($source === 'cart') {
    $stmt = $pdo->prepare(
        "SELECT f.*, ci.quantity
         FROM cart_items ci JOIN flowers f ON f.id = ci.flower_id
         WHERE ci.customer_id = ? AND f.is_active = 1"
    );
    $stmt->execute([$customer_id]);
    $items = $stmt->fetchAll();
    if (!$items) fail('Your cart is empty');
} else {
    fail('source must be cart or buy_now');
}

// Check stock + same-day for every item, and add up the subtotal
$subtotal = 0;
foreach ($items as $it) {
    $qty = (int) $it['quantity'];
    if ($qty > (int) $it['stock']) {
        fail((int) $it['stock'] > 0
            ? "Only {$it['stock']} of {$it['name']} available"
            : "{$it['name']} is out of stock");
    }
    if ($delivery_type === 'same_day' && !(int) $it['same_day_available']) {
        fail("{$it['name']} is not available for same-day delivery");
    }
    $subtotal += (float) $it['price'] * $qty;
}

$delivery_charge = (float) ($delivery_type === 'same_day'
    ? ($settings['same_day_delivery_charge'] ?? 0)
    : ($settings['standard_delivery_charge'] ?? 0));
$total = $subtotal + $delivery_charge;

// ----------------------------------------------------
// 4. Save everything in one transaction
//    (if anything fails, nothing is saved)
// ----------------------------------------------------
try {
    $pdo->beginTransaction();

    // Reduce stock. "AND stock >= ?" stops two customers buying the last item at the same time.
    $reduce = $pdo->prepare("UPDATE flowers SET stock = stock - ? WHERE id = ? AND stock >= ?");
    foreach ($items as $it) {
        $reduce->execute([(int) $it['quantity'], (int) $it['id'], (int) $it['quantity']]);
        if ($reduce->rowCount() === 0) {
            throw new RuntimeException("{$it['name']} just went out of stock. Please update your cart");
        }
    }

    // Create the order (temporary number, replaced right after)
    $stmt = $pdo->prepare(
        "INSERT INTO orders
           (order_number, customer_id, receiver_name, receiver_phone, address, city,
            delivery_type, delivery_date, gift_message,
            subtotal, delivery_charge, total, payment_method)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        'TMP-' . bin2hex(random_bytes(6)), $customer_id,
        $receiver_name, $receiver_phone, $address, $city,
        $delivery_type, $delivery_date, $gift_message !== '' ? $gift_message : null,
        $subtotal, $delivery_charge, $total, $payment_method,
    ]);
    $order_id = (int) $pdo->lastInsertId();

    // Readable order number: FL-20260930-0007
    $order_number = 'FL-' . date('Ymd') . '-' . str_pad((string) $order_id, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE orders SET order_number = ? WHERE id = ?")->execute([$order_number, $order_id]);

    // Save items (price copied, so old orders stay correct if prices change)
    $add = $pdo->prepare(
        "INSERT INTO order_items (order_id, flower_id, flower_name, price, quantity) VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($items as $it) {
        $add->execute([$order_id, (int) $it['id'], $it['name'], (float) $it['price'], (int) $it['quantity']]);
    }

    // Empty the cart after a cart checkout
    if ($source === 'cart') {
        $pdo->prepare("DELETE FROM cart_items WHERE customer_id = ?")->execute([$customer_id]);
    }

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    fail($e->getMessage(), 409);
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('Could not place the order. Please try again', 500);
}

// ----------------------------------------------------
// 5. Answer: the order + what to do next
// ----------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = format_order($pdo, $stmt->fetch());

$data = ['message' => 'Order placed', 'order' => $order];

if ($payment_method === 'cod') {
    $data['next_step'] = "Pay Rs " . number_format($total) . " in cash when the flowers arrive.";
} else {
    $data['payment_instructions'] = [
        'method' => $payment_method,
        'number' => $settings[$payment_method . '_number'] ?? '',
        'title'  => $settings[$payment_method . '_title'] ?? '',
        'amount' => $total,
    ];
    $label = $payment_method === 'nayapay' ? 'NayaPay' : 'Easypaisa';
    $data['next_step'] = 'Send Rs ' . number_format($total) . " to the $label number above, "
        . 'then submit the Transaction ID and screenshot.';
}

// Answer the app first, then send the WhatsApp alert to the shop
respond_then($data, 201, function () use ($pdo, $settings, $order, $customer) {
    whatsapp_alert($pdo, $settings,
        order_alert_text($order, $customer, $settings['shop_name'] ?? 'Flower Shop'));
});
