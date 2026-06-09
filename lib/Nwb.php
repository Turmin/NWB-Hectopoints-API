<?php

declare(strict_types=1);

const NWB_API_BASE_URL = 'https://api.pdok.nl/rws/nationaal-wegenbestand-wegen/ogc/v1';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nwbNormalizeRoad(?string $road): string
{
    $road = strtoupper(trim((string)$road));
    $road = preg_replace('/[^A-Z0-9]/', '', $road) ?? '';

    return $road;
}

function nwbNormalizeSide(?string $side): string
{
    $side = strtoupper(trim((string)$side));

    if (in_array($side, ['R', 'RE', 'RECHTS'], true)) {
        return 'R';
    }

    if (in_array($side, ['L', 'LI', 'LINKS'], true)) {
        return 'L';
    }

    return $side;
}

function nwbSideLabel(?string $side): string
{
    $side = nwbNormalizeSide($side);
    if ($side === 'R') {
        return 'Rechts';
    }

    if ($side === 'L') {
        return 'Links';
    }

    return trim((string)$side) !== '' ? (string)$side : '-';
}

function nwbHectometerCandidates(string $value): array
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '' || !is_numeric($value)) {
        return [];
    }

    $hasDecimal = strpos($value, '.') !== false;
    $candidates = [];

    if ($hasDecimal) {
        $candidates[] = (int)round(((float)$value) * 10);
    } else {
        $candidates[] = ((int)$value) * 10;
        $candidates[] = (int)$value;
    }

    return array_values(array_unique($candidates));
}

function nwbFormatHectometer($rawValue): string
{
    if ($rawValue === null || $rawValue === '') {
        return '-';
    }

    $raw = (int)$rawValue;
    $value = $raw / 10;

    return $raw % 10 === 0
        ? (string)(int)$value
        : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
}

function nwbPermalink(?string $road, ?string $side, $hectometrering, ?string $letter = null): string
{
    $road = nwbNormalizeRoad($road);
    $side = nwbNormalizeSide($side) ?: '-';
    $hm = nwbFormatHectometer($hectometrering);
    $parts = ['hectometer', $road, $side, $hm];

    $letter = strtoupper(trim((string)$letter));
    if ($letter !== '') {
        $parts[] = $letter;
    }

    return '/' . implode('/', array_map('rawurlencode', $parts)) . '/';
}

function nwbFirstCoordinate(?array $geometry): ?array
{
    if (!$geometry || empty($geometry['type']) || !isset($geometry['coordinates'])) {
        return null;
    }

    $coordinates = $geometry['coordinates'];
    if ($geometry['type'] === 'Point' && isset($coordinates[0], $coordinates[1])) {
        return [(float)$coordinates[0], (float)$coordinates[1]];
    }

    if ($geometry['type'] === 'MultiPoint' && isset($coordinates[0][0], $coordinates[0][1])) {
        return [(float)$coordinates[0][0], (float)$coordinates[0][1]];
    }

    return null;
}

function nwbJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function nwbRequestBaseUrl(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $isSecure ? 'https' : 'http';

    return $scheme . '://' . $host;
}
