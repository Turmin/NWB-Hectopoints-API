<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';
require_once __DIR__ . '/auth.php';

adminHandleAuthPost();

if (!adminIsLoggedIn()) {
    adminRenderAuthPage('Hectometer Setup');
    exit;
}

$flash = adminFlash();
$messages = [];
$adminError = null;
$stats = [
    'hectopunten' => 0,
    'wegvakken' => 0,
    'roads' => 0,
    'last_updated' => null,
];
$db = null;
$adminUser = $_SESSION['admin_user'] ?? 'Admin';

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
    $adminError = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hectometer Setup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="container admin-container">
        <div class="admin-card">
            <div class="admin-header d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-1"><i class="bi bi-database-gear me-2"></i>Hectometer Setup</h1>
                    <div>Database schema en configuratie.</div>
                </div>
                <div class="admin-toolbar d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark admin-user-badge"><i class="bi bi-person-circle me-1"></i><?= h($adminUser) ?></span>
                    <a class="btn btn-outline-light btn-sm" href="index.php"><i class="bi bi-speedometer2 me-1"></i>Admin</a>
                    <a class="btn btn-outline-light btn-sm" href="../"><i class="bi bi-house-door me-1"></i>Website</a>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Uitloggen</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type'] ?? 'success') ?>"><?= h($flash['message'] ?? '') ?></div>
        <?php endif; ?>

        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endforeach; ?>

        <?php if ($adminError): ?>
            <div class="alert alert-danger"><?= h($adminError) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="bi bi-database text-primary me-2"></i>Database schema</h2>
                        <p class="text-muted">Deze setup maakt de MySQL-tabellen <code>hm_wegvakken</code> en <code>hm_hectopunten</code>.</p>
                        <form method="post" class="d-grid gap-2">
                            <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                            <input type="hidden" name="action" value="install">
                            <button class="btn btn-primary action-btn w-100" type="submit">Schema bijwerken</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="bi bi-key text-primary me-2"></i>Database credentials</h2>
                        <div class="small text-muted mb-2">Bestand buiten de webroot: <code>database.credentials.php</code></div>
                        <pre><code>&lt;?php
return [
    'host' =&gt; 'localhost',
    'port' =&gt; '3306',
    'db_name' =&gt; 'hectometerpaaltjes',
    'username' =&gt; 'database_user',
    'password' =&gt; 'database_password',
];</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Hectopunten</div>
                    <div class="stat-number"><?= number_format((int)$stats['hectopunten'], 0, ',', '.') ?></div>
                    <small>NWB punten</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Wegvakken</div>
                    <div class="stat-number"><?= number_format((int)$stats['wegvakken'], 0, ',', '.') ?></div>
                    <small>NWB wegvakken</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Wegen</div>
                    <div class="stat-number"><?= number_format((int)$stats['roads'], 0, ',', '.') ?></div>
                    <small>Unieke wegnummers</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted">Database</div>
                    <div class="stat-number"><?= $db ? 'Online' : '-' ?></div>
                    <small><?= $db ? 'Verbonden' : 'Niet verbonden' ?></small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
