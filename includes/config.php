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
    'app_version' => '2.0.0',

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

    // Optional ZEFIX API settings. Credentials can be injected via environment
    // variables on hosts where Basic Auth credentials are required.
    'zefix_api' => [
        'enabled' => filter_var(getenv('ZEFIX_API_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'username' => getenv('ZEFIX_API_USERNAME') ?: '',
        'password' => getenv('ZEFIX_API_PASSWORD') ?: '',
        'timeout_seconds' => 6,
        'active_only' => true,
        'max_ui_results' => 10,
    ],
];
