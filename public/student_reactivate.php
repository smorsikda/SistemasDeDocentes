<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';

requireLogin();
requirePasswordChangeIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

verify_csrf_or_die();

$me = $_SESSION['user'];

if (($me['role'] ?? '') !== 'admin') {
    exit('Acceso denegado');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    exit('ID inválido');
}

$stmt = $pdo->prepare("
    UPDATE students
    SET is_historical = 0
    WHERE id = ?
");
$stmt->execute([$id]);

header("Location: students.php");
exit;
