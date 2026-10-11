<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function prettyOrgType(?string $value): string {
  $value = trim((string)$value);

  if ($value === '') {
    return 'Sin datos';
  }

  $map = [
    'institucion' => 'Institución',
    'privado' => 'Privado',
    'emprendimiento' => 'Emprendimiento',
    'estudiante' => 'Estudiante',
    'productor' => 'Productor',
  ];

  return $map[$value] ?? mb_convert_case(str_replace('_', ' ', $value), MB_CASE_TITLE, 'UTF-8');
}

function prettyCharacterization(?string $value): string {
  $value = trim((string)$value);

  if ($value === '') {
    return 'Sin datos';
  }

  $map = [
    'catador' => 'Catador',
    'barista' => 'Barista',
    'productor' => 'Productor',
    'tostador' => 'Tostador',
    'tecnico' => 'Técnico',
    'comerciante' => 'Comerciante',
    'independiente' => 'Independiente',
    'propietario_cafeteria' => 'Propietario de cafetería',
  ];

  return $map[$value] ?? mb_convert_case(str_replace('_', ' ', $value), MB_CASE_TITLE, 'UTF-8');
}

// ===========================
// STATS (admin vs teacher)
// ===========================
$where = "";
$params = [];

if (($me['role'] ?? '') === 'teacher') {
  $where = "WHERE teacher_id = ?";
  $params[] = (int)$me['id'];
}

// Estado
$stmt = $pdo->prepare("SELECT status, COUNT(*) total FROM students $where GROUP BY status");
$stmt->execute($params);
$statusData = ['pendiente'=>0,'aprobado'=>0,'desaprobado'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['status'] ?? '';
  if (isset($statusData[$k])) {
    $statusData[$k] = (int)$r['total'];
  }
}

// Curso
$stmt = $pdo->prepare("SELECT course_type, COUNT(*) total FROM students $where GROUP BY course_type");
$stmt->execute($params);
$courseData = ['barismo'=>0,'catacion'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['course_type'] ?? '';
  if (isset($courseData[$k])) {
    $courseData[$k] = (int)$r['total'];
  }
}

// Nivel
$stmt = $pdo->prepare("SELECT course_level, COUNT(*) total FROM students $where GROUP BY course_level");
$stmt->execute($params);
$levelData = ['basico'=>0,'avanzado'=>0,'intensivo'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['course_level'] ?? '';
  if (isset($levelData[$k])) {
    $levelData[$k] = (int)$r['total'];
  }
}

$totalStudents = $statusData['pendiente'] + $statusData['aprobado'] + $statusData['desaprobado'];

// ===========================
// DEPARTAMENTOS
// ===========================
if (($me['role'] ?? '') === 'teacher') {
  $stmt = $pdo->prepare("
    SELECT d.name AS department_name, COUNT(*) total
    FROM students s
    LEFT JOIN departments d ON d.id = s.department_id
    WHERE s.teacher_id = ?
    GROUP BY d.id, d.name
    ORDER BY total DESC
  ");
  $stmt->execute([(int)$me['id']]);
} else {
  $stmt = $pdo->prepare("
    SELECT d.name AS department_name, COUNT(*) total
    FROM students s
    LEFT JOIN departments d ON d.id = s.department_id
    GROUP BY d.id, d.name
    ORDER BY total DESC
  ");
  $stmt->execute();
}

$deptLabels = [];
$deptTotals = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $dept = trim((string)($r['department_name'] ?? ''));
  if ($dept === '') {
    $dept = 'Sin datos';
  }
  $deptLabels[] = $dept;
  $deptTotals[] = (int)$r['total'];
}

if (!$deptLabels) {
  $deptLabels = ['Sin datos'];
  $deptTotals = [0];
}

// ===========================
// TIPO DE ORGANIZACIÓN
// ===========================
if (($me['role'] ?? '') === 'teacher') {
  $stmt = $pdo->prepare("
    SELECT so.organization_type, COUNT(*) AS total
    FROM student_organizations so
    INNER JOIN students s ON s.id = so.student_id
    WHERE s.teacher_id = ?
      AND so.organization_type IS NOT NULL
      AND TRIM(so.organization_type) <> ''
    GROUP BY so.organization_type
    ORDER BY total DESC, so.organization_type ASC
  ");
  $stmt->execute([(int)$me['id']]);
} else {
  $stmt = $pdo->prepare("
    SELECT so.organization_type, COUNT(*) AS total
    FROM student_organizations so
    INNER JOIN students s ON s.id = so.student_id
    WHERE so.organization_type IS NOT NULL
      AND TRIM(so.organization_type) <> ''
    GROUP BY so.organization_type
    ORDER BY total DESC, so.organization_type ASC
  ");
  $stmt->execute();
}

$orgLabels = [];
$orgTotals = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $orgLabels[] = prettyOrgType($r['organization_type'] ?? '');
  $orgTotals[] = (int)$r['total'];
}

if (!$orgLabels) {
  $orgLabels = ['Sin datos'];
  $orgTotals = [0];
}

// ===========================
// CARACTERIZACIÓN
// ===========================
if (($me['role'] ?? '') === 'teacher') {
  $stmt = $pdo->prepare("
    SELECT so.characterization, COUNT(*) AS total
    FROM student_organizations so
    INNER JOIN students s ON s.id = so.student_id
    WHERE s.teacher_id = ?
      AND so.characterization IS NOT NULL
      AND TRIM(so.characterization) <> ''
    GROUP BY so.characterization
    ORDER BY total DESC, so.characterization ASC
  ");
  $stmt->execute([(int)$me['id']]);
} else {
  $stmt = $pdo->prepare("
    SELECT so.characterization, COUNT(*) AS total
    FROM student_organizations so
    INNER JOIN students s ON s.id = so.student_id
    WHERE so.characterization IS NOT NULL
      AND TRIM(so.characterization) <> ''
    GROUP BY so.characterization
    ORDER BY total DESC, so.characterization ASC
  ");
  $stmt->execute();
}

$charLabels = [];
$charTotals = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $charLabels[] = prettyCharacterization($r['characterization'] ?? '');
  $charTotals[] = (int)$r['total'];
}

if (!$charLabels) {
  $charLabels = ['Sin datos'];
  $charTotals = [0];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .container{
      padding:26px;
      max-width:1240px;
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

    .col6{grid-column:span 6}
    .col12{grid-column:span 12}

    @media(max-width:860px){
      .col6{grid-column:span 12}
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
      border:none;
      cursor:pointer;
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
      font-weight:700;
      text-decoration:none;
      cursor:pointer;
    }

    .cards{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
      margin-top:12px;
    }

    @media(max-width:860px){
      .cards{grid-template-columns:1fr}
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

    .chartBox{
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      min-width:0;
    }

    .chartTitle{
      font-weight:800;
      margin:0 0 10px;
    }

    .chartWrap{
      position:relative;
      width:100%;
      height:320px;
    }

    .chartWrap.short{
      height:260px;
    }

    .chartWrap.tall{
      height:360px;
    }

    .small{
      font-size:12px;
      color:var(--muted);
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      flex-wrap:wrap;
    }

    .hero-right{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    .tableWrap{
      overflow-x:auto;
      margin-top:12px;
    }

    table{
      width:100%;
      border-collapse:collapse;
      min-width:760px;
    }

    th, td{
      padding:10px 12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:13px;
    }

    th{
      color:var(--muted);
      background:rgba(255,255,255,.04);
    }

    .print-keep{
      display:block;
    }

    .no-print{
      display:block;
    }

    @media(max-width:860px){
      .chartWrap,
      .chartWrap.short,
      .chartWrap.tall{
        height:280px;
      }
    }

    /* ===== SOLO GRÁFICOS AL IMPRIMIR ===== */
    @media print{
      body{
        background:#fff !important;
        color:#000 !important;
      }

      .container{
        max-width:100% !important;
        width:100% !important;
        padding:0 !important;
        margin:0 !important;
      }

      .layout,
      .content-area,
      .grid{
        display:block !important;
        width:100% !important;
        max-width:100% !important;
      }

      .sidebar,
      .mobile-topbar,
      .toggle-btn,
      .hero,
      .hero-right,
      .btn2,
      .btnS,
      #printStatsBtn,
      .no-print{
        display:none !important;
      }

      /* ocultar todo lo imprimible por defecto */
      .print-keep{
        display:none !important;
      }

      /* mostrar solo la sección de estadísticas con charts */
      .print-graphs-only{
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        background:#fff !important;
        color:#000 !important;
        border:none !important;
        box-shadow:none !important;
        padding:0 !important;
        margin:0 !important;
      }

      /* ocultar encabezado de esa sección al imprimir */
      .print-graphs-only > h3,
      .print-graphs-only > p{
        display:none !important;
      }

      .print-graphs-only .grid{
        display:block !important;
      }

      .print-graphs-only .chartBox{
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        background:#fff !important;
        border:none !important;
        box-shadow:none !important;
        padding:0 0 18px 0 !important;
        margin:0 0 22px 0 !important;
        page-break-inside:avoid !important;
        break-inside:avoid !important;
      }

      .print-graphs-only .chartTitle{
        display:block !important;
        color:#000 !important;
        font-size:18px !important;
        font-weight:700 !important;
        margin:0 0 10px 0 !important;
      }

      .chartWrap,
      .chartWrap.short,
      .chartWrap.tall{
        width:100% !important;
        height:320px !important;
        max-height:320px !important;
      }

      canvas{
        max-width:100% !important;
      }

      @page{
        size:auto;
        margin:12mm;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container" id="dashboardReport">
  <div class="grid">

    <section class="panel col12 print-keep">
      <div class="hero">
        <div>
          <h2 style="margin:0 0 6px;">Hola, <?= h($me['name']) ?> 👋</h2>
          <p style="margin:0;color:var(--muted);">
            Rol: <b><?= h($me['role']) ?></b> •
            <?= (($me['role'] ?? '') === 'admin') ? 'Estadísticas globales' : 'Estadísticas de tus estudiantes' ?>
          </p>
        </div>

        <div class="hero-right">
          <a class="btn2" href="students.php">Ver estudiantes</a>
          <a class="btnS" href="groups.php">Ver grupos</a>
          <button class="btnS" id="printStatsBtn" type="button" onclick="window.print()">Imprimir / Exportar PDF</button>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <div class="k">Total estudiantes</div>
          <div class="v"><?= (int)$totalStudents ?></div>
        </div>

        <div class="card">
          <div class="k">Aprobados (≥ 60)</div>
          <div class="v"><?= (int)$statusData['aprobado'] ?></div>
        </div>

        <div class="card">
          <div class="k">Pendientes (sin nota)</div>
          <div class="v"><?= (int)$statusData['pendiente'] ?></div>
        </div>
      </div>

      <div class="small" style="margin-top:10px;">
        Desaprobados: <b><?= (int)$statusData['desaprobado'] ?></b>
      </div>
    </section>

    <section class="panel col6 no-print">
      <h3 style="margin:0 0 10px;">Accesos rápidos</h3>
      <a class="btn2" href="students.php">Estudiantes</a>
      <span style="display:inline-block;width:10px"></span>
      <a class="btnS" href="change_password.php">Cambiar contraseña</a>
      <div class="small" style="margin-top:10px;">
        Tip: aplicá filtros en Estudiantes y luego descargá el CSV con el mismo filtro.
      </div>
    </section>

    <?php if (($me['role'] ?? '') === 'admin'): ?>
      <section class="panel col6 no-print">
        <h3 style="margin:0 0 10px;">Administración</h3>
        <a class="btn2" href="teachers.php">Gestionar docentes</a>
        <span style="display:inline-block;width:10px"></span>
        <a class="btnS" href="teacher_new.php">Crear docente</a>
        <div class="small" style="margin-top:10px;">
          Si un docente olvida contraseña, usá reset para forzar cambio al primer login.
        </div>
      </section>
    <?php endif; ?>

    <section class="panel col12 print-keep print-graphs-only">
      <h3 style="margin:0 0 6px;">Estadísticas</h3>
      <p style="margin:0;color:var(--muted);">
        Vista: <?= (($me['role'] ?? '') === 'admin') ? 'todos los estudiantes' : 'solo tus estudiantes' ?>.
      </p>

      <div class="grid" style="margin-top:14px;">
        <div class="chartBox col6">
          <p class="chartTitle">Estado</p>
          <div class="chartWrap short">
            <canvas id="statusChart"></canvas>
          </div>
        </div>

        <div class="chartBox col6">
          <p class="chartTitle">Curso</p>
          <div class="chartWrap short">
            <canvas id="courseChart"></canvas>
          </div>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Nivel</p>
          <div class="chartWrap short">
            <canvas id="levelChart"></canvas>
          </div>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Estudiantes por departamento</p>
          <div class="chartWrap tall">
            <canvas id="deptChart"></canvas>
          </div>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Estudiantes por tipo de organización</p>
          <div class="chartWrap tall">
            <canvas id="organizationChart"></canvas>
          </div>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Estudiantes por caracterización</p>
          <div class="chartWrap tall">
            <canvas id="characterizationChart"></canvas>
          </div>
        </div>
      </div>
    </section>

    <section class="panel col12 print-keep">
      <h3 style="margin:0 0 10px;">Resumen tabular de estadísticas</h3>

      <div class="tableWrap">
        <table>
          <thead>
            <tr>
              <th>Categoría</th>
              <th>Elemento</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>Estado</td><td>Pendiente</td><td><?= (int)$statusData['pendiente'] ?></td></tr>
            <tr><td>Estado</td><td>Aprobado</td><td><?= (int)$statusData['aprobado'] ?></td></tr>
            <tr><td>Estado</td><td>Desaprobado</td><td><?= (int)$statusData['desaprobado'] ?></td></tr>

            <tr><td>Curso</td><td>Barismo</td><td><?= (int)$courseData['barismo'] ?></td></tr>
            <tr><td>Curso</td><td>Catación</td><td><?= (int)$courseData['catacion'] ?></td></tr>

            <tr><td>Nivel</td><td>Básico</td><td><?= (int)$levelData['basico'] ?></td></tr>
            <tr><td>Nivel</td><td>Avanzado</td><td><?= (int)$levelData['avanzado'] ?></td></tr>
            <tr><td>Nivel</td><td>Intensivo</td><td><?= (int)$levelData['intensivo'] ?></td></tr>

            <?php foreach ($deptLabels as $i => $label): ?>
              <tr>
                <td>Departamento</td>
                <td><?= h($label) ?></td>
                <td><?= (int)$deptTotals[$i] ?></td>
              </tr>
            <?php endforeach; ?>

            <?php foreach ($orgLabels as $i => $label): ?>
              <tr>
                <td>Tipo de organización</td>
                <td><?= h($label) ?></td>
                <td><?= (int)$orgTotals[$i] ?></td>
              </tr>
            <?php endforeach; ?>

            <?php foreach ($charLabels as $i => $label): ?>
              <tr>
                <td>Caracterización</td>
                <td><?= h($label) ?></td>
                <td><?= (int)$charTotals[$i] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>
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

const commonBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { precision: 0 }
    }
  }
};

// 🎨 Paleta elegante (basada en tu sistema)
const colors = [
  '#40CFFF', // celeste principal
  '#1783AF', // azul profundo
  '#2FBF71', // verde
  '#F59E0B', // naranja
  '#EF4444', // rojo
  '#8B5CF6', // violeta
  '#14B8A6', // turquesa
  '#EAB308'  // amarillo
];

const barStyle = {
  borderRadius: 12,
  borderSkipped: false
};

// =====================
// STATUS (DOUGHNUT)
// =====================
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Pendiente','Aprobado','Desaprobado'],
    datasets: [{
      data: [
        <?= (int)$statusData['pendiente'] ?>,
        <?= (int)$statusData['aprobado'] ?>,
        <?= (int)$statusData['desaprobado'] ?>
      ],
      backgroundColor: ['#F59E0B', '#2FBF71', '#EF4444']
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});

// =====================
// CURSO (UNIFORME)
// =====================
new Chart(document.getElementById('courseChart'), {
  type: 'bar',
  data: {
    labels: ['Barismo','Catación'],
    datasets: [{
      data: [
        <?= (int)$courseData['barismo'] ?>,
        <?= (int)$courseData['catacion'] ?>
      ],
      backgroundColor: '#40CFFF',
      ...barStyle
    }]
  },
  options: commonBarOptions
});

// =====================
// NIVEL (UNIFORME)
// =====================
new Chart(document.getElementById('levelChart'), {
  type: 'bar',
  data: {
    labels: ['Básico','Avanzado','Intensivo'],
    datasets: [{
      data: [
        <?= (int)$levelData['basico'] ?>,
        <?= (int)$levelData['avanzado'] ?>,
        <?= (int)$levelData['intensivo'] ?>
      ],
      backgroundColor: '#1783AF',
      ...barStyle
    }]
  },
  options: commonBarOptions
});

// =====================
// DEPARTAMENTOS (COLORES)
// =====================
new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($deptLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    datasets: [{
      data: <?= json_encode($deptTotals, JSON_UNESCAPED_UNICODE) ?>,
      backgroundColor: <?= json_encode($deptTotals) ?>.map((_, i) => colors[i % colors.length]),
      ...barStyle
    }]
  },
  options: {
    ...commonBarOptions,
    scales: {
      x: {
        ticks: {
          autoSkip: false,
          maxRotation: 45,
          minRotation: 20
        }
      },
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

// =====================
// ORGANIZACIÓN (COLORES)
// =====================
new Chart(document.getElementById('organizationChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($orgLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    datasets: [{
      data: <?= json_encode($orgTotals, JSON_UNESCAPED_UNICODE) ?>,
      backgroundColor: <?= json_encode($orgTotals) ?>.map((_, i) => colors[i % colors.length]),
      ...barStyle
    }]
  },
  options: {
    ...commonBarOptions,
    scales: {
      x: {
        ticks: {
          autoSkip: false,
          maxRotation: 45,
          minRotation: 20
        }
      },
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

// =====================
// CARACTERIZACIÓN (COLORES)
// =====================
new Chart(document.getElementById('characterizationChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($charLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    datasets: [{
      data: <?= json_encode($charTotals, JSON_UNESCAPED_UNICODE) ?>,
      backgroundColor: <?= json_encode($charTotals) ?>.map((_, i) => colors[i % colors.length]),
      ...barStyle
    }]
  },
  options: {
    ...commonBarOptions,
    scales: {
      x: {
        ticks: {
          autoSkip: false,
          maxRotation: 45,
          minRotation: 20
        }
      },
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});
</script>
</body>
</html>
