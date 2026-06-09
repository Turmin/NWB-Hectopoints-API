<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/NwbImportService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "This importer is CLI only.\n";
    exit;
}

function optionValue(array $arguments, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($arguments as $argument) {
        if (strncmp($argument, $prefix, strlen($prefix)) === 0) {
            return substr($argument, strlen($prefix));
        }
    }

    return $default;
}

$arguments = array_slice($argv, 1);
$collection = optionValue($arguments, 'collection', $arguments[0] ?? 'all');
$pageSize = (int)optionValue($arguments, 'page-size', '1000');
$maxPagesRaw = optionValue($arguments, 'max-pages');
$maxPages = $maxPagesRaw !== null ? max(1, (int)$maxPagesRaw) : null;

try {
    $db = (new Database())->connect();
    $service = new NwbImportService($db);

    if ($collection === 'all') {
        $result = $service->importAll($pageSize, $maxPages);
    } else {
        $result = [$collection => $service->importCollection((string)$collection, $pageSize, $maxPages)];
    }

    echo json_encode([
        'success' => true,
        'result' => $result,
        'finished_at' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
