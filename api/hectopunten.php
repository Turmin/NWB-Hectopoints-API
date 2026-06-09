<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $repository = apiRepository();
    $results = $repository->search($_GET);

    nwbJsonResponse([
        'success' => true,
        'count' => count($results),
        'results' => $results,
    ]);
} catch (Throwable $exception) {
    apiError($exception);
}
