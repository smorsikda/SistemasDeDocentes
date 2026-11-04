<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';


requireRole('admin');
requirePasswordChangeIfNeeded();

$teachers = $pdo->query("
  SELECT id, name, email, is_active, created_at
  FROM users
  WHERE role='teacher'
  ORDER BY id DESC
")->fetchAll();

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Docentes</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1200px;
      width:100%;
      margin:0 auto;
    }

    .panel{
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow);
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:8px;
    }

    .muted{
      color:var(--muted);
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

    .btnD{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:9px 12px;
      border-radius:12px;
      border:1px solid rgba(255,90,95,.35);
      background:rgba(255,90,95,.10);
      color:rgba(255,255,255,.92);
      font-weight:700;
      text-decoration:none;
      cursor:pointer;
    }

    .summary{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
      margin:16px 0 8px;
    }

    .card{
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
    }

    .card .k{
      font-size:12px;
      color:var(--muted);
    }

    .card .v{
      font-size:28px;
      font-weight:900;
      margin-top:6px;
    }

    table{
      width:100%;
      border-collapse:collapse;
      margin-top:16px;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:14px;
      vertical-align:middle;
    }

    th{
      color:var(--muted);
      font-weight:700;
      background:rgba(255,255,255,.04);
    }

    tr:hover{
      background:rgba(255,255,255,.04);
    }

    .pill{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
    }

    .on{
      border-color:rgba(47,191,113,.35);
      color:var(--green);
    }

    .off{
      border-color:rgba(255,90,95,.35);
      color:rgba(255,255,255,.88);
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    @media(max-width:860px){
      .summary{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Gestión de docentes</h2>
        <p style="margin:0;color:var(--muted);">
          Crear, listar y restablecer contraseñas.
        </p>
      </div>

      <a class="btnS" href="teacher_new.php">+ Crear docente</a>
    </div>

    <?php
      $totalTeachers = count($teachers);
      $activeTeachers = 0;
      $inactiveTeachers = 0;

      foreach ($teachers as $t) {
        if ((int)$t['is_active'] === 1) {
          $activeTeachers++;
        } else {
          $inactiveTeachers++;
        }
      }
    ?>

    <div class="summary">
      <div class="card">
        <div class="k">Total docentes</div>
        <div class="v"><?= (int)$totalTeachers ?></div>
      </div>

      <div class="card">
        <div class="k">Activos</div>
        <div class="v"><?= (int)$activeTeachers ?></div>
      </div>

      <div class="card">
        <div class="k">Inactivos</div>
        <div class="v"><?= (int)$inactiveTeachers ?></div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:80px;">ID</th>
          <th>Docente</th>
          <th>Email</th>
          <th style="width:120px;">Activo</th>
          <th style="width:190px;">Creado</th>
          <th style="width:260px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($teachers as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td>
              <div style="font-weight:800;color:#e5e7eb;">
                <?= h($t['name']) ?>
              </div>
            </td>
            <td><?= h($t['email']) ?></td>
            <td>
              <?php if ((int)$t['is_active'] === 1): ?>
                <span class="pill on">Sí</span>
              <?php else: ?>
                <span class="pill off">No</span>
              <?php endif; ?>
            </td>
            <td><?= h($t['created_at']) ?></td>
            <td>
              <div class="actions">
                <form method="post" action="teacher_reset.php" style="display:inline;">
  <?= csrf_input() ?>
  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
  <button class="btnD" type="submit">Restablecer contraseña</button>
</form>
                <a class="btnS" href="teacher_profile.php?id=<?= (int)$t['id'] ?>">Ver perfil</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if(!$teachers): ?>
          <tr>
            <td colspan="6" style="color:var(--muted);">No hay docentes todavía.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
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
</div>
</div>
</body>
</html>