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

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Asistencia inválida');
}

$stmt = $pdo->prepare("
    SELECT
        a.*,
        g.id AS group_id,
        g.group_code,
        g.name AS group_name,
        g.course_type,
        g.course_level,
        g.teacher_id,
        g.location,
        g.schedule,
        u.name AS teacher_name
    FROM attendances a
    JOIN groups_table g ON g.id = a.group_id
    JOIN users u ON u.id = g.teacher_id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    exit('Asistencia no encontrada');
}

if (($me['role'] ?? '') === 'teacher' && (int)$attendance['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

$stmt = $pdo->prepare("
    SELECT
        s.full_name,
        s.student_code,
        d.name AS department_name,
        ai.present
    FROM attendance_items ai
    JOIN students s ON s.id = ai.student_id
    LEFT JOIN departments d ON d.id = s.department_id
    WHERE ai.attendance_id = ?
    ORDER BY s.full_name ASC
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPresent = 0;
$totalAbsent = 0;

foreach ($items as $item) {
    if ((int)$item['present'] === 1) {
        $totalPresent++;
    } else {
        $totalAbsent++;
    }
}

$totalStudents = count($items);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle de asistencia</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1280px;
      width:100%;
      margin:0 auto;
    }

    .sheet{
      background:#fff;
      color:#111827;
      border-radius:20px;
      box-shadow:0 18px 50px rgba(0,0,0,.25);
      overflow:hidden;
    }

    .head{
      padding:24px 24px 12px;
      border-bottom:1px solid #e5e7eb;
    }

    .brand-row{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:16px;
      flex-wrap:wrap;
    }

    .brand{
      display:flex;
      gap:14px;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .brand img{
      height:58px;
      object-fit:contain;
    }

    .brand-text h2{
      margin:0 0 8px;
      font-size:28px;
      line-height:1.1;
      color:#0f172a;
    }

    .brand-text p{
      margin:0;
      color:#4b5563;
      font-size:14px;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    .btnS,
    .btnG,
    .btn2{
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
      border:1px solid #bbf7d0;
      background:#ecfdf5;
      color:#16a34a;
    }

    .btnG{
      border:1px solid #d1d5db;
      background:#f9fafb;
      color:#374151;
    }

    .btn2{
      border:none;
      background:linear-gradient(180deg,#4ade80,#22c55e);
      color:#052e16;
    }

    .meta{
      display:grid;
      grid-template-columns:repeat(4,minmax(180px,1fr));
      gap:10px 16px;
      margin-top:18px;
      padding:14px;
      border:1px solid #e5e7eb;
      border-radius:16px;
      background:#f9fafb;
    }

    .meta-item .k{
      margin:0 0 4px;
      font-size:12px;
      color:#6b7280;
      font-weight:700;
    }

    .meta-item .v{
      margin:0;
      font-size:15px;
      font-weight:800;
      color:#111827;
    }

    .summary{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
      margin-top:16px;
    }

    .sum-card{
      border-radius:16px;
      padding:16px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
    }

    .sum-card .k{
      font-size:12px;
      color:#6b7280;
      margin-bottom:6px;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.3px;
    }

    .sum-card .v{
      font-size:28px;
      font-weight:900;
      color:#111827;
      line-height:1;
    }

    .sum-card.ok{
      background:#ecfdf5;
      border-color:#bbf7d0;
    }

    .sum-card.ok .v{
      color:#166534;
    }

    .sum-card.bad{
      background:#fef2f2;
      border-color:#fecaca;
    }

    .sum-card.bad .v{
      color:#991b1b;
    }

    .table-wrap{
      padding:18px 24px 8px;
      overflow-x:auto;
    }

    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      overflow:hidden;
      border:1px solid #e5e7eb;
      border-radius:16px;
      font-size:14px;
    }

    thead th{
      background:#0f172a;
      color:#fff;
      text-align:left;
      padding:13px 14px;
      font-size:13px;
      letter-spacing:.2px;
      white-space:nowrap;
    }

    tbody td{
      padding:13px 14px;
      border-top:1px solid #e5e7eb;
      background:#fff;
      vertical-align:middle;
    }

    tbody tr:nth-child(even) td{
      background:#f9fafb;
    }

    .num{
      width:80px;
      font-weight:900;
      color:#374151;
    }

    .name{
      font-weight:800;
      color:#111827;
    }

    .muted{
      color:#6b7280;
      font-size:12px;
      margin-top:4px;
    }

    .status-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      font-weight:800;
      font-size:13px;
      border:1px solid transparent;
    }

    .status-badge.ok{
      background:#dcfce7;
      color:#166534;
      border-color:#86efac;
    }

    .status-badge.bad{
      background:#fee2e2;
      color:#991b1b;
      border-color:#fca5a5;
    }

    .footer{
      padding:20px 24px 26px;
    }

    .signature{
      margin-top:28px;
      width:320px;
      border-top:1px solid #9ca3af;
      padding-top:12px;
      color:#374151;
      font-size:14px;
    }

    .empty{
      padding:24px;
      text-align:center;
      color:#6b7280;
      font-weight:700;
    }

    @media(max-width:980px){
      .meta{
        grid-template-columns:repeat(2,1fr);
      }

      .summary{
        grid-template-columns:1fr;
      }
    }

    @media(max-width:640px){
      .meta{
        grid-template-columns:1fr;
      }
    }

    @page{
      size: letter landscape;
      margin: 12mm;
    }

    @media print{
      html, body{
        width:100%;
        height:auto;
        background:#fff !important;
        color:#111 !important;
        margin:0 !important;
        padding:0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      /* Ocultar navegación y controles externos */
      .actions,
      .sidebar,
      #appSidebar,
      .topbar,
      .mobile-topbar,
      .menu-toggle,
      .hamburger,
      .nav-toggle,
      .toggle-btn,
      .sidebar-toggle,
      .floating-menu,
      button.btn2,
      a.btnG,
      a.btnS{
        display:none !important;
      }

      body > aside,
      body > nav,
      body > header{
        display:none !important;
      }

      .container{
        max-width:100% !important;
        width:100% !important;
        padding:0 !important;
        margin:0 !important;
      }

      .sheet{
        box-shadow:none !important;
        border-radius:0 !important;
        background:#fff !important;
        color:#111 !important;
        border:none !important;
        overflow:visible !important;
      }

      .head{
        padding:0 0 10px !important;
        border-bottom:none !important;
      }

      .brand-row{
        margin-bottom:8px !important;
      }

      .brand{
        gap:10px !important;
      }

      .brand img{
        height:42px !important;
      }

      .brand-text h2{
        font-size:18px !important;
        margin:0 0 4px !important;
        color:#111 !important;
      }

      .brand-text p{
        font-size:12px !important;
        color:#555 !important;
      }

      .meta{
        grid-template-columns:repeat(4,1fr) !important;
        gap:8px !important;
        margin-top:8px !important;
        padding:10px !important;
        border:1px solid #d9d9d9 !important;
        border-radius:10px !important;
        background:#fafafa !important;
        page-break-inside:avoid;
      }

      .meta-item .k{
        color:#555 !important;
        font-size:11px !important;
      }

      .meta-item .v{
        color:#111 !important;
        font-size:12px !important;
        font-weight:700 !important;
      }

      .summary{
        grid-template-columns:repeat(3,1fr) !important;
        gap:8px !important;
        margin-top:10px !important;
        page-break-inside:avoid;
      }

      .sum-card{
        padding:10px !important;
        border:1px solid #d9d9d9 !important;
        border-radius:10px !important;
        background:#fafafa !important;
      }

      .sum-card .k{
        color:#555 !important;
        font-size:11px !important;
      }

      .sum-card .v{
        color:#111 !important;
        font-size:18px !important;
      }

      .sum-card.ok{
        background:#eef8f0 !important;
        border-color:#b7d7be !important;
      }

      .sum-card.bad{
        background:#fdeeee !important;
        border-color:#e2bcbc !important;
      }

      .table-wrap{
        padding:10px 0 0 !important;
        overflow:visible !important;
      }

      table{
        width:100% !important;
        min-width:0 !important;
        border-collapse:collapse !important;
        border-spacing:0 !important;
        border:1px solid #d9d9d9 !important;
        border-radius:0 !important;
        table-layout:fixed !important;
      }

      thead{
        display:table-header-group;
      }

      tr{
        page-break-inside:avoid;
        page-break-after:auto;
      }

      thead th{
        background:#f3f3f3 !important;
        color:#222 !important;
        border:1px solid #d9d9d9 !important;
        padding:7px 6px !important;
        font-size:10px !important;
      }

      tbody td{
        background:#fff !important;
        color:#111 !important;
        border:1px solid #d9d9d9 !important;
        padding:7px 6px !important;
        font-size:10px !important;
        vertical-align:top !important;
        word-break:break-word !important;
        overflow-wrap:break-word !important;
      }

      tbody tr:nth-child(even) td{
        background:#fff !important;
      }

      .num,
      .name{
        color:#111 !important;
      }

      .muted{
        color:#555 !important;
        font-size:9px !important;
      }

      .status-badge{
        min-width:auto !important;
        padding:3px 7px !important;
        border-radius:999px !important;
        font-size:9px !important;
        font-weight:700 !important;
      }

      .status-badge.ok{
        background:#eef8f0 !important;
        color:#1f5d2b !important;
        border:1px solid #b7d7be !important;
      }

      .status-badge.bad{
        background:#fdeeee !important;
        color:#8a2f2f !important;
        border:1px solid #e2bcbc !important;
      }

      .footer{
        padding:14px 0 0 !important;
      }

      .signature{
        width:260px !important;
        margin-top:20px !important;
        color:#111 !important;
        font-size:12px !important;
        border-top:1px solid #777 !important;
      }

      .empty{
        color:#111 !important;
        background:#fff !important;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="sheet">

    <div class="head">
      <div class="brand-row">
        <div class="brand">
          <img src="/docentes/frontend/images/1.png" alt="Logo CONATRADEC">
          <div class="brand-text">
            <h2>Detalle de asistencia</h2>
            <p>Registro oficial de presencia por grupo y fecha.</p>
          </div>
        </div>

        <div class="actions">
          <a class="btnG" href="attendance_history.php">← Volver</a>
          <button class="btn2" type="button" onclick="window.print()">Imprimir / Exportar PDF</button>
        </div>
      </div>

      <div class="meta">
        <div class="meta-item">
          <div class="k">Grupo</div>
          <div class="v"><?= h($attendance['group_name']) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Código</div>
          <div class="v"><?= h($attendance['group_code']) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Curso</div>
          <div class="v"><?= h(courseLabel($attendance['course_type'])) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Nivel</div>
          <div class="v"><?= h(levelLabel($attendance['course_level'])) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Docente</div>
          <div class="v"><?= h($attendance['teacher_name']) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Fecha</div>
          <div class="v"><?= h($attendance['attendance_date']) ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Horario</div>
          <div class="v"><?= h($attendance['schedule'] ?: '—') ?></div>
        </div>
        <div class="meta-item">
          <div class="k">Ubicación</div>
          <div class="v"><?= h($attendance['location'] ?: '—') ?></div>
        </div>
      </div>

      <div class="summary">
        <div class="sum-card">
          <div class="k">Total estudiantes</div>
          <div class="v"><?= (int)$totalStudents ?></div>
        </div>

        <div class="sum-card ok">
          <div class="k">Presentes</div>
          <div class="v"><?= (int)$totalPresent ?></div>
        </div>

        <div class="sum-card bad">
          <div class="k">Ausentes</div>
          <div class="v"><?= (int)$totalAbsent ?></div>
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:8%;">Número</th>
            <th style="width:38%;">Nombre</th>
            <th style="width:18%;">Código</th>
            <th style="width:18%;">Departamento</th>
            <th style="width:18%;">Asistencia</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items): ?>
            <?php $i = 1; foreach ($items as $item): ?>
              <tr>
                <td class="num"><?= $i++ ?></td>
                <td>
                  <div class="name"><?= h($item['full_name']) ?></div>
                </td>
                <td><?= h($item['student_code'] ?: '—') ?></td>
                <td><?= h($item['department_name'] ?: '—') ?></td>
                <td>
                  <?php if ((int)$item['present'] === 1): ?>
                    <span class="status-badge ok">✔ Presente</span>
                  <?php else: ?>
                    <span class="status-badge bad">✘ Ausente</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="empty">No hay estudiantes registrados en esta asistencia.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="footer">
      <div class="signature">
        Firma del docente
      </div>
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