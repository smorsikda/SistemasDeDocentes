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
    clear_auth_session();

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

  global $pdo;
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../config/db.php';
  }

  $userId = (int)($_SESSION['user']['id'] ?? 0);
  $stmt = $pdo->prepare("SELECT role, is_active, must_change_password, password_hash FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $sessionFingerprint = (string)($_SESSION['user']['auth_fingerprint'] ?? '');
  $currentFingerprint = $user ? hash('sha256', (string)$user['password_hash']) : '';

  if (!$user || (int)$user['is_active'] !== 1 || $sessionFingerprint === '' || !hash_equals($currentFingerprint, $sessionFingerprint)) {
    clear_auth_session();
    header("Location: /docentes/public/index.php?expired=1");
    exit;
  }

  // Mantener permisos y obligación de cambio sincronizados con la base de datos.
  $_SESSION['user']['role'] = (string)$user['role'];
  $_SESSION['user']['must_change_password'] = (int)$user['must_change_password'];
}

function clear_auth_session(): void {
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
      'expires' => time() - 42000,
      'path' => $params['path'] ?? '/',
      'domain' => $params['domain'] ?? '',
      'secure' => (bool)($params['secure'] ?? false),
      'httponly' => (bool)($params['httponly'] ?? true),
      'samesite' => $params['samesite'] ?? 'Lax',
    ]);
  }

  if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
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
