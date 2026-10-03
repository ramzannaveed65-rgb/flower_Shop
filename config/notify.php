<?php
// =====================================================
// config/notify.php - WhatsApp alerts to the shop owner
//
// When a customer places an order (or sends an event booking),
// the shop gets the details on WhatsApp right away.
//
// Uses the free CallMeBot service. Set it up once in
// Admin -> Settings -> "WhatsApp order alerts".
// =====================================================

// "0300-1234567" or "923001234567" -> "+923001234567"
function whatsapp_number(string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', $phone);
    if ($p === '')                         return '';
    if (str_starts_with($p, '+'))          return $p;
    if (str_starts_with($p, '00'))         return '+' . substr($p, 2);
    if (str_starts_with($p, '0'))          return '+92' . substr($p, 1);
    return '+' . $p;
}

// Are the alerts switched on and filled in?
function whatsapp_ready(array $settings): bool
{
    return ($settings['whatsapp_alerts_enabled'] ?? '0') === '1'
        && whatsapp_number($settings['whatsapp_alert_phone'] ?? '') !== ''
        && trim($settings['whatsapp_alert_apikey'] ?? '') !== '';
}

// Send one WhatsApp message to the shop.
// Returns [true, ''] when it worked, or [false, 'what went wrong'].
function whatsapp_send(array $settings, string $text): array
{
    $phone  = whatsapp_number($settings['whatsapp_alert_phone'] ?? '');
    $apikey = trim($settings['whatsapp_alert_apikey'] ?? '');
    if ($phone === '' || $apikey === '') {
        return [false, 'Enter the WhatsApp number and the API key first.'];
    }

    $base = getenv('WHATSAPP_API_URL') ?: 'https://api.callmebot.com/whatsapp.php';
    $url  = $base . '?' . http_build_query(['phone' => $phone, 'text' => $text, 'apikey' => $apikey]);

    $body   = '';
    $status = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
        ]);
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
        if ($status === 0) {
            return [false, 'Could not reach the WhatsApp service' . ($error ? " ($error)" : '') . '.'];
        }
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return [false, 'Could not reach the WhatsApp service.'];
        }
        if (preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m)) {
            $status = (int) $m[1];
        }
    }

    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
    if ($status >= 200 && $status < 300 && stripos($plain, 'apikey is invalid') === false) {
        return [true, ''];
    }
    return [false, mb_substr($plain !== '' ? $plain : "The WhatsApp service answered with error $status.", 0, 180)];
}

// Send an alert and remember the result (shown in Admin -> Settings).
// Never stops the order: any problem is only written down.
function whatsapp_alert(PDO $pdo, array $settings, string $text): void
{
    if (!whatsapp_ready($settings)) {
        return;
    }
    try {
        [$ok, $error] = whatsapp_send($settings, $text);
        $note = date('j M, g:i A') . ($ok ? ': sent' : ': FAILED - ' . $error);
        save_setting($pdo, 'whatsapp_last_result', mb_substr($note, 0, 250));
        if (!$ok) {
            error_log('WhatsApp alert failed: ' . $error);
        }
    } catch (Throwable $e) {
        error_log('WhatsApp alert failed: ' . $e->getMessage());
    }
}

// Add or change one setting
function save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $key]);
    } else {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $value]);
    }
}

// The cities the shop delivers to, e.g. ['Islamabad', 'Rawalpindi']
function delivery_cities(array $settings): array
{
    $raw = $settings['delivery_cities'] ?? 'Islamabad, Rawalpindi';
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function money(float $amount): string
{
    return 'Rs ' . number_format($amount);
}

// ----- The message for a new order ($o comes from format_order) -----
function order_alert_text(array $o, array $customer, string $shop_name): string
{
    $pay = [
        'cod'       => 'Cash on Delivery',
        'nayapay'   => 'NayaPay - waiting for the payment proof',
        'easypaisa' => 'Easypaisa - waiting for the payment proof',
    ];

    $lines   = [];
    $lines[] = "*New order {$o['order_number']}*";
    $lines[] = $shop_name;
    $lines[] = '';
    $lines[] = "*Customer:* {$customer['name']} ({$customer['phone']})";
    $lines[] = "*Deliver to:* {$o['receiver_name']} ({$o['receiver_phone']})";
    $lines[] = "*Address:* {$o['address']}, {$o['city']}";
    $lines[] = '*Delivery:* ' . ($o['delivery_type'] === 'same_day' ? 'SAME DAY, today' : 'Standard')
             . ' - ' . date('D j M', strtotime($o['delivery_date']));
    $lines[] = '';
    $lines[] = '*Items:*';
    foreach ($o['items'] as $i) {
        $lines[] = "- {$i['quantity']} x {$i['name']} = " . money($i['line_total']);
    }
    $lines[] = 'Delivery charge: ' . money($o['delivery_charge']);
    $lines[] = '*Total: ' . money($o['total']) . '*';
    $lines[] = '*Payment:* ' . ($pay[$o['payment_method']] ?? $o['payment_method']);
    if (!empty($o['gift_message'])) {
        $lines[] = "*Gift message:* {$o['gift_message']}";
    }
    $lines[] = '';
    $lines[] = BASE_URL . 'admin/order_view.php?id=' . $o['id'];
    return implode("\n", $lines);
}

// ----- The message for a new event booking ($b comes from format_booking) -----
function booking_alert_text(array $b, string $shop_name): string
{
    $lines   = [];
    $lines[] = "*New event booking {$b['booking_number']}*";
    $lines[] = $shop_name;
    $lines[] = '';
    $lines[] = "*Event:* {$b['event_type']}" . ($b['package_name'] ? " - {$b['package_name']}" : ' (custom request)');
    $lines[] = '*Date:* ' . date('D j M Y', strtotime($b['event_date']))
             . ($b['event_time'] ? ' at ' . date('g:i A', strtotime($b['event_time'])) : '');
    $lines[] = "*Venue:* {$b['venue_address']}, {$b['city']}";
    if ($b['guests'] !== null) {
        $lines[] = "*Guests:* {$b['guests']}";
    }
    $lines[] = "*Contact:* {$b['contact_name']} ({$b['contact_phone']})";
    if (!empty($b['notes'])) {
        $lines[] = "*Notes:* {$b['notes']}";
    }
    $lines[] = '';
    $lines[] = BASE_URL . 'admin/booking_view.php?id=' . $b['id'];
    return implode("\n", $lines);
}
