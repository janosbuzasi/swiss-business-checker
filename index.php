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
    <meta name="description" content="Check Swiss business name candidates against .ch domain hints, Swissreg and live ZEFIX API results.">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="page">
    <section class="hero">
        <div class="badge">🇨🇭 V<?= sbc_h($config['app_version']) ?></div>
        <h1>Swiss Business Checker</h1>
        <p>Check Swiss business name candidates against domain hints, Swissreg trade mark search and live ZEFIX company register data.</p>

        <form class="checker-form" method="get" action="">
            <label for="name">Business name candidate</label>
            <div class="input-row">
                <input id="name" name="name" type="text" maxlength="120" placeholder="e.g. elefanten" value="<?= sbc_h($query) ?>" required>
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
                    <p>V2 score uses domain DNS hints, ZEFIX API status and Swissreg manual check availability.</p>
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
                    <p>Open the official Swissreg trade mark database and search for identical or similar marks.</p>
                    <p class="status manual">Manual check</p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['swissreg']['search_url']) ?>">Open Swissreg search</a>
                </article>

                <article class="card">
                    <h2>ZEFIX company register</h2>
                    <?php if (($result['zefix']['success'] ?? false) === true): ?>
                        <p class="status <?= ((int) $result['zefix']['matches'] === 0) ? 'good' : 'warn' ?>">
                            <?= (int) $result['zefix']['matches'] ?> live API match<?= ((int) $result['zefix']['matches'] === 1) ? '' : 'es' ?>
                        </p>
                    <?php else: ?>
                        <p class="status manual">API fallback / manual check</p>
                    <?php endif; ?>
                    <p><?= sbc_h((string) $result['zefix']['note']) ?></p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['zefix']['search_url']) ?>">Open ZEFIX with query</a>
                </article>
            </section>

            <?php if (($result['zefix']['success'] ?? false) === true && !empty($result['zefix']['results'])): ?>
                <section class="card full-width">
                    <h2>ZEFIX live results</h2>
                    <div class="result-list">
                        <?php foreach (array_slice($result['zefix']['results'], 0, (int) $config['zefix_api']['max_ui_results']) as $company): ?>
                            <article class="company-row">
                                <div>
                                    <strong><?= sbc_h((string) $company['name']) ?></strong>
                                    <p>
                                        <?= sbc_h((string) ($company['legal_form'] ?: $company['legal_form_short'] ?: 'Legal form unknown')) ?>
                                        <?php if (!empty($company['uid'])): ?> · <?= sbc_h((string) $company['uid']) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="company-meta">
                                    <span><?= sbc_h(trim((string) $company['legal_seat'] . ' ' . (empty($company['canton']) ? '' : '(' . $company['canton'] . ')'))) ?></span>
                                    <span><?= sbc_h((string) $company['status']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (($result['zefix']['success'] ?? false) !== true): ?>
                <section class="card full-width warn-box">
                    <h2>ZEFIX API note</h2>
                    <p><?= sbc_h((string) ($result['zefix']['error'] ?? 'Live API lookup was not available.')) ?></p>
                    <p>For hosts requiring authentication, set <code>ZEFIX_API_USERNAME</code> and <code>ZEFIX_API_PASSWORD</code> as environment variables.</p>
                </section>
            <?php endif; ?>

            <section class="card api-card full-width">
                <h2>JSON API</h2>
                <p>Use the same check as JSON:</p>
                <code><?= sbc_h('api.php?name=' . rawurlencode($result['query'])) ?></code>
                <button type="button" class="copy-btn" data-copy="<?= sbc_h('api.php?name=' . rawurlencode($result['query'])) ?>">Copy</button>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="card disclaimer full-width">
        <h2>Disclaimer</h2>
        <p>This tool is an initial research helper. It is not legal advice and does not replace official registrar, trade mark or commercial register checks.</p>
    </section>
</main>

<script src="assets/js/script.js"></script>
</body>
</html>
