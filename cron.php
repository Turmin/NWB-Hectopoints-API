<?php

declare(strict_types=1);

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/lib/NwbImportService.php';

$isCli = PHP_SAPI === 'cli';

function cronToken(): ?string
{
    $credentialsFile = dirname(__DIR__) . '/hectometer.cron.credentials.php';
    if (is_file($credentialsFile)) {
        $credentials = require $credentialsFile;
        if (is_array($credentials) && !empty($credentials['token'])) {
            return (string)$credentials['token'];
        }
    }

    $envToken = getenv('CRON_TOKEN');
    return $envToken !== false && $envToken !== '' ? $envToken : null;
}

function cronArgument(string $name, ?string $default = null): ?string
{
    global $argv, $isCli;

    if (!$isCli) {
        return isset($_GET[$name]) ? (string)$_GET[$name] : $default;
    }

    $prefix = '--' . $name . '=';
    foreach (array_slice($argv, 1) as $argument) {
        if (strncmp($argument, $prefix, strlen($prefix)) === 0) {
            return substr($argument, strlen($prefix));
        }
    }

    return $default;
}

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $expectedToken = cronToken();
    $providedToken = (string)($_GET['token'] ?? '');

    if (!$expectedToken || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or missing cron token.',
        ]);
        exit;
    }
}

try {
    $collection = cronArgument('collection', 'all');
    $pageSize = (int)cronArgument('page-size', '1000');
    $maxPagesRaw = cronArgument('max-pages');
    $maxPages = $maxPagesRaw !== null ? max(1, (int)$maxPagesRaw) : null;
    $service = new NwbImportService((new Database())->connect());

    $result = $collection === 'all'
        ? $service->importAll($pageSize, $maxPages)
        : [$collection => $service->importCollection((string)$collection, $pageSize, $maxPages)];

    $payload = [
        'success' => true,
        'result' => $result,
        'finished_at' => date('c'),
    ];
} catch (Throwable $exception) {
    http_response_code(500);
    $payload = [
        'success' => false,
        'error' => $exception->getMessage(),
        'finished_at' => date('c'),
    ];
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ($isCli ? PHP_EOL : '');
