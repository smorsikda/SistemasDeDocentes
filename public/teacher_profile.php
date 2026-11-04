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

function teacherStatusClass($isActive){
  return (int)$isActive === 1 ? 'ok' : 'bad';
}

function groupStatusClass($status){
  if ($status === 'activo') return 'ok';
  if ($status === 'finalizado') return 'bad';
  return 'pending';
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  exit('Docente inválido');
}

// =====================================================
// Traer docente
// =====================================================
$stmt = $pdo->prepare("
  SELECT id, name, email, role, is_active, must_change_password, created_at
  FROM users
  WHERE id = ? AND role = 'teacher'
  LIMIT 1
");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$t) {
  http_response_code(404);
  exit('Docente no encontrado');
}

// Permisos: docente solo puede verse a sí mismo
if (($me['role'] ?? '') === 'teacher' && (int)$me['id'] !== (int)$t['id']) {
  http_response_code(403);
  exit('Acceso denegado');
}

// =====================================================
// Métricas de grupos
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    COUNT(*) AS total_groups,
    SUM(CASE WHEN status = 'activo' THEN 1 ELSE 0 END) AS active_groups,
    SUM(CASE WHEN status = 'finalizado' THEN 1 ELSE 0 END) AS finished_groups
  FROM groups_table
  WHERE teacher_id = ?
");
$stmt->execute([$id]);
$groupMetrics = $stmt->fetch(PDO::FETCH_ASSOC);

$totalGroups = (int)($groupMetrics['total_groups'] ?? 0);
$activeGroups = (int)($groupMetrics['active_groups'] ?? 0);
$finishedGroups = (int)($groupMetrics['finished_groups'] ?? 0);

// =====================================================
// Total de estudiantes del docente
// =====================================================
$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM students
  WHERE teacher_id = ?
");
$stmt->execute([$id]);
$totalStudents = (int)$stmt->fetchColumn();

// =====================================================
// Promedio general de notas en grupos del docente
// =====================================================
$stmt = $pdo->prepare("
  SELECT AVG(sg.final_grade)
  FROM student_grades sg
  JOIN groups_table g ON g.id = sg.group_id
  WHERE g.teacher_id = ?
    AND sg.final_grade IS NOT NULL
");
$stmt->execute([$id]);
$generalAverage = $stmt->fetchColumn();
$generalAverage = $generalAverage !== null ? round((float)$generalAverage, 2) : null;

// =====================================================
// Listado de grupos del docente
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    g.id,
    g.group_code,
    g.name,
    g.course_type,
    g.course_level,
    g.status,
    g.start_date,
    g.end_date,
    (
      SELECT COUNT(*)
      FROM group_students gs
      WHERE gs.group_id = g.id
    ) AS total_students,
    (
      SELECT ROUND(AVG(sg.final_grade), 2)
      FROM student_grades sg
      WHERE sg.group_id = g.id
        AND sg.final_grade IS NOT NULL
    ) AS avg_grade
  FROM groups_table g
  WHERE g.teacher_id = ?
  ORDER BY
    CASE
      WHEN g.status = 'activo' THEN 0
      WHEN g.status = 'finalizado' THEN 1
      ELSE 2
    END,
    g.created_at DESC,
    g.id DESC
");
$stmt->execute([$id]);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Perfil del docente</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1280px;
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

    .grid{
      display:grid;
      grid-template-columns:repeat(12,1fr);
      gap:14px;
    }

    .col3{grid-column:span 3}
    .col4{grid-column:span 4}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}

    @media(max-width:960px){
      .col3,.col4,.col6{grid-column:span 12}
    }

    .kv{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,.05);
    }

    .k{
      font-size:12px;
      color:var(--muted);
      margin:0 0 6px;
    }

    .v{
      font-weight:800;
      color:#e5e7eb;
      margin:0;
    }

    .section{
      font-size:15px;
      font-weight:900;
      margin:18px 0 10px;
      color:#e5e7eb;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:20px;
    }

    .btnS,.btnG,.btn2{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .btnS{
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
    }

    .btnG{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
    }

    .btn2{
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      border:none;
    }

    .stat{
      padding:16px;
      border-radius:14px;
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      text-align:center;
    }

    .stat b{
      font-size:22px;
      display:block;
      color:#e5e7eb;
      margin-bottom:6px;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:10px;
    }

    table{
      width:100%;
      min-width:980px;
      border-collapse:collapse;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:13px;
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

    .small{
      font-size:12px;
      color:var(--muted);
      margin-top:4px;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:90px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      font-weight:800;
      text-transform:capitalize;
    }

    .pill.ok{
      border-color:rgba(34,197,94,.35);
      color:#22c55e;
      background:rgba(34,197,94,.10);
    }

    .pill.bad{
      border-color:rgba(239,68,68,.35);
      color:#fca5a5;
      background:rgba(239,68,68,.10);
    }

    .pill.pending{
      border-color:rgba(245,158,11,.35);
      color:#f59e0b;
      background:rgba(245,158,11,.10);
    }

    .empty{
      padding:18px;
      text-align:center;
      color:var(--muted);
      font-weight:700;
      border:1px dashed var(--line);
      border-radius:14px;
      background:rgba(255,255,255,.03);
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">

    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Perfil del docente</h2>
        <p style="margin:0;color:var(--muted);">
          Información general y métricas académicas del docente.
        </p>
      </div>

      <div class="actions" style="margin-top:0;">
        <a class="btnG" href="teachers.php">← Volver</a>
        <form method="post" action="teacher_reset.php" style="display:inline;">
  <?= csrf_input() ?>
  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
  <button class="btnS" type="submit">Restablecer contraseña</button>
</form>
      </div>
    </div>

    <div class="grid" style="margin-top:16px;">
      <div class="kv col6">
        <p class="k">Nombre completo</p>
        <p class="v"><?= h($t['name']) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Correo</p>
        <p class="v"><?= h($t['email']) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Estado</p>
        <p class="v">
          <span class="pill <?= h(teacherStatusClass($t['is_active'] ?? 0)) ?>">
            <?= ((int)($t['is_active'] ?? 0) === 1) ? 'activo' : 'inactivo' ?>
          </span>
        </p>
      </div>

      <div class="kv col4">
        <p class="k">Cambio de contraseña</p>
        <p class="v"><?= ((int)($t['must_change_password'] ?? 0) === 1) ? 'Pendiente' : 'Completado' ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Fecha de registro</p>
        <p class="v"><?= h($t['created_at'] ?: '—') ?></p>
      </div>
    </div>

    <h3 class="section">Métricas del docente</h3>

    <div class="grid">
      <div class="stat col3">
        <b><?= (int)$totalGroups ?></b>
        Total grupos
      </div>

      <div class="stat col3">
        <b><?= (int)$activeGroups ?></b>
        Grupos activos
      </div>

      <div class="stat col3">
        <b><?= (int)$finishedGroups ?></b>
        Grupos finalizados
      </div>

      <div class="stat col3">
        <b><?= (int)$totalStudents ?></b>
        Estudiantes asignados
      </div>

      <div class="stat col12">
        <b><?= $generalAverage !== null ? h($generalAverage) : '—' ?></b>
        Promedio general de notas
      </div>
    </div>

    <h3 class="section">Grupos asignados</h3>

    <?php if ($groups): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Grupo</th>
              <th>Curso / Nivel</th>
              <th>Estudiantes</th>
              <th>Promedio</th>
              <th>Estado</th>
              <th>Fechas</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $g): ?>
              <tr>
                <td><strong><?= h($g['group_code']) ?></strong></td>
                <td><?= h($g['name']) ?></td>
                <td><?= h(courseLabel($g['course_type'])) ?> / <?= h(levelLabel($g['course_level'])) ?></td>
                <td><?= (int)$g['total_students'] ?></td>
                <td><?= h($g['avg_grade'] !== null && $g['avg_grade'] !== '' ? $g['avg_grade'] : '—') ?></td>
                <td>
                  <span class="pill <?= h(groupStatusClass($g['status'] ?? '')) ?>">
                    <?= h($g['status'] ?: '—') ?>
                  </span>
                </td>
                <td>
                  <div><?= h($g['start_date'] ?: '—') ?></div>
                  <div class="small">hasta <?= h($g['end_date'] ?: '—') ?></div>
                </td>
                <td>
                  <a class="btnS" href="group_profile.php?id=<?= (int)$g['id'] ?>">Ver grupo</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">Este docente todavía no tiene grupos asignados.</div>
    <?php endif; ?>

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