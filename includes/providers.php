<?php
declare(strict_types=1);

function sbc_zefix_api_search(string $query, array $config): array
{
    $apiConfig = $config['zefix_api'] ?? [];
    if (($apiConfig['enabled'] ?? true) !== true) {
        return [
            'status' => 'disabled',
            'success' => false,
            'matches' => null,
            'results' => [],
            'note' => 'ZEFIX API integration is disabled in config.php.',
        ];
    }

    $username = (string) ($apiConfig['username'] ?? '');
    $password = (string) ($apiConfig['password'] ?? '');
    if ($username === '' || $password === '') {
        return [
            'status' => 'credentials-required',
            'success' => false,
            'matches' => null,
            'results' => [],
            'note' => 'ZEFIX PublicREST live lookup requires Basic Auth credentials. Use the official ZEFIX button for manual verification.',
        ];
    }

    $endpoint = rtrim((string) $config['urls']['zefix_api_base'], '/') . '/api/v1/company/search';
    $payload = [
        'name' => $query,
        'activeOnly' => (bool) ($apiConfig['active_only'] ?? true),
    ];

    $response = sbc_http_post_json(
        $endpoint,
        $payload,
        $username,
        $password,
        (int) ($apiConfig['timeout_seconds'] ?? 6)
    );

    if (!$response['ok']) {
        return [
            'status' => 'api-unavailable',
            'success' => false,
            'matches' => null,
            'results' => [],
            'http_status' => $response['http_status'],
            'error' => $response['error'],
            'note' => 'Live ZEFIX API lookup failed. Use the official ZEFIX button for manual verification.',
        ];
    }

    $decoded = json_decode((string) $response['body'], true);
    if (!is_array($decoded)) {
        return [
            'status' => 'invalid-response',
            'success' => false,
            'matches' => null,
            'results' => [],
            'http_status' => $response['http_status'],
            'error' => 'ZEFIX API returned non-JSON or unexpected JSON.',
            'note' => 'Use the official ZEFIX button for manual verification.',
        ];
    }

    $results = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $legalForm = $entry['legalForm'] ?? [];
        $results[] = [
            'name' => (string) ($entry['name'] ?? ''),
            'uid' => (string) ($entry['uid'] ?? ''),
            'ehraid' => $entry['ehraid'] ?? null,
            'legal_seat' => (string) ($entry['legalSeat'] ?? ''),
            'canton' => (string) ($entry['canton'] ?? ''),
            'status' => (string) ($entry['status'] ?? ''),
            'sogc_date' => (string) ($entry['sogcDate'] ?? ''),
            'legal_form' => sbc_pick_translated_name($legalForm['name'] ?? null),
            'legal_form_short' => sbc_pick_translated_name($legalForm['shortName'] ?? null),
        ];
    }

    return [
        'status' => 'live-api',
        'success' => true,
        'matches' => count($results),
        'results' => $results,
        'http_status' => $response['http_status'],
        'note' => count($results) === 0
            ? 'No active ZEFIX company matches found by the live API search.'
            : 'Live ZEFIX company matches found. Review details manually before making decisions.',
    ];
}

function sbc_http_post_json(string $url, array $payload, string $username, string $password, int $timeout): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'http_status' => 0, 'body' => '', 'error' => 'Could not encode JSON payload.'];
    }

    if (function_exists('curl_init')) {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: swiss-business-checker/2.0',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($username !== '' || $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'ok' => $raw !== false && $status >= 200 && $status < 300,
            'http_status' => $status,
            'body' => $raw === false ? '' : (string) $raw,
            'error' => $raw === false ? $error : ($status >= 200 && $status < 300 ? '' : 'HTTP ' . $status),
        ];
    }

    $headers = "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: swiss-business-checker/2.0\r\n";
    if ($username !== '' || $password !== '') {
        $headers .= 'Authorization: Basic ' . base64_encode($username . ':' . $password) . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }

    return [
        'ok' => $raw !== false && $status >= 200 && $status < 300,
        'http_status' => $status,
        'body' => $raw === false ? '' : (string) $raw,
        'error' => $raw === false ? 'HTTP request failed.' : ($status >= 200 && $status < 300 ? '' : 'HTTP ' . $status),
    ];
}

function sbc_pick_translated_name(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (!is_array($value)) {
        return '';
    }
    foreach (['en', 'de', 'fr', 'it'] as $lang) {
        if (!empty($value[$lang]) && is_string($value[$lang])) {
            return $value[$lang];
        }
    }
    return '';
}
