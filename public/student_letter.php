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

$st = $pdo->prepare("
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
$st->execute([$id]);
$s = $st->fetch(PDO::FETCH_ASSOC);

if (!$s) {
  http_response_code(404);
  exit("No existe");
}

// Permisos: docente solo su estudiante
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

function organizationTypeLabel($v){
  if ($v === 'institucion') return 'Institución';
  if ($v === 'privado') return 'Privado';
  if ($v === 'emprendimiento') return 'Emprendimiento';
  if ($v === 'estudiante') return 'Estudiante';
  if ($v === 'productor') return 'Productor';
  return '—';
}

// Fecha bonito
$today_human = date('d/m/Y');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Constancia - <?= h($s['full_name']) ?></title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    :root{
      --paper:#ffffff;
      --ink:#0f172a;
      --muted:#475569;
      --line:#e2e8f0;
    }

    body{
      background:#0b1220;
      margin:0;
    }

    .wrap{
      max-width:900px;
      margin:24px auto;
      padding:18px;
    }

    .paper{
      background:var(--paper);
      color:var(--ink);
      border-radius:16px;
      box-shadow:0 18px 50px rgba(0,0,0,.35);
      overflow:hidden;
    }

    .top{
      display:flex;
      gap:14px;
      align-items:center;
      padding:22px 26px;
      border-bottom:1px solid var(--line);
    }

    .top img{
      width:64px;
      height:64px;
      object-fit:contain;
    }

    .top h1{
      font-size:20px;
      margin:0;
    }

    .top .sub{
      color:var(--muted);
      font-size:13px;
      margin-top:4px;
    }

    .content{
      padding:22px 26px;
    }

    .meta{
      display:flex;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:14px;
    }

    .tag{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      color:var(--muted);
    }

    .title{
      font-size:18px;
      margin:10px 0 12px;
    }

    .p{
      color:var(--ink);
      line-height:1.75;
      margin:0 0 14px;
      font-size:14px;
    }

    .grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
      margin-top:10px;
    }

    .box{
      border:1px solid var(--line);
      border-radius:14px;
      padding:12px 14px;
      background:#fafafa;
    }

    .k{
      font-size:12px;
      color:var(--muted);
      margin:0 0 6px;
    }

    .v{
      font-size:14px;
      margin:0;
      font-weight:700;
      color:var(--ink);
    }

    .footer{
      padding:18px 26px;
      border-top:1px solid var(--line);
      display:flex;
      justify-content:space-between;
      align-items:flex-end;
      gap:14px;
      flex-wrap:wrap;
    }

    .sig{
      width:260px;
      border-top:1px solid var(--line);
      padding-top:10px;
      color:var(--muted);
      font-size:12px;
    }

    .actions{
      display:flex;
      gap:10px;
      justify-content:flex-end;
      padding:14px 26px;
      background:#0b1220;
    }

    .btnPrint{
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.5);
      background:rgba(47,191,113,.12);
      color:#d1fae5;
      font-weight:800;
      cursor:pointer;
    }

    .btnBack{
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(148,163,184,.35);
      background:rgba(255,255,255,.06);
      color:#e5e7eb;
      font-weight:800;
      text-decoration:none;
    }

    @media print{
      body{
        background:#fff;
      }

      .wrap{
        margin:0;
        padding:0;
        max-width:none;
      }

      .paper{
        border-radius:0;
        box-shadow:none;
      }

      .actions{
        display:none !important;
      }
    }
  </style>
</head>
<body>

<div class="wrap">
  <div class="actions">
    <a class="btnBack" href="students.php">← Volver</a>
    <button class="btnPrint" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>

  <section class="paper">
    <div class="top">
      <img src="/docentes/frontend/images/1.png" alt="CONATRADEC">
      <div>
        <h1>CONATRADEC</h1>
        <div class="sub">Registro Académico • Constancia</div>
      </div>
    </div>

    <div class="content">
      <div class="meta">
        <span class="tag">Fecha: <?= h($today_human) ?></span>
        <span class="tag">ID interno: <?= (int)$s['id'] ?></span>
        <span class="tag">Código: <?= h($s['student_code'] ?: '—') ?></span>
      </div>

      <h2 class="title">Constancia de Inscripción / Participación</h2>

      <p class="p">
        Por este medio se hace constar que <b><?= h($s['full_name']) ?></b>
        <?= $s['cedula'] ? " con cédula <b>" . h($s['cedula']) . "</b>" : "" ?>
        se encuentra registrado(a) en el programa de <b><?= h(courseLabel($s['course_type'])) ?></b>,
        nivel <b><?= h(levelLabel($s['course_level'])) ?></b>,
        <?= $s['enrolled_at'] ? " con fecha de inscripción <b>" . h($s['enrolled_at']) . "</b>" : "" ?>,
        en la escuela <b><?= h($s['school_name'] ?: 'No especificada') ?></b>.
      </p>

      <p class="p">
        Esta constancia se emite a solicitud del interesado para los fines que estime convenientes.
      </p>

      <div class="grid">
        <div class="box">
          <p class="k">Curso</p>
          <p class="v"><?= h(courseLabel($s['course_type'])) ?> • <?= h(levelLabel($s['course_level'])) ?></p>
        </div>

        <div class="box">
          <p class="k">Escuela</p>
          <p class="v"><?= h($s['school_name'] ?: 'No especificada') ?></p>
        </div>

        <div class="box">
          <p class="k">Departamento</p>
          <p class="v"><?= h($s['department_name'] ?: 'No especificado') ?></p>
        </div>

        <div class="box">
          <p class="k">Municipio</p>
          <p class="v"><?= h($s['municipality_name'] ?: 'No especificado') ?></p>
        </div>

        <div class="box">
          <p class="k">Teléfono</p>
          <p class="v"><?= h($s['phone'] ?: 'No especificado') ?></p>
        </div>

        <div class="box">
          <p class="k">Comunidad</p>
          <p class="v"><?= h($s['community'] ?: 'No especificada') ?></p>
        </div>

        <div class="box">
          <p class="k">Tipo de organización</p>
          <p class="v"><?= h(organizationTypeLabel($s['organization_type'] ?? '')) ?></p>
        </div>

        <div class="box">
          <p class="k">Organización</p>
          <p class="v"><?= h($s['organization_name'] ?: 'No especificada') ?></p>
        </div>

        <div class="box">
          <p class="k">Caracterización</p>
          <p class="v"><?= h($s['characterization'] ?: 'No especificada') ?></p>
        </div>

        <div class="box">
          <p class="k">Docente</p>
          <p class="v"><?= h($s['teacher_name']) ?></p>
        </div>

        <div class="box">
          <p class="k">Correo del docente</p>
          <p class="v"><?= h($s['teacher_email'] ?: 'No especificado') ?></p>
        </div>

        <div class="box">
          <p class="k">Propósito del curso</p>
          <p class="v"><?= h($s['course_purpose'] ?: '—') ?></p>
        </div>

        <div class="box" style="grid-column:1 / -1;">
          <p class="k">Observaciones</p>
          <p class="v" style="font-weight:600;">
            <?= h($s['observations'] ?: '—') ?>
          </p>
        </div>
      </div>
    </div>

    <div class="footer">
      <div>
        <div class="p" style="margin:0;color:var(--muted);font-size:13px;">
          Managua, Nicaragua • <?= h($today_human) ?>
        </div>
      </div>

      <div class="sig">
        Firma y sello<br>
        <b>CONATRADEC</b>
      </div>
    </div>
  </section>
</div>

</body>
</html>