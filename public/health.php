<?php
declare(strict_types=1);

require __DIR__ . '/../app/config/db.php';

try {
    $pdo->query('SELECT 1');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Application health check failed: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'unavailable'], JSON_UNESCAPED_UNICODE);
}
