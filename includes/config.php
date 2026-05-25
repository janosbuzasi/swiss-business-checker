<?php
declare(strict_types=1);

/**
 * Swiss Business Checker configuration.
 *
 * V2 adds optional live ZEFIX PublicREST integration via server-side PHP.
 * Swissreg remains a manual official check because the public web UI does not
 * provide a stable unauthenticated query URL for simple link-based searches.
 */

$config = [
    'app_name' => 'Swiss Business Checker',
    'app_version' => '2.2.0',

    'tlds' => ['ch'],

    'urls' => [
        'nic_lookup' => 'https://www.nic.ch/whois/',
        'swissreg_trademarks' => 'https://www.swissreg.ch/database-client/search/query/trademarks',
        'swissreg_ch_trademarks_with_query' => 'https://www.swissreg.ch/database-client/search/query/chmarke?q=%s',
        'swissreg_api_base' => 'https://www.swissreg.ch/public/api/v1',
        'swissreg_token_endpoint' => 'https://idp.ipi.ch/auth/realms/egov/protocol/openid-connect/token',
        'swissreg_api_docs' => 'https://www.swissreg.ch/public/apidocs/singlehtml/index.html',
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

    'swissreg_api' => [
        'enabled' => filter_var(
            getenv('SWISSREG_API_ENABLED') ?: ((getenv('SWISSREG_API_REFRESH_TOKEN') || (getenv('SWISSREG_API_USERNAME') && getenv('SWISSREG_API_PASSWORD'))) ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'client_id' => getenv('SWISSREG_API_CLIENT_ID') ?: 'datadelivery-api-client',
        'username' => getenv('SWISSREG_API_USERNAME') ?: '',
        'password' => getenv('SWISSREG_API_PASSWORD') ?: '',
        'refresh_token' => getenv('SWISSREG_API_REFRESH_TOKEN') ?: '',
        'timeout_seconds' => 8,
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

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    /** @var array $localConfig */
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $mergeConfig = static function (array $base, array $override) use (&$mergeConfig): array {
            foreach ($override as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = $mergeConfig($base[$key], $value);
                    continue;
                }
                $base[$key] = $value;
            }
            return $base;
        };

        $config = $mergeConfig($config, $localConfig);
    }
}

return $config;
