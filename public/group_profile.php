<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';

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

function statusClass($status){
  if ($status === 'activo') return 'ok';
  if ($status === 'finalizado') return 'bad';
  if ($status === 'cancelado') return 'cancel';
  return 'pending';
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  exit('Grupo inválido');
}

// =====================================================
// Traer grupo
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    g.*,
    u.name AS teacher_name,
    u.email AS teacher_email
  FROM groups_table g
  JOIN users u ON u.id = g.teacher_id
  WHERE g.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$g) {
  http_response_code(404);
  exit('Grupo no encontrado');
}

// Permisos
if (($me['role'] ?? '') === 'teacher' && (int)$g['teacher_id'] !== (int)$me['id']) {
  http_response_code(403);
  exit('Acceso denegado');
}

// =====================================================
// Total estudiantes
// =====================================================
$stmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM group_students
  WHERE group_id = ?
");
$stmt->execute([$id]);
$totalStudents = (int)$stmt->fetchColumn();

// =====================================================
// Promedio del grupo desde student_grades
// =====================================================
$stmt = $pdo->prepare("
  SELECT AVG(final_grade)
  FROM student_grades
  WHERE group_id = ? AND final_grade IS NOT NULL
");
$stmt->execute([$id]);
$avg = $stmt->fetchColumn();
$avg = $avg !== null ? round((float)$avg, 2) : null;

// =====================================================
// Estados académicos de estudiantes del grupo
// =====================================================
$stmt = $pdo->prepare("
  SELECT s.status, COUNT(*) AS total
  FROM group_students gs
  JOIN students s ON s.id = gs.student_id
  WHERE gs.group_id = ?
  GROUP BY s.status
");
$stmt->execute([$id]);
$statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$approved = 0;
$failed = 0;
$pending = 0;

foreach ($statusRows as $row) {
  $status = (string)($row['status'] ?? '');
  if ($status === 'aprobado') {
    $approved = (int)$row['total'];
  } elseif ($status === 'desaprobado') {
    $failed = (int)$row['total'];
  } else {
    $pending = (int)$row['total'];
  }
}

// =====================================================
// Asistencia promedio del grupo
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    COUNT(ai.id) AS total_records,
    SUM(CASE WHEN ai.present = 1 THEN 1 ELSE 0 END) AS total_present
  FROM attendances a
  JOIN attendance_items ai ON ai.attendance_id = a.id
  WHERE a.group_id = ?
");
$stmt->execute([$id]);
$attendanceSummary = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAttendanceRecords = (int)($attendanceSummary['total_records'] ?? 0);
$totalPresent = (int)($attendanceSummary['total_present'] ?? 0);

$attendanceAverage = $totalAttendanceRecords > 0
  ? round(($totalPresent / $totalAttendanceRecords) * 100, 2)
  : null;

// =====================================================
// Últimas asistencias del grupo
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    a.id,
    a.attendance_date,
    (
      SELECT COUNT(*)
      FROM attendance_items ai
      WHERE ai.attendance_id = a.id AND ai.present = 1
    ) AS total_present,
    (
      SELECT COUNT(*)
      FROM attendance_items ai
      WHERE ai.attendance_id = a.id AND ai.present = 0
    ) AS total_absent
  FROM attendances a
  WHERE a.group_id = ?
  ORDER BY a.attendance_date DESC, a.id DESC
  LIMIT 5
");
$stmt->execute([$id]);
$lastAttendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Perfil del grupo</title>
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
  min-width:760px;
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

.pill.cancel{
  border-color:rgba(148,163,184,.35);
  color:#cbd5e1;
  background:rgba(148,163,184,.10);
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

.note-box{
  border:1px solid var(--line);
  border-radius:14px;
  padding:14px;
  background:rgba(255,255,255,.04);
  color:rgba(255,255,255,.9);
  line-height:1.7;
}
</style>
</head>

<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
<section class="panel">

  <div class="hero">
    <div>
      <h2 style="margin:0 0 6px;">Perfil del grupo</h2>
      <p style="margin:0;color:var(--muted);">
        Expediente general del grupo y accesos rápidos a sus módulos.
      </p>
    </div>

    <div class="actions" style="margin-top:0;">
      <a class="btnG" href="groups.php">← Volver</a>
      <a class="btn2" href="group_report.php?id=<?= (int)$id ?>">Reporte consolidado</a>
    </div>
  </div>

  <div class="grid" style="margin-top:16px;">

    <div class="kv col6">
      <p class="k">Nombre del grupo</p>
      <p class="v"><?= h($g['name']) ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Código</p>
      <p class="v"><?= h($g['group_code']) ?></p>
    </div>

    <div class="kv col3">
      <p class="k">Curso</p>
      <p class="v"><?= h(courseLabel($g['course_type'])) ?></p>
    </div>

    <div class="kv col3">
      <p class="k">Nivel</p>
      <p class="v"><?= h(levelLabel($g['course_level'])) ?></p>
    </div>

    <div class="kv col3">
      <p class="k">Estado</p>
      <p class="v">
        <span class="pill <?= h(statusClass($g['status'] ?? '')) ?>">
          <?= h($g['status']) ?>
        </span>
      </p>
    </div>

    <div class="kv col3">
      <p class="k">Capacidad</p>
      <p class="v"><?= h($g['capacity'] !== null && $g['capacity'] !== '' ? $g['capacity'] : '—') ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Docente asignado</p>
      <p class="v"><?= h($g['teacher_name']) ?></p>
      <p class="small"><?= h($g['teacher_email']) ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Horario</p>
      <p class="v"><?= h($g['schedule'] ?: '—') ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Ubicación</p>
      <p class="v"><?= h($g['location'] ?: '—') ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Departamento</p>
      <p class="v"><?= h($g['department'] ?? '—') ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Fecha inicio</p>
      <p class="v"><?= h($g['start_date'] ?: '—') ?></p>
    </div>

    <div class="kv col6">
      <p class="k">Fecha fin</p>
      <p class="v"><?= h($g['end_date'] ?: '—') ?></p>
    </div>

  </div>

  <h3 class="section">Métricas del grupo</h3>

  <div class="grid">
    <div class="stat col3">
      <b><?= (int)$totalStudents ?></b>
      Total estudiantes
    </div>

    <div class="stat col3">
      <b><?= $avg !== null ? h($avg) : '—' ?></b>
      Promedio del grupo
    </div>

    <div class="stat col3">
      <b><?= $attendanceAverage !== null ? h($attendanceAverage . '%') : '—' ?></b>
      Asistencia promedio
    </div>

    <div class="stat col3">
      <b><?= (int)$approved ?></b>
      Aprobados
    </div>

    <div class="stat col4">
      <b><?= (int)$failed ?></b>
      Desaprobados
    </div>

    <div class="stat col4">
      <b><?= (int)$pending ?></b>
      Pendientes
    </div>

    <div class="stat col4">
      <b><?= (int)count($lastAttendances) ?></b>
      Últimas asistencias listadas
    </div>
  </div>

  <h3 class="section">Notas del grupo</h3>
  <div class="note-box">
    <?= h($g['notes'] ?: 'Sin notas registradas para este grupo.') ?>
  </div>

  <h3 class="section">Últimas asistencias</h3>

  <?php if ($lastAttendances): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Presentes</th>
            <th>Ausentes</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lastAttendances as $a): ?>
            <tr>
              <td><?= h($a['attendance_date']) ?></td>
              <td><?= (int)$a['total_present'] ?></td>
              <td><?= (int)$a['total_absent'] ?></td>
              <td>
                <a class="btnS" href="attendance_view.php?id=<?= (int)$a['id'] ?>">Ver detalle</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty">Este grupo todavía no tiene asistencias registradas.</div>
  <?php endif; ?>

  <h3 class="section">Acciones rápidas</h3>

  <div class="actions">
    <a class="btnS" href="group_students.php?id=<?= (int)$id ?>">Estudiantes</a>
    <a class="btnS" href="grades.php?group_id=<?= (int)$id ?>">Notas</a>
    <?php if (($g['status'] ?? '') === 'activo'): ?>
      <a class="btnS" href="attendance.php?group_id=<?= (int)$id ?>">Asistencia</a>
    <?php endif; ?>
    <a class="btnS" href="attendance_history.php">Historial</a>
    <a class="btnS" href="group_report.php?id=<?= (int)$id ?>">Reporte</a>
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