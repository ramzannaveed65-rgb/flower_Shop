<?php
// =====================================================
// api/book_event.php - Send an event decoration booking request (needs login)
// Header: Authorization: Bearer <token>
//
// POST api/book_event.php
// Body (JSON):
// {
//   "package_id":    2,              // a package from events.php
//   "event_type_id": 1,              // only needed for a custom request (no package)
//   "event_date":    "2026-11-20",   // from tomorrow up to 1 year ahead
//   "event_time":    "18:30",        // optional
//   "venue_address": "Shalimar Marquee, Bosan Road",
//   "city":          "Multan",
//   "guests":        300,            // optional
//   "contact_name":  "Ali Khan",     // optional, defaults to the customer
//   "contact_phone": "03001234567",  // optional, defaults to the customer
//   "notes":         "Red and white theme please"   // optional
// }
//
// No payment here: the shop calls the customer and agrees the final price.
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('POST');

$customer = require_customer($pdo);
$in       = input();

// ----- Package or custom request -----
$package_id = (int) ($in['package_id'] ?? 0);
$package    = null;

if ($package_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.*, t.name AS event_type FROM event_packages p
         LEFT JOIN event_types t ON t.id = p.event_type_id
         WHERE p.id = ? AND p.is_active = 1"
    );
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    if (!$package) fail('This package is no longer available', 404);
    $event_type = $package['event_type'] ?? 'Event';
} else {
    $stmt = $pdo->prepare("SELECT name FROM event_types WHERE id = ? AND is_active = 1");
    $stmt->execute([(int) ($in['event_type_id'] ?? 0)]);
    $event_type = $stmt->fetchColumn();
    if (!$event_type) fail('Choose the event type (e.g. Marriage, Birthday, Engagement)');
}

// ----- Date & time -----
$event_date = (string) ($in['event_date'] ?? '');
$d = DateTime::createFromFormat('Y-m-d', $event_date);
if (!$d || $d->format('Y-m-d') !== $event_date) fail('Choose the event date');
if ($event_date < date('Y-m-d', strtotime('+1 day')))   fail('Event date must be from tomorrow onwards');
if ($event_date > date('Y-m-d', strtotime('+1 year')))  fail('Event date can be at most 1 year ahead');

$event_time = trim((string) ($in['event_time'] ?? ''));
if ($event_time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $event_time)) {
    fail('Event time must look like 18:30');
}

// ----- Venue & contact -----
$venue   = trim((string) ($in['venue_address'] ?? ''));
$city    = trim((string) ($in['city'] ?? ''));
$guests  = ($in['guests'] ?? '') === '' || $in['guests'] === null ? null : (int) $in['guests'];
$name    = trim((string) ($in['contact_name'] ?? $customer['name']));
$phone   = normalize_phone((string) ($in['contact_phone'] ?? $customer['phone']));
$notes   = trim((string) ($in['notes'] ?? ''));

if (mb_strlen($venue) < 5)                      fail('Enter the venue / hall address');
if ($city === '')                               fail('Enter the city');
if ($guests !== null && ($guests < 1 || $guests > 10000)) fail('Number of guests looks wrong');
if (mb_strlen($name) < 3)                       fail('Enter the contact name');
if (!preg_match('/^03\d{9}$/', $phone))         fail('Enter a valid contact number, e.g. 03001234567');
if (mb_strlen($notes) > 1000)                   fail('Notes are too long (max 1000 characters)');

// ----- Save -----
$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO event_bookings
           (booking_number, customer_id, package_id, package_name, event_type, starting_price,
            event_date, event_time, venue_address, city, guests, contact_name, contact_phone, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        'TMP-' . bin2hex(random_bytes(6)), (int) $customer['id'],
        $package ? (int) $package['id'] : null,
        $package ? $package['name'] : null,
        $event_type,
        $package ? (float) $package['starting_price'] : null,
        $event_date, $event_time !== '' ? $event_time : null,
        $venue, $city, $guests, $name, $phone, $notes !== '' ? $notes : null,
    ]);
    $id = (int) $pdo->lastInsertId();
    $number = 'EV-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE event_bookings SET booking_number = ? WHERE id = ?")->execute([$number, $id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('Could not send the booking. Please try again', 500);
}

$stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE id = ?");
$stmt->execute([$id]);

$booking  = format_booking($stmt->fetch());
$settings = get_settings($pdo);

// Answer the app first, then send the WhatsApp alert to the shop
respond_then([
    'message' => 'Booking request sent. The shop will call you to confirm the details and price.',
    'booking' => $booking,
], 201, function () use ($pdo, $settings, $booking) {
    whatsapp_alert($pdo, $settings, booking_alert_text($booking, $settings['shop_name'] ?? 'Flower Shop'));
});
