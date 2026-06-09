<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';
require_once __DIR__ . '/../lib/NwbImportService.php';
require_once __DIR__ . '/auth.php';

$error = null;
$stats = null;
$messages = [];
$flash = null;

adminHandleAuthPost();

if (!adminIsLoggedIn()) {
    adminRenderAuthPage('NWB beheer');
    exit;
}

$flash = adminFlash();

try {
    $db = (new Database())->connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
        adminRequireCsrf();
        $collection = (string)($_POST['collection'] ?? 'all');
        $maxPages = max(1, min((int)($_POST['max_pages'] ?? 1), 10));
        $service = new NwbImportService($db);
        $result = $collection === 'all'
            ? $service->importAll(1000, $maxPages)
            : [$collection => $service->importCollection($collection, 1000, $maxPages)];
        $messages[] = 'Import uitgevoerd: ' . json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    $repository = new HectometerRepository($db);
    $stats = $repository->stats();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NWB beheer</title>
    <link rel="stylesheet" href="/admin/css/admin-style.css">
</head>
<body>
    <main class="admin-container">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Hectometerpaaltjes</p>
                <h1>NWB beheer</h1>
            </div>
            <div class="admin-toolbar">
                <a class="button button-light" href="/">Website</a>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="button button-light" type="submit">Uitloggen</button>
                </form>
            </div>
        </header>

        <?php if ($flash): ?>
            <section class="admin-card <?= h($flash['type'] ?? 'success') ?>">
                <p><?= h($flash['message'] ?? '') ?></p>
            </section>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <section class="admin-card success">
                <p><?= h($message) ?></p>
            </section>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <section class="admin-card alert">
                <h2>Database niet bereikbaar</h2>
                <p><?= h($error) ?></p>
                <a class="button" href="/admin/setup.php">Setup openen</a>
            </section>
        <?php else: ?>
            <section class="stats-grid">
                <article class="admin-card stat-card">
                    <span>Hectopunten</span>
                    <strong><?= number_format((int)$stats['hectopunten'], 0, ',', '.') ?></strong>
                </article>
                <article class="admin-card stat-card">
                    <span>Wegvakken</span>
                    <strong><?= number_format((int)$stats['wegvakken'], 0, ',', '.') ?></strong>
                </article>
                <article class="admin-card stat-card">
                    <span>Wegen</span>
                    <strong><?= number_format((int)$stats['roads'], 0, ',', '.') ?></strong>
                </article>
            </section>

            <section class="admin-card">
                <h2>Laatste update</h2>
                <p><?= h($stats['last_updated'] ?: 'Nog geen data geimporteerd.') ?></p>
            </section>
        <?php endif; ?>

        <section class="admin-card">
            <h2>Import</h2>
            <p>Gebruik de testimport om te controleren of PDOK en MySQL werken. Grote imports horen via webcron te lopen, omdat een browserrequest kan time-outen.</p>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                <input type="hidden" name="action" value="import">
                <label>
                    <span>Collectie</span>
                    <select name="collection">
                        <option value="all">Wegvakken + hectopunten</option>
                        <option value="wegvakken">Alleen wegvakken</option>
                        <option value="hectopunten">Alleen hectopunten</option>
                    </select>
                </label>
                <label>
                    <span>Pagina's</span>
                    <input name="max_pages" type="number" min="1" max="10" value="1">
                </label>
                <button class="button" type="submit">Import starten</button>
            </form>
            <p>Webcron endpoint:</p>
            <pre><code>https://jouwdomein.example/cron.php?token=CRON_TOKEN</code></pre>
            <p>Voor een korte webcron-test:</p>
            <pre><code>https://jouwdomein.example/cron.php?token=CRON_TOKEN&amp;max-pages=1</code></pre>
        </section>

        <section class="admin-card actions">
            <a class="button" href="/admin/setup.php">Schema setup</a>
            <a class="button button-secondary" href="/api/search.php?weg=A4&hectometer=8&zijde=R">API test</a>
        </section>
    </main>
</body>
</html>
