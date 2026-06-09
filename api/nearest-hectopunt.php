<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $lat = $_GET['lat'] ?? null;
    $lon = $_GET['lon'] ?? null;

    if (!is_numeric($lat) || !is_numeric($lon)) {
        nwbJsonResponse([
            'success' => false,
            'error' => 'Gebruik lat en lon als numerieke parameters.',
        ], 400);
        exit;
    }

    $item = apiRepository()->nearest((float)$lat, (float)$lon);
    if ($item === null) {
        nwbJsonResponse([
            'success' => false,
            'error' => 'Geen hectometerpaaltje gevonden.',
        ], 404);
        exit;
    }

    nwbJsonResponse([
        'success' => true,
        'result' => $item,
    ]);
} catch (Throwable $exception) {
    apiError($exception);
}
