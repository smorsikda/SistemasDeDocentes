<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';


requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function courseLabel($t){
    return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
    if ($l === 'avanzado') return 'Avanzado';
    if ($l === 'intensivo') return 'Intensivo';
    return 'Básico';
}

$where = "";
$params = [];

if (($me['role'] ?? '') === 'teacher') {
    $where = "WHERE g.teacher_id = ?";
    $params[] = (int)$me['id'];
}

$sql = "
SELECT
    g.id,
    g.group_code,
    g.name,
    g.course_type,
    g.course_level,
    g.schedule,
    g.location,
    g.status,
    g.teacher_id,
    g.created_at,
    u.name AS teacher_name,
    COUNT(gs.student_id) AS total_students
FROM groups_table g
JOIN users u
    ON u.id = g.teacher_id
LEFT JOIN group_students gs
    ON gs.group_id = g.id
$where
GROUP BY
    g.id,
    g.group_code,
    g.name,
    g.course_type,
    g.course_level,
    g.schedule,
    g.location,
    g.status,
    g.teacher_id,
    g.created_at,
    u.name
ORDER BY
    CASE
      WHEN g.status = 'activo' THEN 0
      WHEN g.status = 'finalizado' THEN 1
      ELSE 2
    END,
    g.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$finalized = isset($_GET['finalized']) && $_GET['finalized'] == '1';

$countActive = 0;
$countDone = 0;
$countCancelled = 0;

foreach ($groups as $g) {
    $status = (string)($g['status'] ?? '');
    if ($status === 'activo') $countActive++;
    elseif ($status === 'finalizado') $countDone++;
    elseif ($status === 'cancelado') $countCancelled++;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Grupos</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1280px;
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
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
    }

    .muted{
      color:var(--muted);
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
      color:var(--accent);
      font-weight:800;
      text-decoration:none;
      margin-right:6px;
      margin-bottom:6px;
      cursor:pointer;
    }

    .btnDanger{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
      color:#fca5a5;
      font-weight:800;
      text-decoration:none;
      margin-right:6px;
      margin-bottom:6px;
      cursor:pointer;
    }

    .btnMuted{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(61,67,55,.25);
      background:rgba(255,255,255,.04);
      color:#C7CBB8;
      font-weight:700;
      margin-right:6px;
      margin-bottom:6px;
    }

    .pill{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      text-transform:capitalize;
    }

    .activo{
      border-color:rgba(107,127,103,.45);
      color:var(--accent);
    }

    .finalizado{
      border-color:rgba(239,68,68,.45);
      color:#fca5a5;
    }

    .cancelado{
      border-color:rgba(61,67,55,.45);
      color:#C7CBB8;
    }

    .ok-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(107,127,103,.10);
      border:1px solid rgba(107,127,103,.35);
      color:var(--accent);
      font-weight:700;
    }

    form.inline{
      display:inline;
    }

    .stats{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      margin-top:12px;
      margin-bottom:8px;
    }

    .stat{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      color:#C7CBB8;
      font-size:13px;
      font-weight:700;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:14px;
    }

    table{
      width:100%;
      min-width:1100px;
      border-collapse:collapse;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:14px;
      vertical-align:top;
    }

    th{
      color:var(--muted);
      background:rgba(255,255,255,.04);
      white-space:nowrap;
    }

    tr:hover{
      background:rgba(255,255,255,.04);
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Grupos</h2>
        <p style="margin:0;color:var(--muted);">
          <?= ($me['role'] === 'admin') ? 'Vista global de grupos' : 'Tus grupos asignados' ?>
        </p>

        <div class="stats">
          <div class="stat">Total grupos: <?= count($groups) ?></div>
          <div class="stat">Activos: <?= $countActive ?></div>
          <div class="stat">Finalizados: <?= $countDone ?></div>
          <div class="stat">Cancelados: <?= $countCancelled ?></div>
        </div>
      </div>

      <a class="btnS" href="group_new.php">+ Crear grupo</a>
    </div>

    <?php if ($finalized): ?>
      <div class="ok-msg">Grupo finalizado correctamente.</div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Curso</th>
            <th>Nivel</th>
            <th>Horario</th>
            <th>Ubicación</th>
            <th>Estudiantes</th>
            <?php if (($me['role'] ?? '') === 'admin'): ?>
              <th>Docente</th>
            <?php endif; ?>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><b><?= h($g['group_code']) ?></b></td>
              <td><?= h($g['name']) ?></td>
              <td><?= h(courseLabel($g['course_type'])) ?></td>
              <td><?= h(levelLabel($g['course_level'])) ?></td>
              <td><?= h($g['schedule'] ?: '—') ?></td>
              <td><?= h($g['location'] ?: '—') ?></td>
              <td><?= (int)$g['total_students'] ?></td>

              <?php if (($me['role'] ?? '') === 'admin'): ?>
                <td><?= h($g['teacher_name']) ?></td>
              <?php endif; ?>

              <td>
                <span class="pill <?= h($g['status']) ?>">
                  <?= h($g['status']) ?>
                </span>
              </td>

              <td>
                <a class="btnS" href="group_students.php?id=<?= (int)$g['id'] ?>">Ingresar Estudiantes</a>
                <a class="btnS" href="group_profile.php?id=<?= (int)$g['id'] ?>">Ver grupo</a>
                <a class="btnS" href="grades.php?group_id=<?= (int)$g['id'] ?>">Notas</a>
                <a class="btnS" href="group_report.php?id=<?= (int)$g['id'] ?>">Reporte</a>

                <?php if (($g['status'] ?? '') === 'activo'): ?>
                  <a class="btnS" href="attendance.php?group_id=<?= (int)$g['id'] ?>">Asistencia</a>

                  <form class="inline" method="post" action="group_finalize.php" onsubmit="return confirm('¿Seguro que deseas finalizar este grupo?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button class="btnDanger" type="submit">Finalizar</button>
                  </form>
                <?php else: ?>
                  <span class="btnMuted">Grupo cerrado</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$groups): ?>
            <tr>
              <td colspan="<?= (($me['role'] ?? '') === 'admin') ? 10 : 9 ?>" class="muted">
                No hay grupos todavía.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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