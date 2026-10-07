<?php
// =====================================================
// config/orders.php - The rules for placing an order and paying for it
//
// Used by the website (checkout.php, order.php) AND by the API,
// so both always follow exactly the same rules.
// =====================================================

// A problem the customer can fix (shown to them as a message)
class ShopError extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

// Clean a Pakistani mobile number: "+92 300-1234567" -> "03001234567"
function normalize_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (str_starts_with($phone, '+92')) $phone = '0' . substr($phone, 3);
    elseif (str_starts_with($phone, '92') && strlen($phone) === 12) $phone = '0' . substr($phone, 2);
    return $phone;
}

// Can a same-day order still be placed right now?
function same_day_open(array $settings): bool
{
    return date('H:i') < ($settings['same_day_cutoff_time'] ?? '14:00');
}

function same_day_cities(array $settings): array
{
    return array_values(array_filter(array_map('trim', explode(',', $settings['same_day_cities'] ?? ''))));
}

// -----------------------------------------------------
// Place an order.
//   $customer : the logged-in customer (row from the customers table)
//   $in       : receiver_name, receiver_phone, address, city, delivery_type,
//               delivery_date, gift_message, payment_method
//   $lines    : what is being bought: [['flower_id' => 1, 'quantity' => 2], ...]
// Returns the new order's id. Throws ShopError with a message for the customer.
//
// Rules:
//  - city:     only the delivery cities from Admin -> Settings
//  - same_day: only in same-day cities, only before the cutoff time,
//              and every flower must allow same-day delivery
//  - standard: delivery from tomorrow up to 30 days ahead
//  - stock is checked and reduced
// -----------------------------------------------------
function place_order(PDO $pdo, array $customer, array $settings, array $in, array $lines): int
{
    $customer_id = (int) $customer['id'];

    // ----- 1. Delivery details -----
    $receiver_name  = trim((string) ($in['receiver_name'] ?? $customer['name']));
    $receiver_phone = normalize_phone((string) ($in['receiver_phone'] ?? $customer['phone']));
    $address        = trim((string) ($in['address'] ?? ''));
    $city           = trim((string) ($in['city'] ?? ''));
    $gift_message   = trim((string) ($in['gift_message'] ?? ''));
    $delivery_type  = $in['delivery_type'] ?? 'standard';
    $payment_method = $in['payment_method'] ?? '';

    if (mb_strlen($receiver_name) < 3)                  throw new ShopError('Enter the receiver\'s name');
    if (!preg_match('/^03\d{9}$/', $receiver_phone))    throw new ShopError('Enter a valid receiver mobile number, e.g. 03001234567');
    if (mb_strlen($address) < 10)                       throw new ShopError('Enter the full delivery address');
    if ($city === '')                                   throw new ShopError('Choose the city');
    if (mb_strlen($gift_message) > 255)                 throw new ShopError('Gift message is too long (max 255 characters)');
    if (!in_array($delivery_type, ['standard', 'same_day'], true)) throw new ShopError('Choose standard or same-day delivery');
    if (!in_array($payment_method, ['cod', 'nayapay', 'easypaisa'], true)) throw new ShopError('Choose a payment method');

    if ($payment_method === 'cod' && ($settings['cod_enabled'] ?? '1') !== '1') {
        throw new ShopError('Cash on Delivery is not available right now. Please pay with NayaPay or Easypaisa');
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
        throw new ShopError('Sorry, we only deliver in ' . implode(' / ', $allowed) . ' right now');
    }
    if ($match !== null) {
        $city = $match;       // save the city exactly as written in the settings
    }

    // ----- 2. Delivery date rules -----
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $max_date = date('Y-m-d', strtotime('+30 days'));

    if ($delivery_type === 'same_day') {
        $cities = array_map('mb_strtolower', same_day_cities($settings));
        if (!in_array(mb_strtolower($city), $cities, true)) {
            throw new ShopError('Same-day delivery is only available in: ' . ($settings['same_day_cities'] ?? ''));
        }
        if (!same_day_open($settings)) {
            $cutoff = $settings['same_day_cutoff_time'] ?? '14:00';
            throw new ShopError('Same-day orders must be placed before ' . date('g:i A', strtotime($cutoff))
                 . '. Please choose standard delivery');
        }
        $delivery_date = $today;
    } else {
        $delivery_date = (string) ($in['delivery_date'] ?? '') ?: $tomorrow;
        $d = DateTime::createFromFormat('Y-m-d', $delivery_date);
        if (!$d || $d->format('Y-m-d') !== $delivery_date) throw new ShopError('Choose a delivery date');
        if ($delivery_date < $tomorrow)  throw new ShopError('Standard delivery starts from tomorrow. For today, choose same-day delivery');
        if ($delivery_date > $max_date)  throw new ShopError('Delivery date can be at most 30 days ahead');
    }

    // ----- 3. The items -----
    if (!$lines) {
        throw new ShopError('Your cart is empty');
    }
    $find  = $pdo->prepare("SELECT * FROM flowers WHERE id = ? AND is_active = 1");
    $items = [];
    foreach ($lines as $line) {
        $qty = (int) ($line['quantity'] ?? 0);
        if ($qty < 1) throw new ShopError('Quantity must be at least 1');
        $find->execute([(int) ($line['flower_id'] ?? 0)]);
        $flower = $find->fetch();
        if (!$flower) throw new ShopError('One of the items is no longer available. Please check your cart', 404);
        $flower['quantity'] = $qty;
        $items[] = $flower;
    }

    // Check stock + same-day for every item, and add up the subtotal
    $subtotal = 0;
    foreach ($items as $it) {
        $qty = (int) $it['quantity'];
        if ($qty > (int) $it['stock']) {
            throw new ShopError((int) $it['stock'] > 0
                ? "Only {$it['stock']} of {$it['name']} available"
                : "{$it['name']} is out of stock");
        }
        if ($delivery_type === 'same_day' && !(int) $it['same_day_available']) {
            throw new ShopError("{$it['name']} is not available for same-day delivery");
        }
        $subtotal += (float) $it['price'] * $qty;
    }

    $delivery_charge = (float) ($delivery_type === 'same_day'
        ? ($settings['same_day_delivery_charge'] ?? 0)
        : ($settings['standard_delivery_charge'] ?? 0));
    $total = $subtotal + $delivery_charge;

    // ----- 4. Save everything in one transaction (if anything fails, nothing is saved) -----
    try {
        $pdo->beginTransaction();

        // Reduce stock. "AND stock >= ?" stops two customers buying the last item at the same time.
        $reduce = $pdo->prepare("UPDATE flowers SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($items as $it) {
            $reduce->execute([(int) $it['quantity'], (int) $it['id'], (int) $it['quantity']]);
            if ($reduce->rowCount() === 0) {
                throw new ShopError("{$it['name']} just went out of stock. Please update your cart", 409);
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

        $pdo->commit();
    } catch (ShopError $e) {
        $pdo->rollBack();
        throw $e;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw new ShopError('Could not place the order. Please try again', 500);
    }

    return $order_id;
}

// -----------------------------------------------------
// Save the proof of an advance payment (NayaPay / Easypaisa).
//   $order : the order row (must belong to the customer - check that before calling)
//   $file  : the uploaded screenshot, i.e. $_FILES['screenshot']
// Throws ShopError with a message for the customer.
// -----------------------------------------------------
function submit_payment_proof(PDO $pdo, array $order, string $transaction_id, string $sender_number, ?array $file): void
{
    $transaction_id = strtoupper(preg_replace('/\s+/', '', $transaction_id));
    $sender_number  = normalize_phone($sender_number);

    if ($order['payment_method'] === 'cod')              throw new ShopError('This order is Cash on Delivery. No payment proof needed');
    if ($order['order_status'] === 'cancelled')          throw new ShopError('This order was cancelled');
    if ($order['payment_status'] === 'submitted')        throw new ShopError('Payment already submitted. Please wait for the shop to check it');
    if ($order['payment_status'] === 'verified')         throw new ShopError('Payment already verified');

    if (!preg_match('/^[A-Z0-9-]{6,40}$/', $transaction_id)) {
        throw new ShopError('Enter the Transaction ID from your NayaPay/Easypaisa message (letters and numbers)');
    }
    if ($sender_number !== '' && !preg_match('/^03\d{9}$/', $sender_number)) {
        throw new ShopError('Sender number must look like 03001234567');
    }
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    if ($stmt->fetch()) {
        throw new ShopError('This Transaction ID has already been used', 409);
    }

    // ----- Check and save the screenshot -----
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new ShopError('Please attach a screenshot of the payment');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new ShopError('Screenshot upload failed. Try a smaller image');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new ShopError('Screenshot is too big (max 5 MB)');
    }

    // getimagesize() reads the real file content, so a renamed .php file is rejected
    $info    = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new ShopError('Screenshot must be a JPG, PNG or WEBP image');
    }

    $folder = __DIR__ . '/../uploads/payments/';
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new ShopError('Server could not create the uploads folder', 500);
    }
    // Random file name: nobody can guess other customers' screenshots
    $filename = $order['order_number'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $folder . $filename)) {
        throw new ShopError('Could not save the screenshot', 500);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO payments (order_id, method, transaction_id, sender_number, amount, screenshot)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            (int) $order['id'], $order['payment_method'], $transaction_id,
            $sender_number !== '' ? $sender_number : null,
            $order['total'], 'uploads/payments/' . $filename,
        ]);
        $pdo->prepare("UPDATE orders SET payment_status = 'submitted' WHERE id = ?")->execute([(int) $order['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        @unlink($folder . $filename);
        throw new ShopError('Could not save the payment. Please try again', 500);
    }
}

// -----------------------------------------------------
// Full order: order details + items + latest payment
// -----------------------------------------------------
function format_order(PDO $pdo, array $o): array
{
    $stmt = $pdo->prepare(
        "SELECT oi.flower_id, oi.flower_name, oi.price, oi.quantity, f.image
         FROM order_items oi
         LEFT JOIN flowers f ON f.id = oi.flower_id
         WHERE oi.order_id = ?
         ORDER BY oi.id"
    );
    $stmt->execute([$o['id']]);
    $items = array_map(fn($i) => [
        'flower_id'  => $i['flower_id'] !== null ? (int) $i['flower_id'] : null,
        'name'       => $i['flower_name'],
        'price'      => (float) $i['price'],
        'quantity'   => (int) $i['quantity'],
        'line_total' => (float) $i['price'] * (int) $i['quantity'],
        'image'      => $i['image'] ?: null,
        'image_url'  => $i['image'] ? BASE_URL . $i['image'] : null,
    ], $stmt->fetchAll());

    $stmt = $pdo->prepare(
        "SELECT transaction_id, amount, status, admin_note, created_at
         FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$o['id']]);
    $payment = $stmt->fetch() ?: null;

    return [
        'id'              => (int) $o['id'],
        'order_number'    => $o['order_number'],
        'order_status'    => $o['order_status'],
        'payment_method'  => $o['payment_method'],
        'payment_status'  => $o['payment_status'],
        'delivery_type'   => $o['delivery_type'],
        'delivery_date'   => $o['delivery_date'],
        'receiver_name'   => $o['receiver_name'],
        'receiver_phone'  => $o['receiver_phone'],
        'address'         => $o['address'],
        'city'            => $o['city'],
        'gift_message'    => $o['gift_message'],
        'subtotal'        => (float) $o['subtotal'],
        'delivery_charge' => (float) $o['delivery_charge'],
        'total'           => (float) $o['total'],
        'created_at'      => $o['created_at'],
        'items'           => $items,
        'payment'         => $payment ? [
            'transaction_id' => $payment['transaction_id'],
            'amount'         => (float) $payment['amount'],
            'status'         => $payment['status'],
            'admin_note'     => $payment['admin_note'],
            'submitted_at'   => $payment['created_at'],
        ] : null,
    ];
}
