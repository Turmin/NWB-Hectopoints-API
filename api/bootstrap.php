<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../lib/HectometerRepository.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Serverfout in de API.',
        'detail' => $error['message'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

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
