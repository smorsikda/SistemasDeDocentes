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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit("Bad request");
}

$st = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id=? LIMIT 1");
$st->execute([$id]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if (!$u) {
  http_response_code(404);
  exit("No existe");
}

if (($u['role'] ?? '') !== 'teacher') {
  http_response_code(403);
  exit("Solo docentes.");
}

function gen_password(int $len = 10): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#';
  $p = '';
  $max = strlen($alphabet) - 1;

  for ($i = 0; $i < $len; $i++) {
    $p .= $alphabet[random_int(0, $max)];
  }

  return $p;
}

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$newPlain = gen_password(10);
$newHash  = password_hash($newPlain, PASSWORD_DEFAULT);

// must_change_password = 1
$up = $pdo->prepare("
  UPDATE users
  SET password_hash = ?, must_change_password = 1
  WHERE id = ?
");
$up->execute([$newHash, $id]);

log_activity(
  $pdo,
  (int)$_SESSION['user']['id'],
  'password_reset',
  "Se restableció la contraseña del docente {$u['name']} ({$u['email']}) con ID {$u['id']}"
);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restablecer contraseña</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:900px;
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

    .box{
      margin-top:16px;
      padding:16px;
      border-radius:16px;
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
    }

    .box p{
      margin:0 0 10px;
    }

    .box p:last-child{
      margin-bottom:0;
    }

    .pw{
      display:inline-block;
      margin-top:6px;
      padding:10px 14px;
      border-radius:12px;
      background:rgba(255,255,255,.06);
      border:1px solid var(--line);
      font-size:20px;
      font-weight:900;
      letter-spacing:.5px;
      color:#C7CBB8;
      word-break:break-all;
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

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:18px;
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

    .status-ok{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
      color:var(--accent);
      font-weight:800;
      font-size:13px;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Contraseña restablecida</h2>
        <div class="status-ok">✅ Restablecimiento exitoso</div>
        <p style="margin:12px 0 0;color:var(--muted);">
          El docente estará obligado a cambiarla en su próximo inicio de sesión.
        </p>
      </div>
    </div>

    <div class="box">
      <p><b>Docente:</b> <?= h($u['name']) ?></p>
      <p><b>Correo:</b> <?= h($u['email']) ?></p>
      <p><b>Nueva contraseña temporal:</b></p>
      <div class="pw"><?= h($newPlain) ?></div>
    </div>

    <div class="note">
      Guardá esta contraseña temporal y compartila únicamente con el docente correspondiente.
      En el próximo acceso, el sistema le pedirá cambiarla obligatoriamente.
    </div>

    <div class="actions">
      <a class="btnS" href="teachers.php">← Volver a docentes</a>
      <a class="btnG" href="dashboard.php">Ir al dashboard</a>
    </div>
  </section>
</main>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('appSidebar');
  if (!sidebar) return;

  if (window.innerWidth <= 960) {
    sidebar.classList.toggle('open');
  } else {
    sidebar.classList.toggle('collapsed');
  }
}
</script>
</body>
</html>