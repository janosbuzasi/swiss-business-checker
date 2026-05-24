<?php
declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$name = isset($_GET['name']) ? (string) $_GET['name'] : '';
$result = sbc_check_business_name($name);

sbc_json_response($result, $result['ok'] ? 200 : 400);
