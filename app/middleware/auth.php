<?php
require_once __DIR__ . '/../helpers/session.php';
start_app_session();

// ============================
// CONFIGURACIÓN DE INACTIVIDAD
// 10 minutos = 600 segundos
// ============================
const SESSION_TIMEOUT = 600;

// ============================
// CONTROL DE EXPIRACIÓN
// ============================
if (isset($_SESSION['user'])) {
  $lastActivity = (int)($_SESSION['LAST_ACTIVITY'] ?? 0);

  if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();

    header("Location: /docentes/public/index.php?expired=1");
    exit;
  }

  $_SESSION['LAST_ACTIVITY'] = time();
}

function isLogged(): bool {
  return isset($_SESSION['user']);
}

function requireLogin(): void {
  if (!isLogged()) {
    header("Location: /docentes/public/index.php");
    exit;
  }
}

function requireRole(string $role): void {
  requireLogin();

  if (($_SESSION['user']['role'] ?? '') !== $role) {
    http_response_code(403);
    echo "403 - Acceso denegado";
    exit;
  }
}

function requirePasswordChangeIfNeeded(): void {
  if (isLogged() && (int)($_SESSION['user']['must_change_password'] ?? 0) === 1) {
    $path = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($path, 'change_password.php') === false && strpos($path, 'logout.php') === false) {
      header("Location: /docentes/public/change_password.php");
      exit;
    }
  }
}
