<?php
declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$config = sbc_load_config();
$query = isset($_GET['name']) ? (string) $_GET['name'] : '';
$result = $query !== '' ? sbc_check_business_name($query) : null;

if (isset($_GET['api']) && $_GET['api'] === '1') {
    sbc_json_response($result ?? ['ok' => false, 'error' => 'Missing name parameter.'], $result && $result['ok'] ? 200 : 400);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= sbc_h($config['app_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Check Swiss business name candidates against .ch domain hints, Swissreg and ZEFIX.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="page">
    <section class="hero">
        <div class="badge">🇨🇭 V<?= sbc_h($config['app_version']) ?></div>
        <h1>Swiss Business Checker</h1>
        <p>Check Swiss business name candidates against domain hints, Swissreg trade mark search and ZEFIX company register search.</p>

        <form class="checker-form" method="get" action="">
            <label for="name">Business name candidate</label>
            <div class="input-row">
                <input id="name" name="name" type="text" maxlength="120" placeholder="e.g. werkmacher" value="<?= sbc_h($query) ?>" required>
                <button type="submit">Check</button>
            </div>
        </form>
    </section>

    <?php if (is_array($result)): ?>
        <?php if (!$result['ok']): ?>
            <section class="card error">
                <h2>Input error</h2>
                <p><?= sbc_h((string) $result['error']) ?></p>
            </section>
        <?php else: ?>
            <section class="results-grid">
                <article class="card score-card">
                    <h2>Candidate score</h2>
                    <div class="score"><?= (int) $result['score'] ?><span>/100</span></div>
                    <p>V1 score is conservative. Swissreg and ZEFIX remain manual checks.</p>
                </article>

                <article class="card">
                    <h2>.ch domain hint</h2>
                    <p class="candidate"><?= sbc_h($result['domain']['domain']) ?></p>
                    <p class="status <?= $result['domain']['available_hint'] ? 'good' : 'warn' ?>">
                        <?= $result['domain']['available_hint'] ? 'Possibly available' : 'DNS records found' ?>
                    </p>
                    <p><?= sbc_h($result['domain']['note']) ?></p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['official_links']['nic_lookup']) ?>">Open nic.ch lookup</a>
                </article>

                <article class="card">
                    <h2>Swissreg trade marks</h2>
                    <p>Search the official Swissreg trade mark database for identical or similar marks.</p>
                    <p class="status manual">Manual check</p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['swissreg']['search_url']) ?>">Open Swissreg search</a>
                </article>

                <article class="card">
                    <h2>ZEFIX company register</h2>
                    <p>Search the Swiss central business name index for existing companies.</p>
                    <p class="status manual">Manual check</p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['zefix']['search_url']) ?>">Open ZEFIX search</a>
                </article>
            </section>

            <section class="card api-card">
                <h2>JSON API</h2>
                <p>Use the same check as JSON:</p>
                <code><?= sbc_h('api.php?name=' . rawurlencode($result['query'])) ?></code>
                <button type="button" class="copy-btn" data-copy="<?= sbc_h('api.php?name=' . rawurlencode($result['query'])) ?>">Copy</button>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="card disclaimer">
        <h2>Disclaimer</h2>
        <p>This tool is an initial research helper. It is not legal advice and does not replace official registrar, trade mark or commercial register checks.</p>
    </section>
</main>

<script src="assets/js/script.js"></script>
</body>
</html>
