<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';
require __DIR__ . '/../backend/helpers/log.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido');
}

verify_csrf_or_die();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$newRole = (string)($_POST['role'] ?? '');
$actorId = (int)($_SESSION['user']['id'] ?? 0);

if (!$userId || !in_array($newRole, ['admin', 'teacher'], true)) {
  header('Location: user_roles.php?result=invalid');
  exit;
}

if ((int)$userId === $actorId) {
  header('Location: user_roles.php?result=self');
  exit;
}

try {
  $pdo->beginTransaction();

  // Lock active admins in a consistent order so concurrent demotions cannot
  // remove the last active administrator.
  $activeAdmins = $pdo->query("
    SELECT id
    FROM users
    WHERE role = 'admin' AND is_active = 1
    ORDER BY id
    FOR UPDATE
  ")->fetchAll(PDO::FETCH_COLUMN);

  $targetStmt = $pdo->prepare("
    SELECT id, name, email, role, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
    FOR UPDATE
  ");
  $targetStmt->execute([(int)$userId]);
  $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

  if (!$target) {
    $pdo->rollBack();
    header('Location: user_roles.php?result=not_found');
    exit;
  }

  if ((int)$target['is_active'] !== 1) {
    $pdo->rollBack();
    header('Location: user_roles.php?result=inactive');
    exit;
  }

  $currentRole = (string)$target['role'];
  if ($currentRole === $newRole) {
    $pdo->rollBack();
    header('Location: user_roles.php?result=unchanged');
    exit;
  }

  if ($currentRole === 'admin' && $newRole === 'teacher' && count($activeAdmins) <= 1) {
    $pdo->rollBack();
    header('Location: user_roles.php?result=last_admin');
    exit;
  }

  $update = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
  $update->execute([$newRole, (int)$userId]);

  $roleNames = ['admin' => 'administrador', 'teacher' => 'docente'];
  log_activity(
    $pdo,
    $actorId,
    'user_role_changed',
    "Se cambió el rol de {$target['name']} ({$target['email']}) de {$roleNames[$currentRole]} a {$roleNames[$newRole]}"
  );

  $pdo->commit();
  header('Location: user_roles.php?result=changed');
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log('Role change failed: ' . $e->getMessage());
  header('Location: user_roles.php?result=failed');
  exit;
}
