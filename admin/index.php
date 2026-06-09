<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';
require_once __DIR__ . '/../lib/NwbImportService.php';
require_once __DIR__ . '/auth.php';

adminHandleAuthPost();

if (!adminIsLoggedIn()) {
    adminRenderAuthPage('Hectometer Admin');
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
$cronTokenConfigured = false;

foreach ([
    dirname(__DIR__, 2) . '/cron.credentials.php',
] as $tokenFile) {
    if (is_file($tokenFile)) {
        $cronTokenConfigured = true;
        break;
    }
}

try {
    $db = (new Database())->connect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
        adminRequireCsrf();
        $collection = (string)($_POST['collection'] ?? 'all');
        $maxPages = max(1, min((int)($_POST['max_pages'] ?? 1), 10));

        if (!in_array($collection, ['all', 'wegvakken', 'hectopunten'], true)) {
            throw new InvalidArgumentException('Onbekende importcollectie.');
        }

        $service = new NwbImportService($db);
        $result = $collection === 'all'
            ? $service->importAll(1000, $maxPages)
            : [$collection => $service->importCollection($collection, 1000, $maxPages)];
        $messages[] = 'Import uitgevoerd: ' . json_encode($result, JSON_UNESCAPED_SLASHES);
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
    <title>Hectometer Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin-style.css" rel="stylesheet">
</head>
<body>
    <div class="container admin-container">
        <div class="admin-card">
            <div class="admin-header d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-1"><i class="bi bi-speedometer2 me-2"></i>Hectometer Admin Panel</h1>
                    <div>NWB import, database setup en API-controle.</div>
                </div>
                <div class="admin-toolbar d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark admin-user-badge"><i class="bi bi-person-circle me-1"></i><?= h($adminUser) ?></span>
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
                    <div class="text-muted">Laatste update</div>
                    <div class="stat-number"><?= h($stats['last_updated'] ? date('d-m-Y', strtotime((string)$stats['last_updated'])) : '-') ?></div>
                    <small><?= h($stats['last_updated'] ?: 'Nog geen data') ?></small>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="bi bi-cloud-download text-primary me-2"></i>NWB import</h2>
                        <form method="post" class="d-grid gap-2">
                            <input type="hidden" name="csrf" value="<?= h(adminCsrf()) ?>">
                            <input type="hidden" name="action" value="import">
                            <div>
                                <label class="form-label" for="collection">Collectie</label>
                                <select class="form-select" id="collection" name="collection">
                                    <option value="all">Wegvakken + hectopunten</option>
                                    <option value="wegvakken">Alleen wegvakken</option>
                                    <option value="hectopunten">Alleen hectopunten</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="max_pages">Pagina's voor testimport</label>
                                <input class="form-control" id="max_pages" name="max_pages" type="number" min="1" max="10" value="1">
                            </div>
                            <button class="btn btn-primary action-btn w-100" type="submit">Testimport starten</button>
                            <div class="small text-muted">
                                Grote imports via webcron draaien; browserrequests kunnen time-outen.
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-body">
                        <h2 class="h5 mb-3"><i class="bi bi-check2-circle text-success me-2"></i>System status</h2>
                        <div class="d-grid gap-2">
                            <div class="status-row d-flex justify-content-between"><span>Database connection</span><span class="badge bg-<?= $db ? 'success' : 'danger' ?>"><?= $db ? 'Online' : 'Offline' ?></span></div>
                            <div class="status-row d-flex justify-content-between"><span>Cron token</span><span class="badge bg-<?= $cronTokenConfigured ? 'success' : 'warning' ?>"><?= $cronTokenConfigured ? 'Configured' : 'Missing' ?></span></div>
                            <div class="status-row d-flex justify-content-between"><span>Admin credentials</span><span class="badge bg-success">Configured</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="card-body">
                <h2 class="h5 mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Webcron</h2>
                <div class="small text-muted mb-2">Gebruik dezelfde tokenstructuur als de bestaande projecten.</div>
                <pre><code>https://hectopunten_dev.turmin.com/cron.php?token=CRON_TOKEN</code></pre>
                <pre><code>https://hectopunten_dev.turmin.com/cron.php?token=CRON_TOKEN&amp;max-pages=1</code></pre>
                <div class="action-grid mt-3">
                    <a class="btn btn-outline-primary action-btn" href="setup.php">Schema setup</a>
                    <a class="btn btn-outline-secondary action-btn" href="../api/search.php?weg=A4&amp;hectometer=8&amp;zijde=R">API test</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
