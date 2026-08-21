<?php
/**
 * Shared SMS Gateway Service & Automated Notification Helper.
 * Supports Notify.lk, Textware, Dialog, Mobitel, Twilio, & Custom HTTP GET/POST APIs.
 */

if (!function_exists('normalize_sri_lankan_phone')) {
    function normalize_sri_lankan_phone(string $phone): string
    {
        // Strip spaces, dashes, parentheses, + sign
        $clean = preg_replace('/[^\d]/', '', $phone);

        // If starts with 0 (e.g., 0771234567), convert to 94771234567
        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            $clean = '94' . substr($clean, 1);
        }

        // If starts with 7 (e.g., 771234567), prepend 94
        if (strlen($clean) === 9 && str_starts_with($clean, '7')) {
            $clean = '94' . $clean;
        }

        return $clean;
    }
}

if (!function_exists('send_sms')) {
    function send_sms(string $to_phone, string $message): array
    {
        global $pdo;

        // 1. Fetch SMS Settings
        $sms_settings = [];
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'sms_%' OR setting_key = 'shop_name'");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $sms_settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Throwable $e) {}
        }

        $enabled = ($sms_settings['sms_enabled'] ?? '1') === '1' || ($sms_settings['sms_enabled'] ?? '') === 'true';
        if (!$enabled) {
            return ['success' => false, 'error' => 'SMS Gateway is currently disabled in System Settings.'];
        }

        $api_key = trim($sms_settings['sms_api_key'] ?? '');
        $api_token = trim($sms_settings['sms_api_token'] ?? ''); // User ID for Notify.lk or Auth Token for Twilio
        $sender_id = trim($sms_settings['sms_sender_id'] ?? 'NotifyDEMO');
        $provider = trim($sms_settings['sms_provider'] ?? 'notify_lk');
        $custom_url = trim($sms_settings['sms_api_url'] ?? '');

        if ($api_key === '') {
            return ['success' => false, 'error' => 'SMS API Key is missing. Please configure SMS API Key in System Settings.'];
        }

        $to_normalized = normalize_sri_lankan_phone($to_phone);
        if ($to_normalized === '') {
            return ['success' => false, 'error' => 'Invalid or empty customer phone number.'];
        }

        $encoded_msg = urlencode($message);

        try {
            // 2. Dispatch based on Provider
            if ($provider === 'smsapi_lk' || str_contains(strtolower($custom_url), 'smsapi.lk')) {
                // SMSAPI.lk Official API v3 (https://dashboard.smsapi.lk/developers/docs)
                $url = trim($custom_url);
                if (empty($url) || !str_contains($url, '/sms/send')) {
                    $url = 'https://dashboard.smsapi.lk/api/v3/sms/send';
                }

                $payload = json_encode([
                    'recipient' => $to_normalized,
                    'sender_id' => $sender_id ?: 'SMSAPI',
                    'type'      => 'plain',
                    'message'   => $message,
                ]);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $api_key,
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $response_body = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    return ['success' => false, 'error' => 'SMSAPI.lk Connection Error: ' . $curl_error];
                }

                $json = json_decode($response_body, true);
                if (is_array($json)) {
                    $st = strtolower((string)($json['status'] ?? ''));
                    $msg = $json['message'] ?? $json['error'] ?? '';

                    if ($st === 'success' || $st === '200' || $st === 'true' || $st === 'ok') {
                        return ['success' => true, 'response' => $json];
                    }
                    if (isset($json['message_id']) || isset($json['data']) || isset($json['sms_id']) || isset($json['id'])) {
                        return ['success' => true, 'response' => $json];
                    }
                    return ['success' => false, 'error' => 'SMSAPI.lk Error: ' . ($msg ?: $response_body)];
                }

                if ($http_code >= 200 && $http_code < 300) {
                    return ['success' => true, 'response' => $response_body];
                }

                return ['success' => false, 'error' => 'SMSAPI.lk HTTP ' . $http_code . ': ' . $response_body];

            } elseif ($provider === 'notify_lk' || str_contains(strtolower($custom_url), 'notify.lk')) {
                // Notify.lk API
                $url = 'https://app.notify.lk/api/v1/send';
                $post_data = [
                    'user_id'   => $api_token ?: $api_key,
                    'api_key'   => $api_key,
                    'sender_id' => $sender_id ?: 'NotifyDEMO',
                    'to'        => $to_normalized,
                    'message'   => $message,
                ];

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($post_data),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $response_body = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    return ['success' => false, 'error' => 'Curl failure: ' . $curl_error];
                }

                $json = json_decode($response_body, true);
                if (isset($json['status']) && strtolower((string)$json['status']) === 'success') {
                    return ['success' => true, 'response' => $json];
                }
                return ['success' => false, 'error' => $json['message'] ?? $response_body ?: 'Notify.lk API request failed.'];

            } elseif ($custom_url !== '') {
                // Custom HTTP GET / POST Endpoint with Template Placeholders
                $target_url = str_replace(
                    ['{api_key}', '{api_token}', '{sender_id}', '{to}', '{message}'],
                    [urlencode($api_key), urlencode($api_token), urlencode($sender_id), urlencode($to_normalized), $encoded_msg],
                    $custom_url
                );

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $target_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $response_body = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    return ['success' => false, 'error' => 'HTTP request failed: ' . $curl_error];
                }

                return ['success' => true, 'response' => $response_body];
            } else {
                // Generic POST Fallback
                $url = 'https://app.notify.lk/api/v1/send';
                $post_data = [
                    'user_id'   => $api_token ?: $api_key,
                    'api_key'   => $api_key,
                    'sender_id' => $sender_id ?: 'NotifyDEMO',
                    'to'        => $to_normalized,
                    'message'   => $message,
                ];

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($post_data),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $response_body = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    return ['success' => false, 'error' => $curl_error];
                }

                return ['success' => true, 'response' => $response_body];
            }
        } catch (Throwable $ex) {
            return ['success' => false, 'error' => 'SMS Sending Exception: ' . $ex->getMessage()];
        }
    }
}

if (!function_exists('send_repair_ticket_sms')) {
    function send_repair_ticket_sms(array $job, array $customer): array
    {
        global $pdo;

        if (empty($customer['phone'])) {
            return ['success' => false, 'error' => 'Customer has no phone number on record.'];
        }

        // Fetch settings
        $settings = [];
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('shop_name', 'sms_repair_template', 'sms_enabled')");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Throwable $e) {}
        }

        $enabled = ($settings['sms_enabled'] ?? '1') === '1' || ($settings['sms_enabled'] ?? '') === 'true';
        if (!$enabled) {
            return ['success' => false, 'error' => 'SMS notifications disabled.'];
        }

        $shop_name = $settings['shop_name'] ?? 'TechShop';
        $ticket_no = $job['ticket_no'] ?? ('RPR-' . ($job['id'] ?? '0'));
        $cust_name = $customer['name'] ?? 'Customer';
        $device = trim(($job['device_brand'] ?? '') . ' ' . ($job['device_model'] ?? ''));
        if (empty($device)) {
            $device = $job['device_type'] ?? 'Device';
        }

        $base_url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $public_token = $job['public_token'] ?? $ticket_no;
        $track_url = $base_url . '/track.php?ticket=' . urlencode($public_token);

        $template = $settings['sms_repair_template'] ?? "Dear {customer_name}, your repair ticket {ticket_no} ({device}) has been created at {shop_name}. Track live progress: {track_url}";

        $message = str_replace(
            ['{customer_name}', '{ticket_no}', '{device}', '{shop_name}', '{track_url}'],
            [$cust_name, $ticket_no, $device, $shop_name, $track_url],
            $template
        );

        return send_sms($customer['phone'], $message);
    }
}
