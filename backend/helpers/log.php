<?php

function get_client_ip(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim((string)$_SERVER[$key]);

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }

            return $value;
        }
    }

    return '0.0.0.0';
}

function log_activity(PDO $pdo, ?int $userId, string $action, string $description = ''): void
{
    $ip = get_client_ip();

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $action,
        $description,
        $ip
    ]);
}