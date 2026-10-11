<?php
require __DIR__ . '/../app/helpers/session.php';
start_app_session();
require __DIR__ . '/../app/config/db.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$token = (string)($_GET['token'] ?? '');
$grant = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
  $grant = $_SESSION['certificate_downloads'][hash('sha256', $token)] ?? null;
}

if (!$grant || (int)($grant['expires_at'] ?? 0) < time()) {
  http_response_code(403);
  exit('El enlace del certificado no es válido o expiró. Realiza una nueva consulta.');
}

$studentId = (int)$grant['student_id'];
$groupId = (int)$grant['group_id'];

$stmt = $pdo->prepare("
  SELECT
    s.id,
    s.full_name,
    s.student_code,
    sc.name AS school_name,
    g.id AS group_id,
    g.group_code,
    g.name AS group_name,
    g.course_type,
    g.course_level,
    g.start_date,
    g.end_date,
    sg.final_grade,
    sg.status AS academic_status,
    u.name AS teacher_name
  FROM students s
  JOIN group_students gs
    ON gs.student_id = s.id
  JOIN groups_table g
    ON g.id = gs.group_id
  JOIN student_grades sg
    ON sg.group_id = gs.group_id
   AND sg.student_id = gs.student_id
  JOIN users u
    ON u.id = g.teacher_id
  LEFT JOIN schools sc
    ON sc.id = s.school_id
  WHERE s.id = ?
    AND g.id = ?
  LIMIT 1
");
$stmt->execute([$studentId, $groupId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
  http_response_code(404);
  exit('No se encontró el certificado solicitado.');
}

if (($student['academic_status'] ?? '') !== 'aprobado' || $student['final_grade'] === null || (float)$student['final_grade'] < 60) {
  http_response_code(403);
  exit('Este certificado aún no está disponible.');
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}

$certificateNumber = 'CERT-' . date('Y') . '-' . str_pad((string)$student['id'], 5, '0', STR_PAD_LEFT) . '-' . str_pad((string)$groupId, 4, '0', STR_PAD_LEFT);

$logoGob = '/docentes/assets/images/grun.png';
$logoSnpcc = '/docentes/assets/images/SNPCC.png';
$logoCona = '/docentes/assets/images/conatradec.png';
$signatureImage = '/docentes/assets/images/firma.png';

$schoolName = trim((string)($student['school_name'] ?? ''));
if ($schoolName === '') {
  $schoolName = 'Escuela de Catación y Barismo CONATRADEC';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Certificado - <?= h($student['full_name']) ?></title>
  <style>
    :root{
      --paper:#fffdf8;
      --ink:#1f2937;
      --muted:#6b7280;
      --gold:#b8891f;
      --gold-soft:#f6ecd2;
      --line:#e7d9b0;
    }

    *{ box-sizing:border-box; }

    body{
      margin:0;
      padding:24px;
      background:#0b1220;
      font-family:"Georgia","Times New Roman",serif;
    }

    .actions{
      max-width:1100px;
      margin:0 auto 14px;
      display:flex;
      justify-content:flex-end;
      gap:10px;
      flex-wrap:wrap;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,.15);
      background:rgba(255,255,255,.06);
      color:#fff;
      font-weight:700;
      text-decoration:none;
      cursor:pointer;
      font-family:Arial,sans-serif;
    }

    .btn.print{
      border-color:rgba(47,191,113,.45);
      background:rgba(47,191,113,.14);
      color:#d1fae5;
    }

    .page{
      max-width:1100px;
      min-height:760px;
      margin:0 auto;
      background:var(--paper);
      color:var(--ink);
      border:10px solid var(--gold-soft);
      box-shadow:0 18px 60px rgba(0,0,0,.35);
      position:relative;
      overflow:hidden;
    }

    .frame{
      border:2px solid var(--gold);
      margin:18px;
      min-height:calc(760px - 36px);
      padding:34px 48px;
      position:relative;
    }

    .top{
      display:grid;
      grid-template-columns:1fr auto 1fr;
      align-items:center;
      gap:18px;
      padding-bottom:8px;
      border-bottom:1px solid var(--line);
    }

    .top-left,.top-center,.top-right{
      display:flex;
      align-items:center;
    }

    .top-left{ justify-content:flex-start; }
    .top-center{ justify-content:center; }
    .top-right{ justify-content:flex-end; }

    .logo-gob{ max-width:220px; max-height:74px; object-fit:contain; }
    .logo-snpcc{ max-width:180px; max-height:70px; object-fit:contain; }
    .logo-cona{ max-width:220px; max-height:74px; object-fit:contain; }

    .cert-number{
      text-align:right;
      font-family:Arial,sans-serif;
      font-size:13px;
      color:var(--muted);
      margin-top:12px;
    }

    .title{
      text-align:center;
      margin-top:30px;
    }

    .title h2{
      margin:0;
      font-size:46px;
      color:#7a5a12;
      letter-spacing:1px;
    }

    .title h4{
      margin:10px 0 0;
      font-size:18px;
      color:var(--muted);
      font-family:Arial,sans-serif;
      font-weight:700;
    }

    .body{
      margin-top:34px;
      text-align:center;
      padding:0 24px;
    }

    .body p{
      margin:0 0 18px;
      font-size:22px;
      line-height:1.7;
    }

    .student-name{
      margin:24px 0 16px;
      font-size:42px;
      font-weight:700;
      color:#3f2f08;
      border-top:1px solid var(--line);
      border-bottom:1px solid var(--line);
      padding:18px 10px;
    }

    .signature{
      width:320px;
      text-align:center;
      font-family:Arial,sans-serif;
      margin:42px auto 0;
    }

    .signature-image-wrap{
      height:80px;
      display:flex;
      align-items:flex-end;
      justify-content:center;
      margin-bottom:6px;
    }

    .signature-image{
      max-width:200px;
      max-height:80px;
      object-fit:contain;
      display:block;
    }

    .signature .line{
      border-top:1px solid #8b7350;
      margin-bottom:8px;
      padding-top:8px;
      color:#4b5563;
      font-size:13px;
    }

    @media print{
      body{ background:#fff; padding:0; }
      .actions{ display:none !important; }
      .page{
        box-shadow:none;
        max-width:none;
        width:100%;
        min-height:auto;
        border:8px solid var(--gold-soft);
      }
      .frame{ min-height:auto; }
    }
  </style>
</head>
<body>

<div class="actions">
  <a class="btn" href="certificate_lookup.php">← Volver</a>
  <button class="btn print" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<section class="page">
  <div class="frame">

    <div class="top">
      <div class="top-left">
        <img class="logo-gob" src="<?= h($logoGob) ?>" alt="Gobierno">
      </div>

      <div class="top-center">
        <img class="logo-snpcc" src="<?= h($logoSnpcc) ?>" alt="SNPCC">
      </div>

      <div class="top-right">
        <img class="logo-cona" src="<?= h($logoCona) ?>" alt="CONATRADEC">
      </div>
    </div>

    <div class="cert-number">
      <div><b>N.º de certificado:</b> <?= h($certificateNumber) ?></div>
      <div><b>Código estudiante:</b> <?= h($student['student_code']) ?></div>
      <div><b>Grupo:</b> <?= h($student['group_code']) ?></div>
    </div>

    <div class="title">
      <h2>CONATRADEC</h2>
      <h4>La Comisión Nacional para la Transformación y Desarrollo de la Caficultura</h4>
    </div>

    <div class="body">
      <p>OTORGA EL PRESENTE CERTIFICADO A:</p>

      <div class="student-name">
        <?= h($student['full_name']) ?>
      </div>

      <p>
        Por haber aprobado el Curso de
        <b><?= h(courseLabel($student['course_type'])) ?></b>,
        del nivel <b><?= h(levelLabel($student['course_level'])) ?></b>,
        correspondiente al grupo <b><?= h($student['group_name']) ?></b>,
        logrando satisfactoriamente cumplir con el programa de formación, impartido por los instructores de la
        <b><?= h($schoolName) ?></b>.
      </p>
    </div>

    <div class="signature">
      <div class="signature-image-wrap">
        <img class="signature-image" src="<?= h($signatureImage) ?>" alt="Firma digital">
      </div>
      <div class="line"></div>
      <div><b>Eduardo Escobar García</b></div>
      <div>Secretario Ejecutivo</div>
      <div><b>CONATRADEC</b></div>
    </div>

  </div>
</section>

</body>
</html>
