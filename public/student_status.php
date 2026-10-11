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

if (!in_array(($me['role'] ?? ''), ['admin', 'teacher'], true)) {
  http_response_code(403);
  exit('Acceso denegado');
}

$id = (int)($_POST['id'] ?? 0);
$to = trim((string)($_POST['to'] ?? ''));

if ($id <= 0 || !in_array($to, ['pendiente','aprobado','desaprobado'], true)) {
  http_response_code(400); exit("Bad request");
}

// Cargar estudiante y validar permisos
$st = $pdo->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
$st->execute([$id]);
$s = $st->fetch();
if (!$s) { http_response_code(404); exit("No existe"); }

if (($me['role'] ?? '') === 'teacher' && (int)$s['teacher_id'] !== (int)$me['id']) {
  http_response_code(403); exit("Acceso denegado");
}

$up = $pdo->prepare("UPDATE students SET status=? WHERE id=?");
$up->execute([$to, $id]);

header("Location: students.php");
exit;
