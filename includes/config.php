<?php
declare(strict_types=1);

/**
 * Swiss Business Checker configuration.
 *
 * V2 adds optional live ZEFIX PublicREST integration via server-side PHP.
 * Swissreg remains a manual official check because the public web UI does not
 * provide a stable unauthenticated query URL for simple link-based searches.
 */

return [
    'app_name' => 'Swiss Business Checker',
    'app_version' => '2.1.0',

    'tlds' => ['ch'],

    'urls' => [
        'nic_lookup' => 'https://www.nic.ch/whois/',
        'swissreg_trademarks' => 'https://www.swissreg.ch/database-client/search/query/trademarks',
        'zefix_search' => 'https://www.zefix.admin.ch/de/search/entity/welcome',
        'zefix_search_with_query' => 'https://www.zefix.admin.ch/de/search/entity/list?mainSearch=%s&searchTypeExact=true',
        'zefix_api_base' => 'https://www.zefix.admin.ch/ZefixPublicREST',
        'zefix_api_docs' => 'https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html',
    ],

    'domain_check_mode' => 'dns-hint',

    // Optional ZEFIX API settings. The official PublicREST API uses Basic Auth,
    // so live checks are disabled unless credentials are configured or the
    // feature is explicitly enabled.
    'zefix_api' => [
        'enabled' => filter_var(
            getenv('ZEFIX_API_ENABLED') ?: ((getenv('ZEFIX_API_USERNAME') && getenv('ZEFIX_API_PASSWORD')) ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'username' => getenv('ZEFIX_API_USERNAME') ?: '',
        'password' => getenv('ZEFIX_API_PASSWORD') ?: '',
        'timeout_seconds' => 6,
        'active_only' => true,
        'max_ui_results' => 10,
    ],

    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 900,
        'directory' => sys_get_temp_dir() . '/swiss-business-checker-cache',
    ],

    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 30,
        'window_seconds' => 300,
        'directory' => sys_get_temp_dir() . '/swiss-business-checker-rate-limit',
    ],
];
