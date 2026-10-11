<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount === 0) {
    header("Location: install.php");
    exit;
}

if (isLogged()) {
    if ((int)($_SESSION['user']['must_change_password'] ?? 0) === 1) {
        header("Location: change_password.php");
        exit;
    }
    header("Location: dashboard.php");
    exit;
}

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function getClientIp(): string
{
    // Los encabezados X-Forwarded-For y CF-Connecting-IP son controlables
    // por el cliente si no hay un proxy de confianza configurado.
    $remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';
}

function logActivity(PDO $pdo, ?int $userId, string $action, string $description, string $ip): void
{
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $action, $description, $ip]);
}

$error = "";
$expiredMessage = isset($_GET['expired']) ? "Tu sesión expiró por inactividad. Iniciá sesión de nuevo." : "";
$ip = getClientIp();

$maxAttempts = 5;
$lockMinutes = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Completá correo y contraseña.";
    } else {
        // Buscar registro de intentos para ese email + IP
        $stmt = $pdo->prepare("
            SELECT *
            FROM login_attempts
            WHERE email = ? AND ip_address = ?
            LIMIT 1
        ");
        $stmt->execute([$email, $ip]);
        $attemptRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si está bloqueado
        if ($attemptRow && !empty($attemptRow['blocked_until']) && strtotime($attemptRow['blocked_until']) > time()) {
            $remaining = max(1, (int)ceil((strtotime($attemptRow['blocked_until']) - time()) / 60));
            $error = "Demasiados intentos fallidos. Intentá de nuevo en {$remaining} minuto(s).";

            logActivity(
                $pdo,
                null,
                'login_blocked',
                "Intento bloqueado para {$email}",
                $ip
            );
        } else {
            // Buscar usuario
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE email = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login correcto: limpiar intentos
                $del = $pdo->prepare("
                    DELETE FROM login_attempts
                    WHERE email = ? AND ip_address = ?
                ");
                $del->execute([$email, $ip]);

                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'must_change_password' => (int)($user['must_change_password'] ?? 0),
                    'auth_fingerprint' => hash('sha256', (string)$user['password_hash']),
                ];

                // Registrar última actividad para control de inactividad
                $_SESSION['LAST_ACTIVITY'] = time();

                logActivity(
                    $pdo,
                    (int)$user['id'],
                    'login_success',
                    "Inicio de sesión exitoso: {$email}",
                    $ip
                );

                if ((int)($user['must_change_password'] ?? 0) === 1) {
                    header("Location: change_password.php");
                    exit;
                }

                header("Location: dashboard.php");
                exit;
            } else {
                // Login fallido: registrar intento
                if ($attemptRow) {
                    $newAttempts = (int)$attemptRow['attempts'] + 1;
                    $blockedUntil = null;

                    if ($newAttempts >= $maxAttempts) {
                        $blockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockMinutes} minutes"));
                    }

                    $upd = $pdo->prepare("
                        UPDATE login_attempts
                        SET attempts = ?, last_attempt_at = NOW(), blocked_until = ?
                        WHERE id = ?
                    ");
                    $upd->execute([
                        $newAttempts,
                        $blockedUntil,
                        (int)$attemptRow['id']
                    ]);
                } else {
                    $newAttempts = 1;
                    $blockedUntil = null;

                    $ins = $pdo->prepare("
                        INSERT INTO login_attempts (email, ip_address, attempts, last_attempt_at, blocked_until)
                        VALUES (?, ?, 1, NOW(), NULL)
                    ");
                    $ins->execute([$email, $ip]);
                }

                logActivity(
                    $pdo,
                    $user ? (int)$user['id'] : null,
                    'login_failed',
                    "Credenciales incorrectas para {$email}",
                    $ip
                );

                if ($newAttempts >= $maxAttempts) {
                    $error = "Demasiados intentos fallidos. Tu acceso fue bloqueado por {$lockMinutes} minutos.";
                } else {
                    $remainingAttempts = $maxAttempts - $newAttempts;
                    $error = "Credenciales incorrectas. Te quedan {$remainingAttempts} intento(s).";
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login | Docentes</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
        <div class="title">
          <h1 style="margin:0;font-size:18px;">CONATRADEC</h1>
          <p style="margin:4px 0 0;color:var(--muted);font-size:13px;">Sistema de Docentes</p>
        </div>
      </div>

      <h2 style="margin:12px 0 6px;">Iniciar sesión</h2>
      <p>Ingresá con tu correo y contraseña.</p>

      <?php if ($expiredMessage !== ''): ?>
        <div class="alert"><?= h($expiredMessage) ?></div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <?= csrf_input() ?>

        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required placeholder="docente@conatradec.local">
        </div>

        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button class="btn" type="submit">Entrar</button>
      </form>

      <div style="margin-top:14px;text-align:center;">
        <a href="certificate_lookup.php" style="color:var(--muted);font-weight:700;text-decoration:none;">
          ¿Sos estudiante? Descargá tu certificado aquí
        </a>
      </div>

      <div class="footer-links">
        <span class="small">©️ <?= date('Y') ?> CONATRADEC</span>
        <form method="post" action="logout.php" style="display:inline;">
          <?= csrf_input() ?>
          <button class="small" type="submit" style="border:0;background:none;padding:0;color:inherit;cursor:pointer;">Limpiar sesión</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
