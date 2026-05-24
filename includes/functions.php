<?php
declare(strict_types=1);

require_once __DIR__ . '/providers.php';

function sbc_load_config(): array
{
    /** @var array $config */
    $config = require __DIR__ . '/config.php';
    return $config;
}


function sbc_substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
    return substr($value, $start, $length);
}

function sbc_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

function sbc_strtolower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function sbc_normalize_query(string $input): string
{
    $input = trim($input);
    $input = preg_replace('/\s+/u', ' ', $input) ?? '';
    return sbc_substr($input, 0, 120);
}

function sbc_slugify_domain_label(string $input): string
{
    $input = sbc_strtolower($input);
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
    return sbc_substr($input, 0, 63);
}

function sbc_domain_hint(string $domain): array
{
    $hasDns = false;
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
        'swissreg' => $config['urls']['swissreg_trademarks'],
        'zefix' => sprintf($config['urls']['zefix_search_with_query'], $encoded),
        'zefix_fallback' => $config['urls']['zefix_search'],
        'zefix_api_docs' => $config['urls']['zefix_api_docs'],
    ];
}

function sbc_score(array $domainHint, array $zefix): int
{
    $score = 20;
    if (($domainHint['available_hint'] ?? false) === true) {
        $score += 30;
    }

    if (($zefix['success'] ?? false) === true) {
        $matches = (int) ($zefix['matches'] ?? 0);
        if ($matches === 0) {
            $score += 35;
        } elseif ($matches <= 3) {
            $score += 10;
        } else {
            $score += 3;
        }
    } else {
        $score += 10; // official ZEFIX manual link still available
    }

    $score += 15; // Swissreg manual official check available
    return min(100, max(0, $score));
}

function sbc_check_business_name(string $input): array
{
    $config = sbc_load_config();
    $query = sbc_normalize_query($input);
    $slug = sbc_slugify_domain_label($query);

    if ($query === '' || $slug === '') {
        return ['ok' => false, 'error' => 'Please enter a valid business name candidate.'];
    }

    if (sbc_strlen($query) < 3) {
        return ['ok' => false, 'error' => 'Please enter at least 3 characters for ZEFIX search.'];
    }

    $domainHint = sbc_domain_hint($slug . '.ch');
    $urls = sbc_build_search_urls($query, $config);
    $zefix = sbc_zefix_api_search($query, $config);

    return [
        'ok' => true,
        'version' => $config['app_version'],
        'query' => $query,
        'normalized_domain_label' => $slug,
        'domain' => $domainHint,
        'swissreg' => [
            'status' => 'manual-check',
            'search_url' => $urls['swissreg'],
            'note' => 'Swissreg is linked as an official manual trade mark check. Similar marks may still create conflicts.',
        ],
        'zefix' => array_merge($zefix, [
            'search_url' => $urls['zefix'],
            'fallback_search_url' => $urls['zefix_fallback'],
            'api_docs_url' => $urls['zefix_api_docs'],
        ]),
        'official_links' => $urls,
        'score' => sbc_score($domainHint, $zefix),
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
