<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';
require __DIR__ . '/../app/helpers/log.php';

requireLogin();
requirePasswordChangeIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

verify_csrf_or_die();

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    header('Location: students.php?archive=not_found');
    exit;
}

$studentStmt = $pdo->prepare('SELECT id, full_name, is_historical FROM students WHERE id = ? LIMIT 1');
$studentStmt->execute([$id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student || (int)$student['is_historical'] === 1) {
    header('Location: students.php?archive=not_found');
    exit;
}

$activeGroupStmt = $pdo->prepare("
    SELECT 1
    FROM group_students gs
    JOIN groups_table g ON g.id = gs.group_id
    WHERE gs.student_id = ? AND g.status = 'activo'
    LIMIT 1
");
$activeGroupStmt->execute([$id]);
if ($activeGroupStmt->fetchColumn()) {
    header('Location: students.php?archive=active_group');
    exit;
}

$archiveStmt = $pdo->prepare('UPDATE students SET is_historical = 1 WHERE id = ? AND is_historical = 0');
$archiveStmt->execute([$id]);

if ($archiveStmt->rowCount() !== 1) {
    header('Location: students.php?archive=failed');
    exit;
}

log_activity($pdo, (int)$_SESSION['user']['id'], 'student.archive', 'Estudiante archivado: ' . $student['full_name'] . ' (ID ' . (int)$id . ')');

header('Location: students.php?archive=done');
exit;
