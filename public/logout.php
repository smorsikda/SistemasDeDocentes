<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/helpers/log.php';
require __DIR__ . '/../app/helpers/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

verify_csrf_or_die();

// Guardar info antes de destruir sesión
$userId = $_SESSION['user']['id'] ?? null;

if ($userId) {
    log_activity(
        $pdo,
        (int)$userId,
        'logout',
        'Cierre de sesión del usuario'
    );
}

$_SESSION = [];
session_destroy();

$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'] ?? 'Lax',
]);

header("Location: index.php");
exit;
