<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $road = (string)($_GET['weg'] ?? $_GET['road'] ?? '');
    $side = (string)($_GET['zijde'] ?? $_GET['side'] ?? '');
    $hectometer = (string)($_GET['hectometer'] ?? $_GET['hm'] ?? '');
    $letter = isset($_GET['letter']) ? (string)$_GET['letter'] : null;

    $item = apiRepository()->findByPermalink($road, $side, $hectometer, $letter);
    if ($item === null) {
        nwbJsonResponse([
            'success' => false,
            'error' => 'Hectometerpaaltje niet gevonden.',
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
