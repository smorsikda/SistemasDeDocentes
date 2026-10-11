<?php

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    if (!$isHttps && (int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        $isHttps = true;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/docentes/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
