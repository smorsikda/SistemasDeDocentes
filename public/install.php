<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/helpers/csrf.php';

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Verificar si ya existe un admin
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount > 0) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $password2 === '') {
        $error = 'Completá todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo inválido.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        // Un bloqueo de MySQL evita que dos solicitudes iniciales creen dos administradores.
        $lock = $pdo->query("SELECT GET_LOCK('docentes_initial_admin_setup', 10)");
        $lockAcquired = (int)$lock->fetchColumn() === 1;

        if (!$lockAcquired) {
            $error = 'No se pudo iniciar la configuración. Intenta de nuevo.';
        } else {
            try {
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount > 0) {
                    $error = 'La configuración inicial ya se completó. Inicia sesión.';
                } else {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $check->execute([$email]);

                    if ($check->fetch()) {
                        $error = 'Ese correo ya existe.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);

                        $ins = $pdo->prepare("
                            INSERT INTO users (name, email, password_hash, role, is_active, must_change_password)
                            VALUES (?, ?, ?, 'admin', 1, 0)
                        ");
                        $ins->execute([$name, $email, $hash]);

                        $success = 'Administrador creado correctamente. Ya podés iniciar sesión.';
                    }
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('docentes_initial_admin_setup')");
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Instalación inicial</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{max-width:520px;width:100%}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card panel">
      <h2 style="margin:0 0 8px;">Configuración inicial</h2>
      <p style="margin:0;color:var(--muted);">Creá el primer administrador del sistema.</p>

      <?php if ($error): ?>
        <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div style="margin-top:12px;padding:12px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);">
          <?= h($success) ?><br><br>
          <a class="btnS" href="index.php">Ir al login</a>
        </div>
      <?php else: ?>
        <form method="post" style="margin-top:14px;">
          <?= csrf_input() ?>

          <div class="field">
            <label>Nombre</label>
            <input name="name" required>
          </div>

          <div class="field">
            <label>Correo</label>
            <input type="email" name="email" required>
          </div>

          <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" required>
          </div>

          <div class="field">
            <label>Confirmar contraseña</label>
            <input type="password" name="password2" required>
          </div>

          <button class="btn" type="submit">Crear administrador</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
