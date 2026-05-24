<?php
declare(strict_types=1);

/**
 * Swiss Business Checker configuration.
 *
 * V1 intentionally avoids scraping Swissreg or ZEFIX.
 * It provides official search links and optional DNS-based domain hints.
 */

return [
    'app_name' => 'Swiss Business Checker',
    'app_version' => '1.0.1',

    // Supported domain suffixes for quick DNS hints.
    // .ch is the default Swiss business use case.
    'tlds' => ['ch'],

    // Official reference URLs.
    'urls' => [
        'nic_lookup' => 'https://www.nic.ch/whois/',
        'swissreg_trademarks' => 'https://www.swissreg.ch/database-client/search/query/trademarks',
        'zefix_search' => 'https://www.zefix.admin.ch/de/search/entity/welcome',
        'zefix_api_docs' => 'https://www.zefix.admin.ch/ZefixPublicREST/swagger-ui/index.html',
    ],

    // DNS timeout is controlled by PHP/system resolver; keep UI expectations clear.
    'domain_check_mode' => 'dns-hint',
];
