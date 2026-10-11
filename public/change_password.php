<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';
require __DIR__ . '/../app/helpers/log.php';

requireLogin();

$me = $_SESSION['user'];
$force = ((int)($me['must_change_password'] ?? 0) === 1);

$error = '';

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();

  $current = (string)($_POST['current_password'] ?? '');
  $new1 = (string)($_POST['new_password'] ?? '');
  $new2 = (string)($_POST['new_password2'] ?? '');

  if ($new1 === '' || $new2 === '') {
    $error = "Completá la nueva contraseña.";
  } elseif ($new1 !== $new2) {
    $error = "Las nuevas contraseñas no coinciden.";
  } elseif (strlen($new1) < 8) {
    $error = "Mínimo 8 caracteres.";
  } else {
    $st = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$me['id']]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      http_response_code(404);
      exit("Usuario no existe");
    }

    if (!password_verify($current, $u['password_hash'])) {
      $error = "La contraseña actual no es correcta.";
    } else {
      $newHash = password_hash($new1, PASSWORD_DEFAULT);

      $up = $pdo->prepare("
        UPDATE users
        SET password_hash = ?, must_change_password = 0
        WHERE id = ?
      ");
      $up->execute([$newHash, (int)$me['id']]);

      log_activity(
        $pdo,
        (int)$me['id'],
        'password_changed',
        'El usuario cambió su contraseña correctamente'
      );

      $_SESSION['user']['must_change_password'] = 0;
      $_SESSION['user']['auth_fingerprint'] = hash('sha256', $newHash);
      session_regenerate_id(true);

      header("Location: dashboard.php");
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambiar contraseña</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:820px;
      width:100%;
      margin:0 auto;
    }

    .panel{
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:20px;
      box-shadow:var(--shadow);
    }

    .note{
      color:var(--muted);
      margin:0 0 12px;
      line-height:1.6;
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      flex-wrap:wrap;
    }

    .warn-box{
      margin-top:14px;
      padding:14px 16px;
      border-radius:16px;
      border:1px solid rgba(245,158,11,.35);
      background:rgba(245,158,11,.08);
      color:#fde68a;
      line-height:1.6;
    }

    .normal-box{
      margin-top:14px;
      padding:14px 16px;
      border-radius:16px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.04);
      color:var(--muted);
      line-height:1.6;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:16px;
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Cambiar contraseña</h2>
        <?php if ($force): ?>
          <p class="note">Por seguridad, debés cambiar tu contraseña para continuar.</p>
        <?php else: ?>
          <p class="note">Usá una contraseña fuerte de al menos 8 caracteres.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($force): ?>
      <div class="warn-box">
        Esta cuenta tiene cambio obligatorio de contraseña activado.
        No podrás continuar normalmente hasta actualizarla.
      </div>
    <?php else: ?>
      <div class="normal-box">
        Recomendación: combiná letras mayúsculas, minúsculas, números y símbolos para mejorar la seguridad.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:14px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:14px;">
      <?= csrf_input(); ?>
      <div class="field">
        <label>Contraseña actual</label>
        <input type="password" name="current_password" required>
      </div>

      <div class="field">
        <label>Nueva contraseña</label>
        <input type="password" name="new_password" required>
      </div>

      <div class="field">
        <label>Confirmar nueva contraseña</label>
        <input type="password" name="new_password2" required>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Guardar</button>

        <?php if (!$force): ?>
          <a class="btnS" href="dashboard.php">← Volver al dashboard</a>
        <?php endif; ?>
      </div>
    </form>
  </section>
</main>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('appSidebar');
  if (window.innerWidth <= 960) {
    sidebar.classList.toggle('open');
  } else {
    sidebar.classList.toggle('collapsed');
  }
}
</script>
</body>
</html>
