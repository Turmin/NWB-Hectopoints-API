<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';

function apiRepository(): HectometerRepository
{
    return new HectometerRepository((new Database())->connect());
}

function apiError(Throwable $exception, int $statusCode = 500): void
{
    error_log('API error: ' . $exception->getMessage());
    nwbJsonResponse([
        'success' => false,
        'error' => $statusCode >= 500 ? 'De data kon niet worden opgehaald.' : $exception->getMessage(),
    ], $statusCode);
}
