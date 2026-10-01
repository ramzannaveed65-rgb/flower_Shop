<?php
// =====================================================
// api/my_bookings.php - Customer's event bookings (needs login)
// Header: Authorization: Bearer <token>
//
// All my bookings:   GET  api/my_bookings.php
// One booking:       GET  api/my_bookings.php?id=4
// Cancel a booking:  POST api/my_bookings.php   { "action": "cancel", "id": 4 }
//                    (only while it is still "new" or "contacted")
// =====================================================

require __DIR__ . '/_helpers.php';

$customer    = require_customer($pdo);
$customer_id = (int) $customer['id'];

function find_booking(PDO $pdo, int $id, int $customer_id): array
{
    $stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, $customer_id]);
    $b = $stmt->fetch();
    if (!$b) fail('Booking not found', 404);
    return $b;
}

// ----- Cancel -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = input();
    if (($in['action'] ?? '') !== 'cancel') fail('Unknown action');
    $b = find_booking($pdo, (int) ($in['id'] ?? 0), $customer_id);
    if (!in_array($b['status'], ['new', 'contacted'], true)) {
        fail('This booking is already ' . $b['status'] . '. Please call the shop to change it.');
    }
    $pdo->prepare("UPDATE event_bookings SET status = 'cancelled' WHERE id = ?")->execute([$b['id']]);
    respond([
        'message' => 'Booking cancelled',
        'booking' => format_booking(find_booking($pdo, (int) $b['id'], $customer_id)),
    ]);
}

require_method('GET');

// ----- One booking -----
if (isset($_GET['id'])) {
    respond(['booking' => format_booking(find_booking($pdo, (int) $_GET['id'], $customer_id))]);
}

// ----- All bookings -----
$stmt = $pdo->prepare("SELECT * FROM event_bookings WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$customer_id]);
$bookings = array_map('format_booking', $stmt->fetchAll());

respond(['count' => count($bookings), 'bookings' => $bookings]);
