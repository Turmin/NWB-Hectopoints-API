<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 80;
    $roads = apiRepository()->roads($limit);

    nwbJsonResponse([
        'success' => true,
        'count' => count($roads),
        'results' => $roads,
    ]);
} catch (Throwable $exception) {
    apiError($exception);
}
