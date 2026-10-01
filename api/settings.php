<?php
// =====================================================
// api/settings.php - Shop settings the app needs at checkout
// (delivery charges, same-day rules, COD, NayaPay/Easypaisa numbers)
// GET api/settings.php
// =====================================================

require __DIR__ . '/_helpers.php';
require_method('GET');

$s = $pdo->query("SELECT setting_key, setting_value FROM settings")
         ->fetchAll(PDO::FETCH_KEY_PAIR);

respond([
    'settings' => [
        'shop_name'                => $s['shop_name'] ?? 'Flower Shop',
        'standard_delivery_charge' => (float) ($s['standard_delivery_charge'] ?? 0),
        'same_day_delivery_charge' => (float) ($s['same_day_delivery_charge'] ?? 0),
        'same_day_cutoff_time'     => $s['same_day_cutoff_time'] ?? '14:00',
        'same_day_cities'          => array_values(array_filter(array_map('trim',
                                        explode(',', $s['same_day_cities'] ?? '')))),
        'cod_enabled'              => ($s['cod_enabled'] ?? '1') === '1',
        'nayapay'  => [
            'number' => $s['nayapay_number'] ?? '',
            'title'  => $s['nayapay_title'] ?? '',
        ],
        'easypaisa' => [
            'number' => $s['easypaisa_number'] ?? '',
            'title'  => $s['easypaisa_title'] ?? '',
        ],
    ],
]);
