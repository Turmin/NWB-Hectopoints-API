<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';
require_once __DIR__ . '/auth.php';

$messages = [];
$error = null;
$stats = null;
$credentialsPath = dirname(__DIR__, 2) . '/database.credentials.php';
$flash = null;

adminHandleAuthPost();

if (!adminIsLoggedIn()) {
    adminRenderAuthPage('NWB setup');
    exit;
}

$flash = adminFlash();

function runSchema(PDO $db, string $schemaPath): void
{
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('schema.sql kon niet worden gelezen.');
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $schema);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $db->exec($statement);
    }
}

try {
    $db = (new Database())->connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
        adminRequireCsrf();
        runSchema($db, __DIR__ . '/../schema.sql');
        $messages[] = 'Schema is bijgewerkt.';
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
    <title>NWB setup</title>
    <link rel="stylesheet" href="/admin/css/admin-style.css">
</head>
<body>
    <main class="admin-container">
        <header class="admin-header">
            <div>
                <p class="eyebrow">Hectometerpaaltjes</p>
                <h1>Setup</h1>
            </div>
            <div class="admin-toolbar">
                <a class="button button-light" href="/admin/">Beheer</a>
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
                <h2>Configuratie nodig</h2>
                <p><?= h($error) ?></p>
                <p>Maak bij voorkeur dit bestand buiten de webroot:</p>
                <pre><code><?= h($credentialsPath) ?></code></pre>
                <pre><code>&lt;?php
return [
    'host' =&gt; 'localhost',
    'port' =&gt; '3306',
    'db_name' =&gt; 'hectometerpaaltjes',
    'username' =&gt; 'database_user',
    'password' =&gt; 'database_password',
];</code></pre>
            </section>
        <?php else: ?>
            <section class="admin-card">
                <h2>Database schema</h2>
                <p>Deze setup maakt de MySQL-tabellen `wegvakken` en `hectopunten` met kolommen voor lon/lat, metadata en indexen.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                    <input type="hidden" name="action" value="install">
                    <button class="button" type="submit">Schema bijwerken</button>
                </form>
            </section>

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
                <h2>Data importeren</h2>
                <pre><code>php admin/import-nwb.php all</code></pre>
                <p>Test eerst klein als je wilt controleren of de PDOK-connectie en MySQL-import goed staan:</p>
                <pre><code>php admin/import-nwb.php all --max-pages=1</code></pre>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
