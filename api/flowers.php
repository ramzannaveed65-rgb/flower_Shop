<?php
// =====================================================
// api/flowers.php - Flower list and single flower
//
// All flowers:          GET api/flowers.php
// One category:         GET api/flowers.php?category_id=1
// Search by name:       GET api/flowers.php?search=rose
// One flower (details): GET api/flowers.php?id=3
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('GET');

$sql = "SELECT f.*, c.name AS category_name
        FROM flowers f
        LEFT JOIN categories c ON c.id = f.category_id
        WHERE f.is_active = 1";

// ----- Single flower (details screen) -----
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare($sql . " AND f.id = ?");
    $stmt->execute([(int) $_GET['id']]);
    $flower = $stmt->fetch();

    if (!$flower) {
        fail('Flower not found', 404);
    }
    respond(['flower' => format_flower($flower)]);
}

// ----- Flower list (home screen) -----
$params = [];

if (!empty($_GET['category_id'])) {
    $sql .= " AND f.category_id = ?";
    $params[] = (int) $_GET['category_id'];
}

if (!empty($_GET['search'])) {
    $sql .= " AND f.name LIKE ?";
    $params[] = '%' . trim($_GET['search']) . '%';
}

$sql .= " ORDER BY f.created_at DESC, f.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flowers = array_map('format_flower', $stmt->fetchAll());

respond([
    'count'   => count($flowers),
    'flowers' => $flowers,
]);
