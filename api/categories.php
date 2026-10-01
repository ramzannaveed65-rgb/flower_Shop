<?php
// =====================================================
// api/categories.php - Category list for the filter chips
// GET api/categories.php
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('GET');

$rows = $pdo->query(
    "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name"
)->fetchAll();

$categories = array_map(fn($c) => [
    'id'   => (int) $c['id'],
    'name' => $c['name'],
], $rows);

respond(['categories' => $categories]);
