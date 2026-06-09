<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';

$error = null;
$stats = null;

try {
    $repository = new HectometerRepository((new Database())->connect());
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
            <a class="button button-light" href="/">Website</a>
        </header>

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
            <p>Voer de import uit via de command line. Eerst worden wegvakken geladen, daarna hectopunten.</p>
            <pre><code>php admin/import-nwb.php all</code></pre>
            <p>Voor een snelle test:</p>
            <pre><code>php admin/import-nwb.php all --max-pages=1</code></pre>
        </section>

        <section class="admin-card actions">
            <a class="button" href="/admin/setup.php">Schema setup</a>
            <a class="button button-secondary" href="/api/search.php?weg=A4&hectometer=8&zijde=R">API test</a>
        </section>
    </main>
</body>
</html>
