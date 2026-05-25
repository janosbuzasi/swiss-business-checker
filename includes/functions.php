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

function sbc_idn_domain_label(string $input): string
{
    $input = sbc_strtolower(trim($input));
    $input = preg_replace('/\s+/u', '-', $input) ?? '';
    $input = trim($input, '-');

    if ($input === '' || !function_exists('idn_to_ascii')) {
        return '';
    }

    $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
    $encoded = idn_to_ascii($input, IDNA_DEFAULT, $variant);
    if (!is_string($encoded)) {
        return '';
    }

    $encoded = sbc_strtolower($encoded);
    if (!preg_match('/^[a-z0-9-]{1,63}$/', $encoded) || str_starts_with($encoded, '-') || str_ends_with($encoded, '-')) {
        return '';
    }

    return $encoded;
}

function sbc_domain_candidates(string $query): array
{
    $labels = array_values(array_unique(array_filter([
        sbc_slugify_domain_label($query),
        sbc_idn_domain_label($query),
    ])));

    return array_map(
        static fn (string $label): array => ['label' => $label, 'domain' => $label . '.ch'],
        $labels
    );
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

function sbc_cache_key(string $query): string
{
    return hash('sha256', sbc_strtolower($query));
}

function sbc_cache_get(array $config, string $query): ?array
{
    $cache = $config['cache'] ?? [];
    if (($cache['enabled'] ?? false) !== true) {
        return null;
    }

    $file = rtrim((string) ($cache['directory'] ?? ''), '/\\') . '/' . sbc_cache_key($query) . '.json';
    $ttl = (int) ($cache['ttl_seconds'] ?? 0);
    if ($ttl <= 0 || !is_file($file) || filemtime($file) < time() - $ttl) {
        return null;
    }

    $data = json_decode((string) @file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function sbc_cache_set(array $config, string $query, array $payload): void
{
    $cache = $config['cache'] ?? [];
    if (($cache['enabled'] ?? false) !== true) {
        return;
    }

    $directory = rtrim((string) ($cache['directory'] ?? ''), '/\\');
    if ($directory === '' || (!is_dir($directory) && !@mkdir($directory, 0775, true))) {
        return;
    }

    $file = $directory . '/' . sbc_cache_key($query) . '.json';
    @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sbc_rate_limit_check(array $config, string $identifier): array
{
    $rateLimit = $config['rate_limit'] ?? [];
    if (($rateLimit['enabled'] ?? false) !== true) {
        return ['ok' => true];
    }

    $maxRequests = max(1, (int) ($rateLimit['max_requests'] ?? 30));
    $windowSeconds = max(1, (int) ($rateLimit['window_seconds'] ?? 300));
    $directory = rtrim((string) ($rateLimit['directory'] ?? ''), '/\\');
    if ($directory === '' || (!is_dir($directory) && !@mkdir($directory, 0775, true))) {
        return ['ok' => true];
    }

    $file = $directory . '/' . hash('sha256', $identifier) . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = array_values(array_filter($decoded, static fn ($ts): bool => is_int($ts) && $ts > $now - $windowSeconds));
        }
    }

    if (count($hits) >= $maxRequests) {
        return [
            'ok' => false,
            'error' => 'Too many API requests. Please try again shortly.',
            'retry_after_seconds' => max(1, ($hits[0] + $windowSeconds) - $now),
        ];
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return ['ok' => true];
}

function sbc_build_search_urls(string $query, array $config): array
{
    $encoded = rawurlencode($query);
    return [
        'nic_lookup' => $config['urls']['nic_lookup'],
        'swissreg' => sprintf($config['urls']['swissreg_ch_trademarks_with_query'], $encoded),
        'swissreg_fallback' => $config['urls']['swissreg_trademarks'],
        'swissreg_api_docs' => $config['urls']['swissreg_api_docs'],
        'zefix' => sprintf($config['urls']['zefix_search_with_query'], $encoded),
        'zefix_fallback' => $config['urls']['zefix_search'],
        'zefix_api_docs' => $config['urls']['zefix_api_docs'],
    ];
}

function sbc_score(array $domainHint, array $swissreg, array $zefix): int
{
    $score = 20;
    if (($domainHint['available_hint'] ?? false) === true) {
        $score += 30;
    }

    if (($swissreg['success'] ?? false) === true) {
        $trafficLight = (string) ($swissreg['traffic_light'] ?? 'yellow');
        if ($trafficLight === 'green') {
            $score += 30;
        } elseif ($trafficLight === 'yellow') {
            $score += 8;
        }
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
    }

    return min(100, max(0, $score));
}

function sbc_confidence(array $swissreg, array $zefix): string
{
    if (($swissreg['success'] ?? false) === true && ($zefix['success'] ?? false) === true) {
        return 'high';
    }
    if (($swissreg['success'] ?? false) === true || ($zefix['success'] ?? false) === true) {
        return 'medium';
    }
    return 'low';
}

function sbc_check_business_name(string $input): array
{
    $config = sbc_load_config();
    $query = sbc_normalize_query($input);
    $domainCandidates = sbc_domain_candidates($query);
    $slug = (string) ($domainCandidates[0]['label'] ?? '');

    if ($query === '' || $slug === '') {
        return ['ok' => false, 'error' => 'Please enter a valid business name candidate.'];
    }

    if (sbc_strlen($query) < 3) {
        return ['ok' => false, 'error' => 'Please enter at least 3 characters for ZEFIX search.'];
    }

    $cached = sbc_cache_get($config, $query);
    if (is_array($cached)) {
        $cached['cached'] = true;
        return $cached;
    }

    $domainHint = sbc_domain_hint((string) $domainCandidates[0]['domain']);
    $domainHint['candidates'] = $domainCandidates;
    $urls = sbc_build_search_urls($query, $config);
    $swissreg = sbc_swissreg_api_search($query, $config);
    $zefix = sbc_zefix_api_search($query, $config);

    $result = [
        'ok' => true,
        'version' => $config['app_version'],
        'query' => $query,
        'normalized_domain_label' => $slug,
        'domain' => $domainHint,
        'swissreg' => array_merge($swissreg, [
            'search_url' => $urls['swissreg'],
            'fallback_search_url' => $urls['swissreg_fallback'],
            'api_docs_url' => $urls['swissreg_api_docs'],
        ]),
        'zefix' => array_merge($zefix, [
            'search_url' => $urls['zefix'],
            'fallback_search_url' => $urls['zefix_fallback'],
            'api_docs_url' => $urls['zefix_api_docs'],
        ]),
        'official_links' => $urls,
        'score' => sbc_score($domainHint, $swissreg, $zefix),
        'confidence' => sbc_confidence($swissreg, $zefix),
        'cached' => false,
        'disclaimer' => 'This tool is an initial technical/name research helper, not legal advice.',
    ];

    sbc_cache_set($config, $query, $result);
    return $result;
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
