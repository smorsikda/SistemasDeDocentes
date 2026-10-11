<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

$q = trim($_GET['q'] ?? '');
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$courseType = trim($_GET['course_type'] ?? '');
$courseLevel = trim($_GET['course_level'] ?? '');
$department = trim($_GET['department'] ?? '');
$municipality = trim($_GET['municipality'] ?? '');
$organizationType = trim($_GET['organization_type'] ?? '');

$where = ["s.is_historical = 1"];
$params = [];

// Docente solo ve los suyos
if (($me['role'] ?? '') === 'teacher') {
  $where[] = "s.teacher_id = :me";
  $params[':me'] = (int)$me['id'];
} else {
  if ($teacherId > 0) {
    $where[] = "s.teacher_id = :tid";
    $params[':tid'] = $teacherId;
  }
}

// Filtros
if ($courseType !== '') {
  $where[] = "s.course_type = :ctype";
  $params[':ctype'] = $courseType;
}

if ($courseLevel !== '') {
  $where[] = "s.course_level = :clevel";
  $params[':clevel'] = $courseLevel;
}

if ($department !== '') {
  $where[] = "d.name LIKE :dept";
  $params[':dept'] = "%{$department}%";
}

if ($municipality !== '') {
  $where[] = "m.name LIKE :muni";
  $params[':muni'] = "%{$municipality}%";
}

if ($organizationType !== '') {
  $where[] = "so.organization_type = :otype";
  $params[':otype'] = $organizationType;
}

// Búsqueda general
if ($q !== '') {
  $where[] = "(
    s.full_name LIKE :q OR
    s.student_code LIKE :q OR
    sc.name LIKE :q OR
    s.cedula LIKE :q OR
    s.phone LIKE :q OR
    d.name LIKE :q OR
    m.name LIKE :q OR
    so.organization_name LIKE :q OR
    so.organization_location LIKE :q OR
    so.characterization LIKE :q OR
    s.profession LIKE :q
  )";
  $params[':q'] = "%{$q}%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Dropdown docentes (solo admin)
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='teacher'
    ORDER BY name ASC
  ")->fetchAll();
}

$sql = "
SELECT
  s.*,
  u.name AS teacher_name,
  sc.name AS school_name,
  d.name AS department_name,
  m.name AS municipality_name,
  so.organization_type,
  so.organization_name,
  so.organization_location,
  so.characterization
FROM students s
JOIN users u ON u.id = s.teacher_id
LEFT JOIN schools sc ON sc.id = s.school_id
LEFT JOIN departments d ON d.id = s.department_id
LEFT JOIN municipalities m ON m.id = s.municipality_id
LEFT JOIN student_organizations so ON so.student_id = s.id
{$whereSql}
ORDER BY s.id DESC
LIMIT 800
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function sexLabel($v){
  if ($v === 'masculino') return 'Masculino';
  if ($v === 'femenino') return 'Femenino';
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

function statusClass($status){
  if ($status === 'aprobado') return 'ok';
  if ($status === 'desaprobado') return 'bad';
  return 'pending';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estudiantes históricos</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1450px;
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

    .toolbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      flex-wrap:wrap;
    }

    .filters{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:end;
      margin-top:12px;
    }

    .filters .field{
      margin-top:0;
      min-width:180px;
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

    .btnD{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(255,90,95,.35);
      background:rgba(255,90,95,.10);
      color:rgba(255,255,255,.92);
      font-weight:800;
      text-decoration:none;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.12);
      color:var(--green);
      font-weight:700;
      text-decoration:none;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:14px;
    }

    table{
      width:100%;
      min-width:1380px;
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
    }

    tr:hover{
      background:rgba(255,255,255,.04);
    }

    .actions{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
    }

    .muted{
      color:var(--muted);
    }

    .small{
      font-size:12px;
      color:var(--muted);
    }

    .nowrap{
      white-space:nowrap;
    }

    .stats{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      margin-top:10px;
    }

    .stat{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      color:#e5e7eb;
      font-size:13px;
      font-weight:700;
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
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="toolbar">
      <div>
        <h2 style="margin:0 0 6px;">Estudiantes históricos</h2>
        <p style="margin:0;color:var(--muted);">
          Registro de estudiantes de años anteriores.
        </p>

        <div class="stats">
          <div class="stat">Resultados: <?= count($rows) ?></div>
          <?php if (($me['role'] ?? '') === 'admin'): ?>
            <div class="stat">Modo administrador</div>
          <?php else: ?>
            <div class="stat">Modo docente</div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btnS" href="students_export.php?<?= http_build_query($_GET) ?>">Descargar CSV</a>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="field">
        <label>Buscar</label>
        <input name="q" value="<?= h($q) ?>" placeholder="Nombre, código, escuela, organización, profesión...">
      </div>

      <div class="field" style="min-width:170px">
        <label>Curso</label>
        <select name="course_type">
          <option value="">Todos</option>
          <option value="barismo" <?= $courseType==='barismo'?'selected':'' ?>>Barismo</option>
          <option value="catacion" <?= $courseType==='catacion'?'selected':'' ?>>Catación</option>
        </select>
      </div>

      <div class="field" style="min-width:170px">
        <label>Nivel</label>
        <select name="course_level">
          <option value="">Todos</option>
          <option value="basico" <?= $courseLevel==='basico'?'selected':'' ?>>Básico</option>
          <option value="avanzado" <?= $courseLevel==='avanzado'?'selected':'' ?>>Avanzado</option>
          <option value="intensivo" <?= $courseLevel==='intensivo'?'selected':'' ?>>Intensivo</option>
        </select>
      </div>

      <div class="field" style="min-width:220px">
        <label>Departamento</label>
        <input name="department" value="<?= h($department) ?>" placeholder="Ej: Matagalpa">
      </div>

      <div class="field" style="min-width:220px">
        <label>Municipio</label>
        <input name="municipality" value="<?= h($municipality) ?>" placeholder="Ej: Matagalpa">
      </div>

      <div class="field" style="min-width:220px">
        <label>Tipo de organización</label>
        <select name="organization_type">
          <option value="">Todos</option>
          <option value="institucion" <?= $organizationType==='institucion'?'selected':'' ?>>Institución</option>
          <option value="privado" <?= $organizationType==='privado'?'selected':'' ?>>Privado</option>
          <option value="emprendimiento" <?= $organizationType==='emprendimiento'?'selected':'' ?>>Emprendimiento</option>
          <option value="estudiante" <?= $organizationType==='estudiante'?'selected':'' ?>>Estudiante</option>
          <option value="productor" <?= $organizationType==='productor'?'selected':'' ?>>Productor</option>
        </select>
      </div>

      <?php if (($me['role'] ?? '') === 'admin'): ?>
        <div class="field" style="min-width:260px">
          <label>Docente</label>
          <select name="teacher_id">
            <option value="0">Todos los docentes</option>
            <?php foreach($teachers as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $teacherId===(int)$t['id']?'selected':'' ?>>
                <?= h($t['name']) ?> (<?= h($t['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field" style="min-width:140px">
        <button class="btn" type="submit">Filtrar</button>
      </div>

      <div class="field" style="min-width:140px">
        <a class="btnD" href="students_historical.php">Limpiar</a>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <th style="width:150px;">Código</th>
            <th>Nombre / Escuela</th>
            <th>Curso / Nivel</th>
            <th>Estado</th>
            <th>Nota final</th>
            <th>Sexo</th>
            <th>Ubicación</th>
            <th>Organización</th>
            <th class="nowrap">Teléfono</th>
            <?php if ($me['role']==='admin'): ?><th>Docente</th><?php endif; ?>
            <th style="width:280px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>

              <td class="nowrap">
                <span class="tag"><?= h($r['student_code'] ?: '—') ?></span>
              </td>

              <td>
                <div style="font-weight:800; color:#e5e7eb;"><?= h($r['full_name']) ?></div>
                <div class="small"><?= h($r['school_name'] ?: '—') ?></div>
                <?php if (!empty($r['profession'])): ?>
                  <div class="small">Profesión: <?= h($r['profession']) ?></div>
                <?php endif; ?>
              </td>

              <td>
                <div style="font-weight:700;"><?= h(courseLabel($r['course_type'])) ?></div>
                <div class="small"><?= h(levelLabel($r['course_level'])) ?></div>
              </td>

              <td>
                <span class="status-pill <?= h(statusClass($r['status'] ?? 'pendiente')) ?>">
                  <?= h($r['status'] ?: 'pendiente') ?>
                </span>
              </td>

              <td class="nowrap"><?= h($r['final_grade'] !== null && $r['final_grade'] !== '' ? $r['final_grade'] : '—') ?></td>

              <td class="muted"><?= h(sexLabel($r['sex'] ?? '')) ?></td>

              <td>
                <div class="small"><?= h($r['department_name'] ?: '—') ?></div>
                <div class="small"><?= h($r['municipality_name'] ?: '—') ?></div>
                <?php if (!empty($r['community'])): ?>
                  <div class="small"><?= h($r['community']) ?></div>
                <?php endif; ?>
              </td>

              <td>
                <div class="small"><?= h(organizationTypeLabel($r['organization_type'] ?? '')) ?></div>
                <div class="small"><?= h($r['organization_name'] ?: '—') ?></div>
              </td>

              <td class="muted nowrap"><?= h($r['phone'] ?: '—') ?></td>

              <?php if ($me['role']==='admin'): ?>
                <td class="muted"><?= h($r['teacher_name']) ?></td>
              <?php endif; ?>

              <td>
                <div class="actions">
                  <a class="btnG" href="student_profile.php?id=<?= (int)$r['id'] ?>">Ver</a>
                  <a class="btnG" href="student_letter.php?id=<?= (int)$r['id'] ?>" target="_blank">Constancia</a>
                  <?php if ((int)$r['is_historical'] === 1): ?>
                    <form method="post" action="student_reactivate.php" onsubmit="return confirm('¿Rehabilitar este estudiante y devolverlo al año 2026?');" style="display:inline;">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btnG" type="submit">Rehabilitar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if(!$rows): ?>
            <tr>
              <td colspan="<?= $me['role']==='admin' ? 12 : 11 ?>" class="muted">
                No hay estudiantes históricos para mostrar.
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
