<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';
require __DIR__ . '/../app/helpers/log.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function isValidOptionalDate(string $date): bool {
  if ($date === '') return true;
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) return false;
  return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
}

$characterizationOptions = [
  'catador' => 'Catador',
  'barista' => 'Barista',
  'productor' => 'Productor',
  'tostador' => 'Tostador',
  'tecnico' => 'Técnico',
  'comerciante' => 'Comerciante',
  'independiente' => 'Independiente',
  'propietario_cafeteria' => 'Propietario de cafetería'
];

$id = (int)($_GET['id'] ?? 0);
$error = '';

// =====================================================
// Catálogos
// =====================================================
$schools = $pdo->query("
  SELECT id, name
  FROM schools
  WHERE is_active = 1
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$departments = $pdo->query("
  SELECT id, name
  FROM departments
  WHERE is_active = 1
  ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$municipalityRows = $pdo->query("
  SELECT id, name, department_id
  FROM municipalities
  WHERE is_active = 1
  ORDER BY department_id ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$municipios = [];
foreach ($municipalityRows as $m) {
  $depId = (int)$m['department_id'];
  if (!isset($municipios[$depId])) {
    $municipios[$depId] = [];
  }
  $municipios[$depId][] = [
    'id' => (int)$m['id'],
    'name' => $m['name']
  ];
}

// =====================================================
// Defaults
// =====================================================
$row = [
  'id' => 0,
  'teacher_id' => (int)$me['id'],
  'full_name' => '',
  'sex' => '',
  'education_level' => '',
  'profession' => '',
  'nationality' => '',
  'student_code' => '',
  'school_id' => '',
  'course_type' => 'barismo',
  'course_level' => 'basico',
  'phone' => '',
  'cedula' => '',
  'department_id' => '',
  'municipality_id' => '',
  'municipality' => '',
  'community' => '',
  'organization_type' => '',
  'organization_name' => '',
  'organization_phone' => '',
  'organization_location' => '',
  'characterization' => '',
  'trademark_registration' => '',
  'course_purpose' => '',
  'number_of_members' => '',
  'future_projection' => '',
  'enrolled_at' => '',
  'observations' => '',
  'notes' => ''
];

// =====================================================
// Editar
// =====================================================
if ($id > 0) {
  $st = $pdo->prepare("
    SELECT
      s.*,
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
    LEFT JOIN student_organizations so ON so.student_id = s.id
    LEFT JOIN student_profiles sp ON sp.student_id = s.id
    WHERE s.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $found = $st->fetch(PDO::FETCH_ASSOC);

  if (!$found) {
    http_response_code(404);
    exit("No existe");
  }

  if (($me['role'] ?? '') === 'teacher' && (int)$found['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit("Acceso denegado");
  }

  if (!empty($found['municipality_id'])) {
    $munNameStmt = $pdo->prepare("
      SELECT name
      FROM municipalities
      WHERE id = ?
      LIMIT 1
    ");
    $munNameStmt->execute([(int)$found['municipality_id']]);
    $found['municipality'] = (string)($munNameStmt->fetchColumn() ?: '');
  } else {
    $found['municipality'] = '';
  }

  $row = array_merge($row, $found);
}

// =====================================================
// Docentes para admin
// =====================================================
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='teacher' AND is_active=1
    ORDER BY name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// Guardar
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();

  $full_name = trim($_POST['full_name'] ?? '');
  $sex = trim($_POST['sex'] ?? '');
  $education_level = trim($_POST['education_level'] ?? '');
  $profession = trim($_POST['profession'] ?? '');
  $nationality = trim($_POST['nationality'] ?? '');

  $school_id = (int)($_POST['school_id'] ?? 0);
  $course_type = trim($_POST['course_type'] ?? 'barismo');
  $course_level = trim($_POST['course_level'] ?? 'basico');

  $phone = trim($_POST['phone'] ?? '');
  $cedula = strtoupper(trim($_POST['cedula'] ?? ''));
  $department_id = (int)($_POST['department_id'] ?? 0);
  $municipality_name = trim($_POST['municipality'] ?? '');
  $municipality_id = 0;
  $community = trim($_POST['community'] ?? '');

  $organization_type = trim($_POST['organization_type'] ?? '');
  $organization_name = trim($_POST['organization_name'] ?? '');
  $organization_phone = trim($_POST['organization_phone'] ?? '');
  $organization_location = trim($_POST['organization_location'] ?? '');
  $characterization = trim($_POST['characterization'] ?? '');
  $trademark_registration = trim($_POST['trademark_registration'] ?? '');
  $course_purpose = trim($_POST['course_purpose'] ?? '');
  $number_of_members_raw = trim($_POST['number_of_members'] ?? '');
  $future_projection = trim($_POST['future_projection'] ?? '');
  $enrolled_at = trim($_POST['enrolled_at'] ?? '');
  $observations = trim($_POST['observations'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if ($department_id > 0 && $municipality_name !== '') {
    $munStmt = $pdo->prepare("
      SELECT id
      FROM municipalities
      WHERE department_id = ? AND name = ?
      LIMIT 1
    ");
    $munStmt->execute([$department_id, $municipality_name]);
    $municipality_id = (int)($munStmt->fetchColumn() ?: 0);
  }

  $numberOfMembersValid = $number_of_members_raw === ''
    || (preg_match('/^\d+$/', $number_of_members_raw) && (float)$number_of_members_raw <= 2147483647);
  $number_of_members = ($number_of_members_raw === '' || !$numberOfMembersValid) ? null : (int)$number_of_members_raw;

  $teacher_id = (int)$row['teacher_id'];
  if (($me['role'] ?? '') === 'admin') {
    $teacher_id = (int)($_POST['teacher_id'] ?? $teacher_id);
  } else {
    $teacher_id = (int)$me['id'];
  }

  $phoneDigits = preg_replace('/\D+/', '', $phone);
  $orgPhoneDigits = preg_replace('/\D+/', '', $organization_phone);
  $schoolStmt = $pdo->prepare('SELECT COUNT(*) FROM schools WHERE id = ? AND is_active = 1');
  $schoolStmt->execute([$school_id]);
  $schoolIsActive = (int)$schoolStmt->fetchColumn() === 1;
  $teacherStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1");
  $teacherStmt->execute([$teacher_id]);
  $teacherIsActive = (int)$teacherStmt->fetchColumn() === 1;
  $cedulaAlreadyUsed = false;
  if ($cedula !== '' && preg_match('/^\d{13}[A-Z]$/', $cedula)) {
    $cedulaStmt = $pdo->prepare("SELECT id FROM students WHERE cedula = ? AND id <> ? LIMIT 1");
    $cedulaStmt->execute([$cedula, $id]);
    $cedulaAlreadyUsed = (bool)$cedulaStmt->fetchColumn();
  }

  // =====================================================
  // Validaciones
  // =====================================================
  if ($full_name === '') {
    $error = "El nombre es obligatorio.";
  } elseif ($school_id <= 0 || !$schoolIsActive) {
    $error = "Seleccioná una escuela válida.";
  } elseif (!$teacherIsActive) {
    $error = "Seleccioná un docente activo.";
  } elseif (!isValidOptionalDate($enrolled_at)) {
    $error = "La fecha de inscripción no es válida.";
  } elseif ($sex !== '' && !in_array($sex, ['masculino','femenino'], true)) {
    $error = "Sexo inválido.";
  } elseif ($education_level !== '' && !in_array($education_level, ['secundaria','tecnico','universitario'], true)) {
    $error = "Nivel escolar inválido.";
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = "Tipo de curso inválido.";
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = "Nivel inválido.";
  } elseif ($phone !== '' && strlen($phoneDigits) !== 8) {
    $error = "El teléfono debe tener exactamente 8 dígitos.";
  } elseif ($cedula !== '' && !preg_match('/^\d{13}[A-Z]$/', $cedula)) {
    $error = "La cédula debe tener exactamente 13 números y 1 letra.";
  } elseif ($cedulaAlreadyUsed) {
    $error = "La cédula ya está registrada para otro estudiante.";
  } elseif ($department_id > 0 && $municipality_name !== '' && $municipality_id <= 0) {
    $error = "El municipio no pertenece al departamento seleccionado.";
  } elseif ($organization_phone !== '' && strlen($orgPhoneDigits) !== 8) {
    $error = "El teléfono de la organización debe tener exactamente 8 dígitos.";
  } elseif ($organization_type !== '' && !in_array($organization_type, ['institucion','privado','emprendimiento','estudiante','productor'], true)) {
    $error = "Tipo de organización inválido.";
  } elseif ($trademark_registration !== '' && !in_array($trademark_registration, ['si','no'], true)) {
    $error = "Registro de marca inválido.";
  } elseif ($characterization !== '' && !array_key_exists($characterization, $characterizationOptions)) {
    $error = "Caracterización inválida.";
  } elseif (!$numberOfMembersValid) {
    $error = "El número de socios debe ser un entero entre 0 y 2147483647.";
  } else {
    try {
      $pdo->beginTransaction();

      if ($id > 0) {
        $up = $pdo->prepare("
          UPDATE students SET
            teacher_id = ?,
            school_id = ?,
            department_id = ?,
            municipality_id = ?,
            full_name = ?,
            sex = ?,
            education_level = ?,
            profession = ?,
            nationality = ?,
            phone = ?,
            cedula = ?,
            course_type = ?,
            course_level = ?,
            enrolled_at = ?,
            community = ?
          WHERE id = ?
        ");

        $up->execute([
          $teacher_id,
          $school_id ?: null,
          $department_id ?: null,
          $municipality_id ?: null,
          $full_name,
          ($sex === '' ? null : $sex),
          ($education_level === '' ? null : $education_level),
          ($profession === '' ? null : $profession),
          ($nationality === '' ? null : $nationality),
          ($phone === '' ? null : $phoneDigits),
          ($cedula === '' ? null : $cedula),
          $course_type,
          $course_level,
          ($enrolled_at === '' ? null : $enrolled_at),
          ($community === '' ? null : $community),
          $id
        ]);

        $orgSave = $pdo->prepare("
          INSERT INTO student_organizations
          (
            student_id,
            organization_type,
            organization_name,
            organization_phone,
            organization_location,
            characterization,
            trademark_registration,
            number_of_members
          )
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            organization_type = VALUES(organization_type),
            organization_name = VALUES(organization_name),
            organization_phone = VALUES(organization_phone),
            organization_location = VALUES(organization_location),
            characterization = VALUES(characterization),
            trademark_registration = VALUES(trademark_registration),
            number_of_members = VALUES(number_of_members)
        ");

        $orgSave->execute([
          $id,
          ($organization_type === '' ? null : $organization_type),
          ($organization_name === '' ? null : $organization_name),
          ($organization_phone === '' ? null : $orgPhoneDigits),
          ($organization_location === '' ? null : $organization_location),
          ($characterization === '' ? null : $characterization),
          ($trademark_registration === '' ? null : $trademark_registration),
          $number_of_members
        ]);

        $profileSave = $pdo->prepare("
          INSERT INTO student_profiles
          (
            student_id,
            course_purpose,
            future_projection,
            observations,
            notes
          )
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            course_purpose = VALUES(course_purpose),
            future_projection = VALUES(future_projection),
            observations = VALUES(observations),
            notes = VALUES(notes)
        ");

        $profileSave->execute([
          $id,
          ($course_purpose === '' ? null : $course_purpose),
          ($future_projection === '' ? null : $future_projection),
          ($observations === '' ? null : $observations),
          ($notes === '' ? null : $notes)
        ]);

      } else {
        $ins = $pdo->prepare("
          INSERT INTO students
          (
            teacher_id, school_id, department_id, municipality_id,
            full_name, sex, education_level, profession, nationality,
            phone, cedula,
            course_type, course_level, enrolled_at, community
          )
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $ins->execute([
          $teacher_id,
          $school_id ?: null,
          $department_id ?: null,
          $municipality_id ?: null,
          $full_name,
          ($sex === '' ? null : $sex),
          ($education_level === '' ? null : $education_level),
          ($profession === '' ? null : $profession),
          ($nationality === '' ? null : $nationality),
          ($phone === '' ? null : $phoneDigits),
          ($cedula === '' ? null : $cedula),
          $course_type,
          $course_level,
          ($enrolled_at === '' ? null : $enrolled_at),
          ($community === '' ? null : $community)
        ]);

        $newId = (int)$pdo->lastInsertId();
        $studentCode = 'EST-' . date('Y') . '-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);

        $upCode = $pdo->prepare("UPDATE students SET student_code = ? WHERE id = ?");
        $upCode->execute([$studentCode, $newId]);

        $orgSave = $pdo->prepare("
          INSERT INTO student_organizations
          (
            student_id,
            organization_type,
            organization_name,
            organization_phone,
            organization_location,
            characterization,
            trademark_registration,
            number_of_members
          )
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            organization_type = VALUES(organization_type),
            organization_name = VALUES(organization_name),
            organization_phone = VALUES(organization_phone),
            organization_location = VALUES(organization_location),
            characterization = VALUES(characterization),
            trademark_registration = VALUES(trademark_registration),
            number_of_members = VALUES(number_of_members)
        ");

        $orgSave->execute([
          $newId,
          ($organization_type === '' ? null : $organization_type),
          ($organization_name === '' ? null : $organization_name),
          ($organization_phone === '' ? null : $orgPhoneDigits),
          ($organization_location === '' ? null : $organization_location),
          ($characterization === '' ? null : $characterization),
          ($trademark_registration === '' ? null : $trademark_registration),
          $number_of_members
        ]);

        $profileSave = $pdo->prepare("
          INSERT INTO student_profiles
          (
            student_id,
            course_purpose,
            future_projection,
            observations,
            notes
          )
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            course_purpose = VALUES(course_purpose),
            future_projection = VALUES(future_projection),
            observations = VALUES(observations),
            notes = VALUES(notes)
        ");

        $profileSave->execute([
          $newId,
          ($course_purpose === '' ? null : $course_purpose),
          ($future_projection === '' ? null : $future_projection),
          ($observations === '' ? null : $observations),
          ($notes === '' ? null : $notes)
        ]);
      }

      $pdo->commit();

      if ($id > 0) {
        log_activity(
          $pdo,
          (int)$_SESSION['user']['id'],
          'student_updated',
          "Se editó el estudiante {$full_name} con ID {$id}"
        );
      } else {
        log_activity(
          $pdo,
          (int)$_SESSION['user']['id'],
          'student_created',
          "Se creó el estudiante {$full_name} con ID {$newId} y código {$studentCode}"
        );
      }

      header("Location: students.php");
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $error = "Ocurrió un error al guardar el estudiante: " . $e->getMessage();
    }
  }

  // Rehidratar
  $row['teacher_id'] = $teacher_id;
  $row['full_name'] = $full_name;
  $row['sex'] = $sex;
  $row['education_level'] = $education_level;
  $row['profession'] = $profession;
  $row['nationality'] = $nationality;
  $row['school_id'] = $school_id;
  $row['course_type'] = $course_type;
  $row['course_level'] = $course_level;
  $row['phone'] = $phone;
  $row['cedula'] = $cedula;
  $row['department_id'] = $department_id;
  $row['municipality_id'] = $municipality_id;
  $row['municipality'] = $municipality_name;
  $row['community'] = $community;
  $row['organization_type'] = $organization_type;
  $row['organization_name'] = $organization_name;
  $row['organization_phone'] = $organization_phone;
  $row['organization_location'] = $organization_location;
  $row['characterization'] = $characterization;
  $row['trademark_registration'] = $trademark_registration;
  $row['course_purpose'] = $course_purpose;
  $row['number_of_members'] = $number_of_members_raw;
  $row['future_projection'] = $future_projection;
  $row['enrolled_at'] = $enrolled_at;
  $row['observations'] = $observations;
  $row['notes'] = $notes;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1080px;width:100%;margin:0 auto;}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;}
    .col4{grid-column:span 4}
    .col6{grid-column:span 6}
    .col8{grid-column:span 8}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col4,.col6,.col8{grid-column:span 12}}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;}
    .btnS,.btnG{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:14px;font-weight:800;text-decoration:none;cursor:pointer;}
    .btnS{border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);}
    .btnG{border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.05);color:#cbd5e1;}
    textarea{resize:vertical}
    .hint{font-size:12px;color:var(--muted);margin-top:6px}
    .hero{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:12px;}
    .info-box{margin-top:12px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid var(--line);color:var(--muted);line-height:1.6;font-size:14px;}
    .readonly-box{padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:rgba(255,255,255,.04);color:#e5e7eb;font-weight:700;min-height:46px;display:flex;align-items:center;}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;"><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</h2>
        <p style="margin:0;color:var(--muted);">El código del estudiante se genera automáticamente al crear el registro.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px;">
      <?= csrf_input(); ?>

      <div class="grid">

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <div class="field col12">
            <label for="teacher_id">Docente</label>
            <select name="teacher_id" id="teacher_id" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$row['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field col8">
          <label for="full_name">Nombre completo *</label>
          <input id="full_name" name="full_name" value="<?= h($row['full_name']) ?>" required>
        </div>

        <div class="field col4">
          <label>Código del estudiante</label>
          <div class="readonly-box">
            <?= $id > 0 ? h($row['student_code'] ?: 'Sin código') : 'Se generará automáticamente al guardar' ?>
          </div>
        </div>

        <div class="field col4">
          <label for="sex">Sexo</label>
          <select name="sex" id="sex">
            <option value="">Seleccionar</option>
            <option value="masculino" <?= ($row['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
            <option value="femenino" <?= ($row['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
          </select>
        </div>

        <div class="field col4">
          <label for="education_level">Nivel escolar</label>
          <select name="education_level" id="education_level">
            <option value="">Seleccionar</option>
            <option value="secundaria" <?= ($row['education_level'] ?? '') === 'secundaria' ? 'selected' : '' ?>>Secundaria</option>
            <option value="tecnico" <?= ($row['education_level'] ?? '') === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
            <option value="universitario" <?= ($row['education_level'] ?? '') === 'universitario' ? 'selected' : '' ?>>Universitario</option>
          </select>
        </div>

        <div class="field col4">
          <label for="nationality">Nacionalidad</label>
          <input
            type="text"
            id="nationality"
            name="nationality"
            value="<?= h($row['nationality'] ?? '') ?>"
            placeholder="Ej: Nicaragüense"
          >
        </div>

        <div class="field col6">
          <label for="profession">Profesión</label>
          <input id="profession" name="profession" value="<?= h($row['profession'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label for="school_id">Escuela</label>
          <select name="school_id" id="school_id" required>
            <option value="">Seleccionar escuela</option>
            <?php foreach ($schools as $school): ?>
              <option value="<?= (int)$school['id'] ?>" <?= (int)($row['school_id'] ?? 0) === (int)$school['id'] ? 'selected' : '' ?>>
                <?= h($school['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col4">
          <label for="course_type">Tipo de curso</label>
          <select name="course_type" id="course_type">
            <option value="barismo" <?= ($row['course_type'] ?? '') === 'barismo' ? 'selected' : '' ?>>Barismo</option>
            <option value="catacion" <?= ($row['course_type'] ?? '') === 'catacion' ? 'selected' : '' ?>>Catación</option>
          </select>
        </div>

        <div class="field col4">
          <label for="course_level">Nivel</label>
          <select name="course_level" id="course_level">
            <option value="basico" <?= ($row['course_level'] ?? '') === 'basico' ? 'selected' : '' ?>>Básico</option>
            <option value="avanzado" <?= ($row['course_level'] ?? '') === 'avanzado' ? 'selected' : '' ?>>Avanzado</option>
            <option value="intensivo" <?= ($row['course_level'] ?? '') === 'intensivo' ? 'selected' : '' ?>>Intensivo</option>
          </select>
        </div>

        <div class="field col4">
          <label for="enrolled_at">Fecha de inscripción</label>
          <input type="date" id="enrolled_at" name="enrolled_at" value="<?= h($row['enrolled_at'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label for="phone">Teléfono</label>
          <input id="phone" name="phone" value="<?= h($row['phone'] ?? '') ?>" placeholder="Ej: 88888888" maxlength="8" inputmode="numeric">
          <div class="hint">Debe tener exactamente 8 dígitos.</div>
        </div>

        <div class="field col4">
          <label for="cedula">Cédula</label>
          <input
            id="cedula"
            name="cedula"
            value="<?= h($row['cedula'] ?? '') ?>"
            placeholder="Ej: 0011234567890A"
            maxlength="14"
            pattern="\d{13}[A-Za-z]"
            title="Debe contener 13 números y 1 letra"
            style="text-transform:uppercase;"
          >
          <div class="hint">Debe tener exactamente 13 números y 1 letra.</div>
        </div>

        <div class="field col4">
          <label for="departmentSelect">Departamento</label>
          <select name="department_id" id="departmentSelect">
            <option value="">Seleccionar departamento</option>
            <?php foreach ($departments as $dep): ?>
              <option value="<?= (int)$dep['id'] ?>" <?= (int)($row['department_id'] ?? 0) === (int)$dep['id'] ? 'selected' : '' ?>>
                <?= h($dep['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col4">
          <label for="municipalitySelect">Municipio</label>
          <select name="municipality" id="municipalitySelect" data-selected="<?= h($row['municipality'] ?? '') ?>">
            <option value="">Seleccionar municipio</option>
          </select>
          <div class="hint">Seleccioná primero el departamento y luego el municipio.</div>
        </div>

        <div class="field col4">
          <label for="community">Comunidad</label>
          <input id="community" name="community" value="<?= h($row['community'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label for="organization_type">Tipo de organización</label>
          <select name="organization_type" id="organization_type">
            <option value="">Seleccionar</option>
            <option value="institucion" <?= ($row['organization_type'] ?? '') === 'institucion' ? 'selected' : '' ?>>Institución</option>
            <option value="privado" <?= ($row['organization_type'] ?? '') === 'privado' ? 'selected' : '' ?>>Privado</option>
            <option value="emprendimiento" <?= ($row['organization_type'] ?? '') === 'emprendimiento' ? 'selected' : '' ?>>Emprendimiento</option>
            <option value="estudiante" <?= ($row['organization_type'] ?? '') === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
            <option value="productor" <?= ($row['organization_type'] ?? '') === 'productor' ? 'selected' : '' ?>>Productor</option>
          </select>
        </div>

        <div class="field col4">
          <label for="organization_name">Nombre de organización</label>
          <input id="organization_name" name="organization_name" value="<?= h($row['organization_name'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label for="organization_phone">Teléfono de la organización</label>
          <input id="organization_phone" name="organization_phone" value="<?= h($row['organization_phone'] ?? '') ?>" placeholder="Ej: 88888888" maxlength="8" inputmode="numeric">
        </div>

        <div class="field col6">
          <label for="organization_location">Ubicación de la organización</label>
          <input id="organization_location" name="organization_location" value="<?= h($row['organization_location'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label for="characterization">Caracterización</label>
          <select name="characterization" id="characterization">
            <option value="">Seleccionar</option>
            <?php foreach ($characterizationOptions as $value => $label): ?>
              <option value="<?= h($value) ?>" <?= ($row['characterization'] ?? '') === $value ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col4">
          <label for="trademark_registration">Registro de marca</label>
          <select name="trademark_registration" id="trademark_registration">
            <option value="">Seleccionar</option>
            <option value="si" <?= ($row['trademark_registration'] ?? '') === 'si' ? 'selected' : '' ?>>Sí</option>
            <option value="no" <?= ($row['trademark_registration'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
          </select>
        </div>

        <div class="field col4">
          <label for="number_of_members">Número de socios</label>
          <input type="number" min="0" id="number_of_members" name="number_of_members" value="<?= h($row['number_of_members'] ?? '') ?>">
        </div>

        <div class="field col12">
          <label for="course_purpose">Propósito del curso</label>
          <textarea id="course_purpose" name="course_purpose" rows="3"><?= h($row['course_purpose'] ?? '') ?></textarea>
        </div>

        <div class="field col12">
          <label for="future_projection">Proyección a futuro</label>
          <textarea id="future_projection" name="future_projection" rows="3"><?= h($row['future_projection'] ?? '') ?></textarea>
        </div>

        <div class="field col12">
          <label for="observations">Observaciones</label>
          <textarea id="observations" name="observations" rows="4"><?= h($row['observations'] ?? '') ?></textarea>
        </div>

        <div class="field col12">
          <label for="notes">Notas internas</label>
          <textarea id="notes" name="notes" rows="3"><?= h($row['notes'] ?? '') ?></textarea>
        </div>

      </div>

      <div class="actions">
        <button class="btn" type="submit"><?= $id > 0 ? 'Guardar cambios' : 'Crear estudiante' ?></button>
        <a class="btnS" href="students.php">← Cancelar</a>
        <a class="btnG" href="dashboard.php">Ir al dashboard</a>
      </div>
    </form>

    <div class="info-box">
      Verificá bien los datos personales, ubicación, organización y formación antes de guardar.
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

const municipiosPorDepartamento = <?= json_encode($municipios, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function loadMunicipalities() {
  const depSelect = document.getElementById('departmentSelect');
  const muniSelect = document.getElementById('municipalitySelect');
  const selected = muniSelect.dataset.selected || '';
  const depId = parseInt(depSelect.value || '0', 10);

  muniSelect.innerHTML = '<option value="">Seleccionar municipio</option>';

  if (depId && municipiosPorDepartamento[depId]) {
    municipiosPorDepartamento[depId].forEach(function(muni) {
      const opt = document.createElement('option');
      opt.value = muni.name;
      opt.textContent = muni.name;
      if (muni.name === selected) {
        opt.selected = true;
      }
      muniSelect.appendChild(opt);
    });
  }
}

document.addEventListener('DOMContentLoaded', function() {
  loadMunicipalities();

  document.getElementById('departmentSelect').addEventListener('change', function() {
    const muniSelect = document.getElementById('municipalitySelect');
    muniSelect.dataset.selected = '';
    loadMunicipalities();
  });

  const cedulaInput = document.getElementById('cedula');
  if (cedulaInput) {
    cedulaInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);
    });
  }
});
</script>
</body>
</html>
