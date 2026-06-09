<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $bbox = (string)($_GET['bbox'] ?? '');
    $parts = array_map('trim', explode(',', $bbox));

    if (count($parts) !== 4 || array_filter($parts, 'is_numeric') !== $parts) {
        nwbJsonResponse([
            'success' => false,
            'error' => 'Gebruik bbox=minLon,minLat,maxLon,maxLat.',
        ], 400);
        exit;
    }

    [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', $parts);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
    $repository = apiRepository();
    $items = $repository->bbox($minLon, $minLat, $maxLon, $maxLat, $limit);

    nwbJsonResponse([
        'type' => 'FeatureCollection',
        'features' => array_map([$repository, 'toGeoJsonFeature'], $items),
    ]);
} catch (Throwable $exception) {
    apiError($exception);
}
