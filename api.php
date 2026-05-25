<?php
declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$config = sbc_load_config();
$rateLimit = sbc_rate_limit_check($config, $_SERVER['REMOTE_ADDR'] ?? 'cli');
if (!$rateLimit['ok']) {
    sbc_json_response($rateLimit, 429);
}

$name = isset($_GET['name']) ? (string) $_GET['name'] : '';
$result = sbc_check_business_name($name);

sbc_json_response($result, $result['ok'] ? 200 : 400);
