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

// All the rules live in config/bookings.php (shared with the website)
try {
    $id = create_booking($pdo, $customer, $in);
} catch (ShopError $e) {
    fail($e->getMessage(), $e->status);
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
