<?php
declare(strict_types=1);

function sbc_load_config(): array
{
    /** @var array $config */
    $config = require __DIR__ . '/config.php';
    return $config;
}

function sbc_normalize_query(string $input): string
{
    $input = trim($input);
    $input = preg_replace('/\s+/u', ' ', $input) ?? '';
    return mb_substr($input, 0, 120);
}

function sbc_slugify_domain_label(string $input): string
{
    $input = mb_strtolower($input, 'UTF-8');

    // Basic transliteration fallback. idn_to_ascii would be better for full IDN support,
    // but this keeps V1 dependency-free on shared hosting.
    $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'à' => 'a', 'á' => 'a', 'â' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ç' => 'c', 'ß' => 'ss',
    ];
    $input = strtr($input, $map);
    $input = preg_replace('/[^a-z0-9]+/', '-', $input) ?? '';
    $input = trim($input, '-');
    $input = preg_replace('/-+/', '-', $input) ?? '';

    return mb_substr($input, 0, 63);
}

function sbc_domain_hint(string $domain): array
{
    $hasDns = false;

    // dns_get_record can be disabled on some shared hosts. Fall back gracefully.
    if (function_exists('dns_get_record')) {
        $records = @dns_get_record($domain, DNS_A + DNS_AAAA + DNS_CNAME + DNS_NS + DNS_MX);
        $hasDns = is_array($records) && count($records) > 0;
    } else {
        $hasDns = checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA') || checkdnsrr($domain, 'NS');
    }

    return [
        'domain' => $domain,
        'mode' => 'dns-hint',
        'has_dns_records' => $hasDns,
        'available_hint' => !$hasDns,
        'status' => $hasDns ? 'possibly-registered-or-active' : 'possibly-available',
        'note' => 'DNS lookup is only a hint. Final availability must be checked with nic.ch or a registrar.',
    ];
}

function sbc_build_search_urls(string $query, array $config): array
{
    $encoded = rawurlencode($query);

    return [
        'nic_lookup' => $config['urls']['nic_lookup'],
        'swissreg' => $config['urls']['swissreg_trademarks'] . '?queryString=' . $encoded,
        // ZEFIX web UI is more stable as official entry page; query deep-links can fail due to UI/session state.
        'zefix' => $config['urls']['zefix_search'],
        'zefix_api_docs' => $config['urls']['zefix_api_docs'],
    ];
}

function sbc_score(array $domainHint): int
{
    // V1 score is deliberately conservative because Swissreg/ZEFIX are manual link-outs.
    $score = 30; // base for valid query
    if (($domainHint['available_hint'] ?? false) === true) {
        $score += 40;
    }
    $score += 15; // Swissreg official link provided
    $score += 15; // ZEFIX official link provided

    return min(100, max(0, $score));
}

function sbc_check_business_name(string $input): array
{
    $config = sbc_load_config();
    $query = sbc_normalize_query($input);
    $slug = sbc_slugify_domain_label($query);

    if ($query === '' || $slug === '') {
        return [
            'ok' => false,
            'error' => 'Please enter a valid business name candidate.',
        ];
    }

    $domain = $slug . '.ch';
    $domainHint = sbc_domain_hint($domain);
    $urls = sbc_build_search_urls($query, $config);

    return [
        'ok' => true,
        'query' => $query,
        'normalized_domain_label' => $slug,
        'domain' => $domainHint,
        'swissreg' => [
            'status' => 'manual-check',
            'search_url' => $urls['swissreg'],
            'note' => 'Swissreg should be checked manually. Similar trade marks may still create conflicts.',
        ],
        'zefix' => [
            'status' => 'manual-check',
            'search_url' => $urls['zefix'],
            'api_docs_url' => $urls['zefix_api_docs'],
            'note' => 'ZEFIX should be checked manually via the official search page. PublicREST integration can be added in a later version.',
        ],
        'official_links' => $urls,
        'score' => sbc_score($domainHint),
        'disclaimer' => 'This tool is an initial technical/name research helper, not legal advice.',
    ];
}

function sbc_json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sbc_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
