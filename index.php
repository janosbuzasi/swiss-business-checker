<?php
declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$config = sbc_load_config();
$query = isset($_GET['name']) ? (string) $_GET['name'] : '';

if (isset($_GET['api']) && $_GET['api'] === '1') {
    $rateLimit = sbc_rate_limit_check($config, $_SERVER['REMOTE_ADDR'] ?? 'cli');
    if (!$rateLimit['ok']) {
        sbc_json_response($rateLimit, 429);
    }

    $result = $query !== '' ? sbc_check_business_name($query) : null;
    sbc_json_response($result ?? ['ok' => false, 'error' => 'Missing name parameter.'], $result && $result['ok'] ? 200 : 400);
}

$result = $query !== '' ? sbc_check_business_name($query) : null;
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
                    <p>Confidence: <?= sbc_h((string) $result['confidence']) ?><?= !empty($result['cached']) ? ' · cached result' : '' ?></p>
                    <p>Score uses domain DNS hints and live ZEFIX API status. Swissreg remains a manual check.</p>
                </article>

                <article class="card">
                    <h2>.ch domain hint</h2>
                    <p class="candidate"><?= sbc_h($result['domain']['domain']) ?></p>
                    <p class="status <?= $result['domain']['available_hint'] ? 'good' : 'warn' ?>">
                        <?= $result['domain']['available_hint'] ? 'Possibly available' : 'DNS records found' ?>
                    </p>
                    <p><?= sbc_h($result['domain']['note']) ?></p>
                    <?php if (count($result['domain']['candidates'] ?? []) > 1): ?>
                        <p>Also check: <?= sbc_h(implode(', ', array_column($result['domain']['candidates'], 'domain'))) ?></p>
                    <?php endif; ?>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['official_links']['nic_lookup']) ?>">Open nic.ch lookup</a>
                </article>

                <article class="card">
                    <h2>Swissreg trade marks</h2>
                    <?php if (($result['swissreg']['success'] ?? false) === true): ?>
                        <?php
                            $swissregLight = (string) ($result['swissreg']['traffic_light'] ?? 'yellow');
                            $swissregClass = ['green' => 'good', 'yellow' => 'warn', 'red' => 'bad'][$swissregLight] ?? 'manual';
                            $swissregLabel = ['green' => 'No matches found', 'yellow' => 'Deleted/unclear entries found', 'red' => 'Active or pending entries found'][$swissregLight] ?? 'Manual check';
                        ?>
                        <p class="status <?= sbc_h($swissregClass) ?>"><?= sbc_h($swissregLabel) ?></p>
                        <p><?= (int) $result['swissreg']['matches'] ?> Swissreg match<?= ((int) $result['swissreg']['matches'] === 1) ? '' : 'es' ?></p>
                    <?php else: ?>
                        <p class="status manual">Live API inactive</p>
                    <?php endif; ?>
                    <p><?= sbc_h((string) $result['swissreg']['note']) ?></p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['swissreg']['search_url']) ?>">Open Swissreg CH-Marke search</a>
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

                <article class="card">
                    <h2>UID / CHE register</h2>
                    <?php
                        $uidLight = (string) ($result['uid']['traffic_light'] ?? 'manual');
                        $uidClass = ['green' => 'good', 'yellow' => 'warn', 'red' => 'bad'][$uidLight] ?? 'manual';
                        $uidLabel = ['green' => 'One UID found', 'yellow' => 'Multiple UIDs found', 'red' => 'No UID found'][$uidLight] ?? 'Manual check';
                    ?>
                    <p class="status <?= sbc_h($uidClass) ?>"><?= sbc_h($uidLabel) ?></p>
                    <?php if (($result['uid']['matches'] ?? null) !== null): ?>
                        <p><?= (int) $result['uid']['matches'] ?> UID match<?= ((int) $result['uid']['matches'] === 1) ? '' : 'es' ?></p>
                    <?php endif; ?>
                    <p><?= sbc_h((string) $result['uid']['note']) ?></p>
                    <a class="button-link" target="_blank" rel="noopener" href="<?= sbc_h($result['uid']['search_url']) ?>">Open UID register</a>
                </article>
            </section>

            <?php if (!empty($result['uid']['uids'])): ?>
                <section class="card full-width">
                    <h2>UID / CHE results</h2>
                    <div class="result-list">
                        <?php foreach ($result['uid']['uids'] as $uid): ?>
                            <article class="company-row">
                                <div>
                                    <strong><?= sbc_h((string) $uid['uid']) ?></strong>
                                    <p><?= sbc_h((string) ($uid['name'] ?: 'Company name unavailable')) ?></p>
                                </div>
                                <div class="company-meta">
                                    <span><?= sbc_h(trim((string) $uid['legal_seat'] . ' ' . (empty($uid['canton']) ? '' : '(' . $uid['canton'] . ')'))) ?></span>
                                    <a target="_blank" rel="noopener" href="<?= sbc_h((string) $uid['search_url']) ?>">Verify UID</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (($result['swissreg']['success'] ?? false) === true && !empty($result['swissreg']['results'])): ?>
                <section class="card full-width">
                    <h2>Swissreg live results</h2>
                    <div class="result-list">
                        <?php foreach (array_slice($result['swissreg']['results'], 0, (int) $config['swissreg_api']['max_ui_results']) as $trademark): ?>
                            <article class="company-row">
                                <div>
                                    <strong><?= sbc_h((string) ($trademark['name'] ?: 'Trademark name unavailable')) ?></strong>
                                    <p>
                                        <?= sbc_h((string) ($trademark['registration_number'] ?: 'Registration number unavailable')) ?>
                                        <?php if (!empty($trademark['owner'])): ?> · <?= sbc_h((string) $trademark['owner']) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="company-meta">
                                    <span class="status <?= (($trademark['status_kind'] ?? '') === 'active' || ($trademark['status_kind'] ?? '') === 'pending') ? 'bad' : ((($trademark['status_kind'] ?? '') === 'deleted') ? 'warn' : 'manual') ?>">
                                        <?= sbc_h((string) (($trademark['status_label'] ?? '') ?: ($trademark['status'] ?? '') ?: 'Status unclear')) ?>
                                    </span>
                                    <?php if (!empty($trademark['detail_url'])): ?>
                                        <a target="_blank" rel="noopener" href="<?= sbc_h((string) $trademark['detail_url']) ?>">Open entry</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

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
