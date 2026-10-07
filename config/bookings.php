<?php
// =====================================================
// config/bookings.php - The rules for an event decoration booking
//
// Used by the website (book-event.php) AND by the API.
// No payment here: the shop calls the customer and agrees the final price.
// =====================================================

// -----------------------------------------------------
// Save a booking request.
//   $in: package_id (or event_type_id for a custom request), event_date, event_time,
//        venue_address, city, guests, contact_name, contact_phone, notes
// Returns the new booking's id. Throws ShopError with a message for the customer.
// -----------------------------------------------------
function create_booking(PDO $pdo, array $customer, array $in): int
{
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
        if (!$package) throw new ShopError('This package is no longer available', 404);
        $event_type = $package['event_type'] ?? 'Event';
    } else {
        $stmt = $pdo->prepare("SELECT name FROM event_types WHERE id = ? AND is_active = 1");
        $stmt->execute([(int) ($in['event_type_id'] ?? 0)]);
        $event_type = $stmt->fetchColumn();
        if (!$event_type) throw new ShopError('Choose the event type (e.g. Marriage, Birthday, Engagement)');
    }

    // ----- Date & time -----
    $event_date = (string) ($in['event_date'] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $event_date);
    if (!$d || $d->format('Y-m-d') !== $event_date)          throw new ShopError('Choose the event date');
    if ($event_date < date('Y-m-d', strtotime('+1 day')))    throw new ShopError('Event date must be from tomorrow onwards');
    if ($event_date > date('Y-m-d', strtotime('+1 year')))   throw new ShopError('Event date can be at most 1 year ahead');

    $event_time = trim((string) ($in['event_time'] ?? ''));
    if ($event_time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $event_time)) {
        throw new ShopError('Event time must look like 18:30');
    }

    // ----- Venue & contact -----
    $venue   = trim((string) ($in['venue_address'] ?? ''));
    $city    = trim((string) ($in['city'] ?? ''));
    $guests  = ($in['guests'] ?? '') === '' || $in['guests'] === null ? null : (int) $in['guests'];
    $name    = trim((string) ($in['contact_name'] ?? $customer['name']));
    $phone   = normalize_phone((string) ($in['contact_phone'] ?? $customer['phone']));
    $notes   = trim((string) ($in['notes'] ?? ''));

    if (mb_strlen($venue) < 5)                      throw new ShopError('Enter the venue / hall address');
    if ($city === '')                               throw new ShopError('Enter the city');
    if ($guests !== null && ($guests < 1 || $guests > 10000)) throw new ShopError('Number of guests looks wrong');
    if (mb_strlen($name) < 3)                       throw new ShopError('Enter the contact name');
    if (!preg_match('/^03\d{9}$/', $phone))         throw new ShopError('Enter a valid contact number, e.g. 03001234567');
    if (mb_strlen($notes) > 1000)                   throw new ShopError('Notes are too long (max 1000 characters)');

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
        throw new ShopError('Could not send the booking. Please try again', 500);
    }
    return $id;
}

// Event booking with clean values
function format_booking(array $b): array
{
    return [
        'id'             => (int) $b['id'],
        'booking_number' => $b['booking_number'],
        'status'         => $b['status'],
        'event_type'     => $b['event_type'],
        'package_id'     => $b['package_id'] !== null ? (int) $b['package_id'] : null,
        'package_name'   => $b['package_name'],
        'starting_price' => $b['starting_price'] !== null ? (float) $b['starting_price'] : null,
        'quoted_price'   => $b['quoted_price'] !== null ? (float) $b['quoted_price'] : null,
        'event_date'     => $b['event_date'],
        'event_time'     => $b['event_time'] ? substr($b['event_time'], 0, 5) : null,   // "18:30"
        'venue_address'  => $b['venue_address'],
        'city'           => $b['city'],
        'guests'         => $b['guests'] !== null ? (int) $b['guests'] : null,
        'contact_name'   => $b['contact_name'],
        'contact_phone'  => $b['contact_phone'],
        'notes'          => $b['notes'],
        'shop_note'      => $b['shop_note'],
        'created_at'     => $b['created_at'],
    ];
}

