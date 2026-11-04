<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';
require __DIR__ . '/../backend/helpers/log.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: groups.php");
    exit;
}

verify_csrf_or_die();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: groups.php");
    exit;
}

// Traer grupo
$stmt = $pdo->prepare("SELECT * FROM groups_table WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header("Location: groups.php");
    exit;
}

// Permisos:
// admin puede finalizar cualquiera
// docente solo sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

// Si ya está finalizado, no hacer nada
if (($group['status'] ?? '') !== 'finalizado') {
    $upd = $pdo->prepare("UPDATE groups_table SET status = 'finalizado' WHERE id = ?");
    $upd->execute([$id]);

    log_activity(
        $pdo,
        (int)$_SESSION['user']['id'],
        'group_finalized',
        "Se finalizó el grupo {$group['group_code']} ({$group['name']}) con ID {$group['id']}"
    );
}

header("Location: groups.php?finalized=1");
exit;