<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';
require __DIR__ . '/../backend/helpers/log.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$error = '';
$success = '';

function gen_password(int $len = 10): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#';
  $p = '';
  for ($i = 0; $i < $len; $i++) {
    $p .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  return $p;
}

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();

  $name  = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));

  $allowedDomains = [
    'gmail.com',
    'hotmail.com',
    'yahoo.com',
    'conatradec.com'
  ];

  if ($name === '' || $email === '') {
    $error = 'Completá nombre y email.';
  } elseif (mb_strlen($name) < 3) {
    $error = 'El nombre debe tener al menos 3 caracteres.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'El email no tiene un formato válido.';
  } else {
    $domain = substr(strrchr($email, '@'), 1);

    if (!$domain || !in_array($domain, $allowedDomains, true)) {
      $error = 'Solo se permiten correos Gmail, Hotmail, Yahoo o institucionales de CONATRADEC.';
    } else {
      $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $chk->execute([$email]);

      if ($chk->fetch()) {
        $error = 'Ese email ya existe.';
      } else {
        $plain = gen_password(10);
        $hash  = password_hash($plain, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("
          INSERT INTO users (name, email, password_hash, role, is_active, must_change_password)
          VALUES (?, ?, ?, ?, 1, 1)
        ");
        $ins->execute([$name, $email, $hash, 'teacher']);

        $newTeacherId = (int)$pdo->lastInsertId();

        log_activity(
          $pdo,
          (int)$_SESSION['user']['id'],
          'teacher_created',
          "Se creó el docente {$name} ({$email}) con ID {$newTeacherId}"
        );

        $success = "Docente creado correctamente.";
        $success .= "<br><b>Contraseña temporal:</b> <code>" . h($plain) . "</code>";
        $success .= "<br><span style='color:var(--muted)'>El docente será obligado a cambiarla al iniciar sesión.</span>";

        $_POST['name'] = '';
        $_POST['email'] = '';
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear docente</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:860px;
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

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      flex-wrap:wrap;
    }

    .muted{
      color:var(--muted);
    }

    .success-box{
      margin-top:14px;
      padding:14px 16px;
      border-radius:16px;
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
      line-height:1.7;
    }

    .success-box code{
      display:inline-block;
      margin-top:6px;
      padding:8px 12px;
      border-radius:10px;
      background:rgba(255,255,255,.06);
      border:1px solid var(--line);
      font-size:18px;
      font-weight:900;
      color:#C7CBB8;
      word-break:break-all;
    }

    .form-grid{
      display:grid;
      grid-template-columns:1fr;
      gap:0;
      margin-top:12px;
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
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
      color:var(--accent);
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
      border:1px solid rgba(61,67,55,.25);
      background:rgba(255,255,255,.05);
      color:#C7CBB8;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .note{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(255,255,255,.04);
      border:1px solid var(--line);
      color:var(--muted);
      font-size:14px;
      line-height:1.6;
    }

    .hint{
      font-size:12px;
      color:var(--muted);
      margin-top:6px;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Crear docente</h2>
        <p style="margin:0;color:var(--muted);">
          Se genera automáticamente una contraseña temporal segura.
        </p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:14px;"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success-box">
        <?= $success ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form-grid">
      <?= csrf_input(); ?>

      <div class="field">
        <label>Nombre</label>
        <input name="name" required value="<?= h($_POST['name'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Email</label>
        <input
          type="email"
          name="email"
          required
          placeholder="docente@correo.com"
          value="<?= h($_POST['email'] ?? '') ?>"
        >
        <div class="hint">Solo se permiten correos Gmail, Hotmail, Yahoo o institucionales de CONATRADEC.</div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Crear docente</button>
        <a class="btnS" href="teachers.php">← Volver a docentes</a>
        <a class="btnG" href="dashboard.php">Ir al dashboard</a>
      </div>
    </form>

    <div class="note">
      El docente creado quedará con cambio de contraseña obligatorio en su próximo inicio de sesión.
    </div>
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