<?php

require_once __DIR__ . '/session.php';
start_app_session();

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_die(): void
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postedToken  = $_POST['csrf_token'] ?? '';

    if (!$sessionToken || !$postedToken || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(403);
        exit('Solicitud inválida (CSRF).');
    }
}
