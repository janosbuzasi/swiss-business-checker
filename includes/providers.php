<?php
declare(strict_types=1);

function sbc_swissreg_api_search(string $query, array $config): array
{
    $apiConfig = $config['swissreg_api'] ?? [];
    if (($apiConfig['enabled'] ?? false) !== true) {
        return [
            'status' => 'manual-check',
            'success' => false,
            'matches' => null,
            'results' => [],
            'traffic_light' => 'manual',
            'note' => 'Swissreg API integration is disabled. Use the official Swissreg button for manual verification.',
        ];
    }

    $token = sbc_swissreg_access_token($config, $apiConfig);
    if (!$token['ok']) {
        return [
            'status' => 'credentials-required',
            'success' => false,
            'matches' => null,
            'results' => [],
            'traffic_light' => 'manual',
            'error' => $token['error'],
            'note' => 'Swissreg API lookup requires valid IPI datadelivery credentials.',
        ];
    }

    $endpoint = rtrim((string) $config['urls']['swissreg_api_base'], '/');
    $xml = sbc_swissreg_search_xml($query);
    $response = sbc_http_post_xml(
        $endpoint,
        $xml,
        (string) $token['access_token'],
        (int) ($apiConfig['timeout_seconds'] ?? 8)
    );

    if (!$response['ok']) {
        return [
            'status' => 'api-unavailable',
            'success' => false,
            'matches' => null,
            'results' => [],
            'traffic_light' => 'manual',
            'http_status' => $response['http_status'],
            'error' => $response['error'],
            'note' => 'Live Swissreg API lookup failed. Use the official Swissreg button for manual verification.',
        ];
    }

    $results = sbc_parse_swissreg_results((string) $response['body'], (int) ($apiConfig['max_ui_results'] ?? 10));
    $trafficLight = sbc_swissreg_traffic_light($results);

    return [
        'status' => 'live-api',
        'success' => true,
        'matches' => count($results),
        'results' => $results,
        'traffic_light' => $trafficLight,
        'http_status' => $response['http_status'],
        'note' => sbc_swissreg_note($trafficLight, count($results)),
    ];
}

function sbc_swissreg_access_token(array $config, array $apiConfig): array
{
    $clientId = (string) ($apiConfig['client_id'] ?? 'datadelivery-api-client');
    $refreshToken = (string) ($apiConfig['refresh_token'] ?? '');
    $username = (string) ($apiConfig['username'] ?? '');
    $password = (string) ($apiConfig['password'] ?? '');

    if ($refreshToken !== '') {
        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ];
    } elseif ($username !== '' && $password !== '') {
        $payload = [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'username' => $username,
            'password' => $password,
        ];
    } else {
        return ['ok' => false, 'error' => 'Missing SWISSREG_API credentials.'];
    }

    $response = sbc_http_post_form(
        (string) $config['urls']['swissreg_token_endpoint'],
        $payload,
        (int) ($apiConfig['timeout_seconds'] ?? 8)
    );

    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error']];
    }

    $decoded = json_decode((string) $response['body'], true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        return ['ok' => false, 'error' => 'Swissreg token endpoint returned no access_token.'];
    }

    return ['ok' => true, 'access_token' => (string) $decoded['access_token']];
}

function sbc_swissreg_search_xml(string $query): string
{
    $safeQuery = htmlspecialchars($query, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ApiRequest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="urn:ige:schema:xsd:datadeliverycore-1.0.0"
    xmlns:tmk="urn:ige:schema:xsd:datadeliverytrademark-1.0.0">
  <Action type="TrademarkSearch">
    <tmk:TrademarkSearchRequest xmlns="urn:ige:schema:xsd:datadeliverycommon-1.0.0">
      <Representation details="Maximal" images="Link" strictness="Strict" itemBags="false">
        <Resource role="item" action="Embed"/>
      </Representation>
      <Page size="10"/>
      <Query>
        <tmk:WordElement>$safeQuery</tmk:WordElement>
      </Query>
      <Sort>
        <tmk:RegistrationDateSort>Descending</tmk:RegistrationDateSort>
        <LastUpdateSort>Descending</LastUpdateSort>
      </Sort>
    </tmk:TrademarkSearchRequest>
  </Action>
</ApiRequest>
XML;
}

function sbc_parse_swissreg_results(string $xml, int $limit): array
{
    if (!function_exists('simplexml_load_string')) {
        return [];
    }

    $root = @simplexml_load_string($xml);
    if (!$root instanceof SimpleXMLElement) {
        return [];
    }

    $items = [];
    sbc_collect_swissreg_items($root, $items, $limit);
    if ($items === []) {
        $fields = [];
        sbc_flatten_xml_fields($root, $fields);
        if (sbc_swissreg_has_trademark_fields($fields)) {
            $items[] = sbc_swissreg_item_from_fields($fields);
        }
    }
    return array_slice(sbc_unique_swissreg_items($items), 0, max(1, $limit));
}

function sbc_unique_swissreg_items(array $items): array
{
    $seen = [];
    $unique = [];
    foreach ($items as $item) {
        $key = ($item['registration_number'] ?? '') . '|' . ($item['name'] ?? '');
        if ($key === '|' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $item;
    }
    return $unique;
}

function sbc_collect_swissreg_items(SimpleXMLElement $node, array &$items, int $limit): void
{
    if (count($items) >= $limit) {
        return;
    }

    $nodeName = sbc_xml_local_name($node->getName());
    $fields = [];
    sbc_flatten_xml_fields($node, $fields);
    if (in_array($nodeName, ['Item', 'Trademark', 'TrademarkApplication', 'SearchResult', 'Resource'], true) && sbc_swissreg_has_trademark_fields($fields)) {
        $item = sbc_swissreg_item_from_fields($fields);
        if ($item['name'] !== '' || $item['registration_number'] !== '') {
            $items[] = $item;
        }
    }

    foreach ($node->children() as $child) {
        sbc_collect_swissreg_items($child, $items, $limit);
    }
}

function sbc_flatten_xml_fields(SimpleXMLElement $node, array &$fields): void
{
    foreach ($node->attributes() as $name => $value) {
        $key = sbc_xml_local_name((string) $name);
        if (!isset($fields[$key])) {
            $fields[$key] = trim((string) $value);
        }
    }

    foreach ($node->children() as $child) {
        $name = sbc_xml_local_name($child->getName());
        $text = trim((string) $child);
        if ($text !== '' && !isset($fields[$name])) {
            $fields[$name] = $text;
        }
        sbc_flatten_xml_fields($child, $fields);
    }
}

function sbc_xml_local_name(string $name): string
{
    $parts = explode(':', $name);
    return (string) end($parts);
}

function sbc_swissreg_has_trademark_fields(array $fields): bool
{
    foreach (['WordElement', 'Title', 'MarkVerbalElementText', 'TradeMarkNumber', 'ApplicationNumber', 'IPRightStatus', 'IPRightPhase'] as $key) {
        if (!empty($fields[$key])) {
            return true;
        }
    }
    return false;
}

function sbc_swissreg_item_from_fields(array $fields): array
{
    $name = sbc_pick_first($fields, ['WordElement', 'Title', 'MarkVerbalElementText', 'VerbalElementText', 'MarkDescriptionText', 'Name']);
    $registrationNumber = sbc_pick_first($fields, ['RegistrationNumber', 'TradeMarkNumber', 'TrademarkNumber', 'TrademarkID', 'ApplicationNumber', 'IPRightNumber', 'id']);
    $status = sbc_pick_first($fields, ['IPRightStatus', 'IPRightPhase', 'TradeMarkStatus', 'TrademarkStatus', 'RegistrationStatus', 'LegalStatus', 'Status']);
    $owner = sbc_pick_first($fields, ['HolderName', 'OwnerName', 'ApplicantName', 'PartyName']);
    $classes = sbc_pick_first($fields, ['NiceClassification', 'ClassNumber', 'GoodsServicesClass']);
    $detailUrl = $registrationNumber !== ''
        ? 'https://www.swissreg.ch/database-client/register/detail/trademark/' . rawurlencode($registrationNumber)
        : '';

    return [
        'name' => $name,
        'registration_number' => $registrationNumber,
        'status' => $status,
        'status_kind' => sbc_swissreg_status_kind($status),
        'status_label' => sbc_swissreg_status_label($status),
        'owner' => $owner,
        'classes' => $classes,
        'detail_url' => $detailUrl,
    ];
}

function sbc_pick_first(array $fields, array $keys): string
{
    foreach ($keys as $key) {
        if (!empty($fields[$key])) {
            return (string) $fields[$key];
        }
    }
    return '';
}

function sbc_swissreg_status_kind(string $status): string
{
    $normalized = sbc_strtolower($status);
    if ($normalized === '') {
        return 'unknown';
    }
    if (str_contains($normalized, 'deleted') || str_contains($normalized, 'geloscht') || str_contains($normalized, 'gelöscht') || str_contains($normalized, 'cancelled') || str_contains($normalized, 'canceled') || str_contains($normalized, 'expired')) {
        return 'deleted';
    }
    if (str_contains($normalized, 'registered') || str_contains($normalized, 'eingetragen') || str_contains($normalized, 'active') || str_contains($normalized, 'in force')) {
        return 'active';
    }
    if (str_contains($normalized, 'pending') || str_contains($normalized, 'initiated') || str_contains($normalized, 'application') || str_contains($normalized, 'hängig') || str_contains($normalized, 'haengig')) {
        return 'pending';
    }
    return 'unknown';
}

function sbc_swissreg_status_label(string $status): string
{
    $kind = sbc_swissreg_status_kind($status);
    if ($kind === 'deleted') {
        return 'Gelöscht';
    }
    if ($kind === 'active') {
        return 'Eingetragen';
    }
    if ($kind === 'pending') {
        return 'Hängig';
    }
    return $status;
}

function sbc_swissreg_traffic_light(array $results): string
{
    if (count($results) === 0) {
        return 'green';
    }

    foreach ($results as $result) {
        if (($result['status_kind'] ?? '') === 'active' || ($result['status_kind'] ?? '') === 'pending') {
            return 'red';
        }
    }

    foreach ($results as $result) {
        if (($result['status_kind'] ?? '') === 'deleted') {
            return 'yellow';
        }
    }

    return 'yellow';
}

function sbc_swissreg_note(string $trafficLight, int $matches): string
{
    if ($trafficLight === 'green') {
        return 'No Swissreg trademark matches found by the live API search.';
    }
    if ($trafficLight === 'red') {
        return 'Swissreg found active or pending trademark entries. Review details before using the name.';
    }
    if ($matches > 0) {
        return 'Swissreg found trademark entries, but they appear deleted or unclear. Deleted entries can still matter for review.';
    }
    return 'Swissreg live lookup completed.';
}

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

function sbc_http_post_form(string $url, array $payload, int $timeout): array
{
    return sbc_http_post_body(
        $url,
        http_build_query($payload),
        [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'User-Agent: swiss-business-checker/2.2',
        ],
        $timeout
    );
}

function sbc_http_post_xml(string $url, string $body, string $bearerToken, int $timeout): array
{
    return sbc_http_post_body(
        $url,
        $body,
        [
            'Content-Type: application/xml',
            'Accept: application/xml',
            'Authorization: Bearer ' . $bearerToken,
            'User-Agent: swiss-business-checker/2.2',
        ],
        $timeout
    );
}

function sbc_http_post_body(string $url, string $body, array $headers, int $timeout): array
{
    if (function_exists('curl_init')) {
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

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
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
