<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit("Falta id");
}

$stmt = $pdo->prepare("
  SELECT
    s.*,
    u.name AS teacher_name,
    u.email AS teacher_email,
    sc.name AS school_name,
    d.name AS department_name,
    m.name AS municipality_name,
    so.organization_type,
    so.organization_name,
    so.organization_phone,
    so.organization_location,
    so.characterization,
    so.trademark_registration,
    so.number_of_members,
    sp.course_purpose,
    sp.future_projection,
    sp.observations,
    sp.notes
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  LEFT JOIN schools sc ON sc.id = s.school_id
  LEFT JOIN departments d ON d.id = s.department_id
  LEFT JOIN municipalities m ON m.id = s.municipality_id
  LEFT JOIN student_organizations so ON so.student_id = s.id
  LEFT JOIN student_profiles sp ON sp.student_id = s.id
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
  http_response_code(404);
  exit("Estudiante no encontrado");
}

// Permiso: docente solo ve los suyos
if (($me['role'] ?? '') === 'teacher' && (int)$s['teacher_id'] !== (int)$me['id']) {
  http_response_code(403);
  exit("Acceso denegado");
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}

function sexLabel($v){
  if ($v === 'masculino') return 'Masculino';
  if ($v === 'femenino') return 'Femenino';
  return '—';
}

function educationLabel($v){
  if ($v === 'secundaria') return 'Secundaria';
  if ($v === 'tecnico') return 'Técnico';
  if ($v === 'universitario') return 'Universitario';
  return '—';
}

function organizationTypeLabel($v){
  if ($v === 'institucion') return 'Institución';
  if ($v === 'privado') return 'Privado';
  if ($v === 'emprendimiento') return 'Emprendimiento';
  if ($v === 'estudiante') return 'Estudiante';
  if ($v === 'productor') return 'Productor';
  return '—';
}

function yesNoLabel($v){
  if ($v === 'si') return 'Sí';
  if ($v === 'no') return 'No';
  return '—';
}

function studentStatusClass($status){
  if ($status === 'aprobado') return 'ok';
  if ($status === 'desaprobado') return 'bad';
  return 'pending';
}

function groupStatusClass($status){
  if ($status === 'activo') return 'ok';
  if ($status === 'finalizado') return 'bad';
  return 'pending';
}

// =====================================================
// Resumen de grupos + notas + asistencia por grupo
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    g.id,
    g.group_code,
    g.name,
    g.course_type,
    g.course_level,
    g.status AS group_status,
    g.start_date,
    g.end_date,
    g.created_at,
    u.name AS group_teacher_name,
    sg.exam1,
    sg.exam2,
    sg.exam3,
    sg.exam4,
    sg.exam5,
    sg.final_grade,
    sg.status AS academic_status,
    COUNT(ai.id) AS attendance_total,
    SUM(CASE WHEN ai.present = 1 THEN 1 ELSE 0 END) AS attendance_present
  FROM group_students gs
  JOIN groups_table g ON g.id = gs.group_id
  JOIN users u ON u.id = g.teacher_id
  LEFT JOIN student_grades sg
    ON sg.group_id = g.id
   AND sg.student_id = gs.student_id
  LEFT JOIN attendances a
    ON a.group_id = g.id
  LEFT JOIN attendance_items ai
    ON ai.attendance_id = a.id
   AND ai.student_id = gs.student_id
  WHERE gs.student_id = ?
  GROUP BY
    g.id, g.group_code, g.name, g.course_type, g.course_level, g.status,
    g.start_date, g.end_date, g.created_at, u.name,
    sg.exam1, sg.exam2, sg.exam3, sg.exam4, sg.exam5, sg.final_grade, sg.status
  ORDER BY g.created_at DESC, g.id DESC
");
$stmt->execute([$id]);
$academicGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// Resumen general de asistencia
// =====================================================
$stmt = $pdo->prepare("
  SELECT
    COUNT(ai.id) AS total_records,
    SUM(CASE WHEN ai.present = 1 THEN 1 ELSE 0 END) AS total_present
  FROM attendance_items ai
  WHERE ai.student_id = ?
");
$stmt->execute([$id]);
$attendanceSummary = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAttendanceRecords = (int)($attendanceSummary['total_records'] ?? 0);
$totalPresent = (int)($attendanceSummary['total_present'] ?? 0);
$overallAttendancePercent = $totalAttendanceRecords > 0
  ? round(($totalPresent / $totalAttendanceRecords) * 100, 2)
  : null;

$totalGroups = count($academicGroups);
$averageSum = 0;
$averageCount = 0;
$latestAcademicStatus = 'pendiente';

if ($academicGroups) {
  $latestAcademicStatus = $academicGroups[0]['academic_status'] ?: 'pendiente';
}

foreach ($academicGroups as $g) {
  if ($g['final_grade'] !== null && $g['final_grade'] !== '') {
    $averageSum += (float)$g['final_grade'];
    $averageCount++;
  }
}

$overallAcademicAverage = $averageCount > 0 ? round($averageSum / $averageCount, 2) : null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Expediente • <?= h($s['full_name']) ?></title>
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

    .btn2{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      font-weight:800;
      text-decoration:none;
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      text-decoration:none;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(148,163,184,.35);
      background:rgba(255,255,255,.06);
      color:rgba(255,255,255,.92);
      font-weight:800;
      text-decoration:none;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:12px;
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
      margin:0;
      font-weight:800;
      color:#e5e7eb;
    }

    .muted{
      color:var(--muted);
    }

    .titleRow{
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .boxNote{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,.04);
    }

    .boxNote p{
      margin:0;
      color:rgba(255,255,255,.88);
      line-height:1.7;
    }

    .tag{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      color:#e5e7eb;
      background:rgba(255,255,255,.04);
    }

    .section-title{
      font-size:15px;
      font-weight:900;
      margin:18px 0 10px;
      color:#e5e7eb;
    }

    .summary-card{
      border:1px solid var(--line);
      border-radius:16px;
      padding:16px;
      background:rgba(255,255,255,.05);
    }

    .summary-card .num{
      font-size:28px;
      font-weight:900;
      color:#e5e7eb;
      line-height:1;
      margin:4px 0 0;
    }

    .status-pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:110px;
      padding:8px 10px;
      border-radius:999px;
      font-size:12px;
      font-weight:800;
      text-transform:capitalize;
      border:1px solid var(--line);
    }

    .status-pill.ok{
      color:#22c55e;
      border-color:rgba(34,197,94,.35);
      background:rgba(34,197,94,.10);
    }

    .status-pill.bad{
      color:#ef4444;
      border-color:rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
    }

    .status-pill.pending{
      color:#f59e0b;
      border-color:rgba(245,158,11,.35);
      background:rgba(245,158,11,.10);
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:10px;
    }

    table{
      width:100%;
      min-width:1080px;
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
      font-weight:700;
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
    <div class="titleRow">
      <div>
        <h2 style="margin:0 0 6px;">Expediente del estudiante</h2>
        <div class="muted">
          ID interno: <b><?= (int)$s['id'] ?></b>
          <?php if (!empty($s['student_code'])): ?>
            • Código: <span class="tag"><?= h($s['student_code']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="actions">
        <a class="btnG" href="students.php">← Volver</a>
        <a class="btnS" href="student_form.php?id=<?= (int)$s['id'] ?>">Editar</a>
        <a class="btn2" href="student_letter.php?id=<?= (int)$s['id'] ?>" target="_blank">Constancia</a>
      </div>
    </div>

    <hr style="border:0;border-top:1px solid var(--line);margin:14px 0;">

    <div class="grid">
      <div class="kv col12">
        <p class="k">Nombre completo</p>
        <p class="v" style="font-size:18px;"><?= h($s['full_name']) ?></p>
        <p class="muted" style="margin:8px 0 0;">
          <?= h($s['school_name'] ?: 'Escuela no especificada') ?>
        </p>
      </div>

      <div class="section-title col12">Resumen académico</div>

      <div class="summary-card col3">
        <p class="k">Promedio general por grupos</p>
        <div class="num"><?= $overallAcademicAverage !== null ? h($overallAcademicAverage) : '—' ?></div>
      </div>

      <div class="summary-card col3">
        <p class="k">Último estado académico</p>
        <div style="margin-top:8px;">
          <span class="status-pill <?= h(studentStatusClass($latestAcademicStatus)) ?>">
            <?= h($latestAcademicStatus ?: 'pendiente') ?>
          </span>
        </div>
      </div>

      <div class="summary-card col3">
        <p class="k">Asistencia general</p>
        <div class="num"><?= $overallAttendancePercent !== null ? h($overallAttendancePercent . '%') : '—' ?></div>
        <div class="small">
          <?= $totalAttendanceRecords > 0 ? h($totalPresent . ' de ' . $totalAttendanceRecords . ' registros') : 'Sin registros de asistencia' ?>
        </div>
      </div>

      <div class="summary-card col3">
        <p class="k">Grupos asignados</p>
        <div class="num"><?= (int)$totalGroups ?></div>
      </div>

      <div class="section-title col12">Información personal</div>

      <div class="kv col4">
        <p class="k">Sexo</p>
        <p class="v"><?= h(sexLabel($s['sex'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nivel escolar</p>
        <p class="v"><?= h(educationLabel($s['education_level'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nacionalidad</p>
        <p class="v"><?= h($s['nationality'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Profesión</p>
        <p class="v"><?= h($s['profession'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Caracterización</p>
        <p class="v"><?= h($s['characterization'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Teléfono</p>
        <p class="v"><?= h($s['phone'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Cédula</p>
        <p class="v"><?= h($s['cedula'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Formación</div>

      <div class="kv col6">
        <p class="k">Curso principal</p>
        <p class="v"><?= h(courseLabel($s['course_type'] ?? 'barismo')) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Nivel principal</p>
        <p class="v"><?= h(levelLabel($s['course_level'] ?? 'basico')) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Fecha de inscripción</p>
        <p class="v"><?= h($s['enrolled_at'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Propósito del curso</p>
        <p class="v"><?= h($s['course_purpose'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Trayectoria académica</div>

      <div class="col12">
        <?php if ($academicGroups): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Grupo</th>
                  <th>Curso</th>
                  <th>Docente</th>
                  <th>Notas registradas</th>
                  <th>Nota final</th>
                  <th>Estado académico</th>
                  <th>Asistencia</th>
                  <th>Estado del grupo</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($academicGroups as $g): ?>
                  <?php
                    $attendanceTotal = (int)($g['attendance_total'] ?? 0);
                    $attendancePresent = (int)($g['attendance_present'] ?? 0);
                    $attendancePercent = $attendanceTotal > 0
                      ? round(($attendancePresent / $attendanceTotal) * 100, 2)
                      : null;

                    $gradesList = [];
                    foreach (['exam1','exam2','exam3','exam4','exam5'] as $examKey) {
                      if ($g[$examKey] !== null && $g[$examKey] !== '') {
                        $gradesList[] = $g[$examKey];
                      }
                    }

                    $academicStatus = $g['academic_status'] ?: 'pendiente';
                  ?>
                  <tr>
                    <td>
                      <strong><?= h($g['group_code']) ?></strong>
                      <div class="small"><?= h($g['name']) ?></div>
                    </td>
                    <td>
                      <?= h(courseLabel($g['course_type'])) ?> / <?= h(levelLabel($g['course_level'])) ?>
                      <div class="small">
                        <?= h($g['start_date'] ?: '—') ?> a <?= h($g['end_date'] ?: '—') ?>
                      </div>
                    </td>
                    <td><?= h($g['group_teacher_name']) ?></td>
                    <td><?= $gradesList ? h(implode(' / ', $gradesList)) : '—' ?></td>
                    <td><?= h($g['final_grade'] !== null && $g['final_grade'] !== '' ? $g['final_grade'] : '—') ?></td>
                    <td>
                      <span class="status-pill <?= h(studentStatusClass($academicStatus)) ?>">
                        <?= h($academicStatus) ?>
                      </span>
                    </td>
                    <td>
                      <?= $attendancePercent !== null ? h($attendancePercent . '%') : '—' ?>
                      <div class="small">
                        <?= $attendanceTotal > 0 ? h($attendancePresent . '/' . $attendanceTotal . ' presentes') : 'Sin asistencias registradas' ?>
                      </div>
                    </td>
                    <td>
                      <span class="status-pill <?= h(groupStatusClass($g['group_status'] ?? '')) ?>">
                        <?= h($g['group_status'] ?: '—') ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty">Este estudiante todavía no tiene grupos asignados.</div>
        <?php endif; ?>
      </div>

      <div class="section-title col12">Ubicación</div>

      <div class="kv col4">
        <p class="k">Departamento</p>
        <p class="v"><?= h($s['department_name'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Municipio</p>
        <p class="v"><?= h($s['municipality_name'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Comunidad</p>
        <p class="v"><?= h($s['community'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Organización</div>

      <div class="kv col4">
        <p class="k">Tipo de organización</p>
        <p class="v"><?= h(organizationTypeLabel($s['organization_type'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nombre de organización</p>
        <p class="v"><?= h($s['organization_name'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Teléfono de organización</p>
        <p class="v"><?= h($s['organization_phone'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Ubicación de organización</p>
        <p class="v"><?= h($s['organization_location'] ?: '—') ?></p>
      </div>

      <div class="kv col3">
        <p class="k">Registro de marca</p>
        <p class="v"><?= h(yesNoLabel($s['trademark_registration'] ?? '')) ?></p>
      </div>

      <div class="kv col3">
        <p class="k">Número de socios</p>
        <p class="v"><?= h($s['number_of_members'] !== null && $s['number_of_members'] !== '' ? $s['number_of_members'] : '—') ?></p>
      </div>

      <div class="section-title col12">Proyección y observaciones</div>

      <div class="col12">
        <div class="boxNote">
          <p class="k" style="margin-bottom:10px;">Proyección a futuro</p>
          <p><?= h($s['future_projection'] ?: '—') ?></p>
        </div>
      </div>

      <div class="col12">
        <div class="boxNote">
          <p class="k" style="margin-bottom:10px;">Observaciones</p>
          <p><?= h($s['observations'] ?: '—') ?></p>
        </div>
      </div>

      <div class="col12">
        <div class="boxNote">
          <p class="k" style="margin-bottom:10px;">Notas internas</p>
          <p><?= h($s['notes'] ?: '—') ?></p>
        </div>
      </div>

      <?php if (($me['role'] ?? '') === 'admin'): ?>
        <div class="section-title col12">Control administrativo</div>

        <div class="kv col12">
          <p class="k">Docente asignado</p>
          <p class="v"><?= h($s['teacher_name']) ?></p>
          <p class="muted" style="margin:8px 0 0;"><?= h($s['teacher_email']) ?></p>
        </div>
      <?php endif; ?>

      <div class="col12 muted" style="font-size:12px;">
        Registrado: <b><?= h($s['created_at'] ?? '—') ?></b>
        <?php if (!empty($s['updated_at'])): ?>
          • Última actualización: <b><?= h($s['updated_at']) ?></b>
        <?php endif; ?>
      </div>
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