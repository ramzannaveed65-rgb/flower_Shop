<?php
// =====================================================
// api/events.php - Event decoration types and packages (no login needed)
//
// Everything:            GET api/events.php
// One event type only:   GET api/events.php?type_id=1
// One package:           GET api/events.php?id=3
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('GET');

$sql = "SELECT p.*, t.name AS event_type
        FROM event_packages p
        LEFT JOIN event_types t ON t.id = p.event_type_id
        WHERE p.is_active = 1 AND (t.id IS NULL OR t.is_active = 1)";

// ----- One package -----
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare($sql . " AND p.id = ?");
    $stmt->execute([(int) $_GET['id']]);
    $package = $stmt->fetch();
    if (!$package) {
        fail('Package not found', 404);
    }
    respond(['package' => format_package($package)]);
}

// ----- Event types (for the filter chips and custom requests) -----
$types = array_map(fn($t) => ['id' => (int) $t['id'], 'name' => $t['name']],
    $pdo->query("SELECT id, name FROM event_types WHERE is_active = 1 ORDER BY id")->fetchAll());

// ----- Packages -----
$params = [];
if (!empty($_GET['type_id'])) {
    $sql .= " AND p.event_type_id = ?";
    $params[] = (int) $_GET['type_id'];
}
$sql .= " ORDER BY t.id, p.starting_price, p.id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

respond([
    'event_types' => $types,
    'packages'    => array_map('format_package', $stmt->fetchAll()),
]);
