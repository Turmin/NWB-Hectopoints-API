<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Nwb.php';

function normalizeFeature(array $feature): array
{
    $properties = $feature['properties'] ?? [];
    if (!is_array($properties)) {
        $properties = [];
    }

    $coordinate = nwbFirstCoordinate($feature['geometry'] ?? null);

    return [
        'id' => $feature['id'] ?? null,
        'wvk_id' => $properties['wvk_id'] ?? null,
        'hectometrering' => $properties['hectomtrng'] ?? null,
        'hectometer' => isset($properties['hectomtrng']) ? nwbFormatHectometer($properties['hectomtrng']) : null,
        'zijde' => $properties['zijde'] ?? null,
        'side' => isset($properties['zijde']) ? nwbNormalizeSide((string)$properties['zijde']) : null,
        'hectoletter' => $properties['hecto_lttr'] ?? null,
        'afstand' => $properties['afstand'] ?? null,
        'longitude' => $coordinate[0] ?? null,
        'latitude' => $coordinate[1] ?? null,
        'geometry' => $feature['geometry'] ?? null,
        'source' => 'PDOK NWB',
    ];
}
