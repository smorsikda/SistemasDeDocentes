<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';


requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'] ?? null;

if (($me['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado');
}

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalizeText($value){
    return trim((string)$value);
}

function normalizeLower($value){
    return mb_strtolower(trim((string)$value), 'UTF-8');
}

function normalizePhone($value){
    $digits = preg_replace('/\D+/', '', (string)$value);
    return $digits === '' ? null : $digits;
}

function normalizeCedula($value){
    $value = strtoupper(trim((string)$value));
    return $value === '' ? null : $value;
}

function normalizeNullable($value){
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function normalizeGrade($value){
    $value = trim((string)$value);
    if ($value === '') return null;

    $value = str_replace(',', '.', $value);
    if (!is_numeric($value)) return false;

    $num = (float)$value;
    if ($num < 0 || $num > 100) return false;

    return round($num, 2);
}

function calcStudentStatus($finalGrade){
    if ($finalGrade === null) return 'pendiente';
    return ((float)$finalGrade >= 60) ? 'aprobado' : 'desaprobado';
}

function normalizeDateInput($value){
    $value = trim((string)$value);
    if ($value === '') return null;

    // acepta YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        [$y, $m, $d] = explode('-', $value);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return $value;
        }
        return false;
    }

    // acepta DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
        [$d, $m, $y] = explode('/', $value);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }

    return false;
}

function detectDelimiter($firstLine){
    $comma = substr_count($firstLine, ',');
    $semicolon = substr_count($firstLine, ';');
    return ($semicolon > $comma) ? ';' : ',';
}

function readCsvRows($tmpPath){
    $lines = file($tmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines || !isset($lines[0])) {
        return [null, [], "El archivo CSV está vacío."];
    }

    $delimiter = detectDelimiter($lines[0]);

    $fh = fopen($tmpPath, 'r');
    if (!$fh) {
        return [null, [], "No se pudo leer el archivo CSV."];
    }

    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) {
        fclose($fh);
        return [null, [], "No se pudo leer el encabezado del CSV."];
    }

    $header = array_map(function($v){
        return trim((string)$v);
    }, $header);

    $rows = [];
    while (($data = fgetcsv($fh, 0, $delimiter)) !== false) {
        if ($data === [null] || $data === false) continue;

        while (count($data) < count($header)) {
            $data[] = '';
        }

        if (count($data) > count($header)) {
            $data = array_slice($data, 0, count($header));
        }

        $rows[] = array_combine($header, $data);
    }

    fclose($fh);
    return [$header, $rows, null];
}

$requiredHeaders = [
    'full_name',
    'school',
    'course_type',
    'course_level',
    'student_year'
];

$allowedSex = ['masculino', 'femenino'];
$allowedEducation = ['secundaria', 'tecnico', 'universitario'];
$allowedCourseType = ['barismo', 'catacion'];
$allowedCourseLevel = ['basico', 'avanzado', 'intensivo'];
$allowedOrganizationType = ['institucion', 'privado', 'emprendimiento', 'estudiante', 'productor'];
$allowedTrademark = ['si', 'no'];

$errors = [];
$success = '';
$previewRows = [];
$previewReady = false;

// =====================================================
// Catálogos
// =====================================================
$teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role = 'teacher' AND is_active = 1
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

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
    SELECT m.id, m.name, m.department_id
    FROM municipalities m
    WHERE m.is_active = 1
    ORDER BY m.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$schoolMap = [];
foreach ($schools as $s) {
    $schoolMap[normalizeLower($s['name'])] = (int)$s['id'];
}

$departmentMap = [];
foreach ($departments as $d) {
    $departmentMap[normalizeLower($d['name'])] = (int)$d['id'];
}

$municipalityMap = [];
foreach ($municipalityRows as $m) {
    $key = (int)$m['department_id'] . '|' . normalizeLower($m['name']);
    $municipalityMap[$key] = (int)$m['id'];
}

// =====================================================
// PREVIEW
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    verify_csrf_or_die();
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if ($teacherId <= 0) {
        $errors[] = 'Seleccioná un docente.';
    }

    if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Subí un archivo CSV válido.';
    }

    if (!$errors) {
        [$header, $csvRows, $readError] = readCsvRows($_FILES['csv']['tmp_name']);

        if ($readError) {
            $errors[] = $readError;
        } else {
            foreach ($requiredHeaders as $rh) {
                if (!in_array($rh, $header, true)) {
                    $errors[] = "Falta la columna obligatoria: {$rh}";
                }
            }

            if (!$errors) {
                $preparedValidRows = [];
                $rowNumber = 1;

                foreach ($csvRows as $raw) {
                    $rowNumber++;

                    $rowErrors = [];

                    $full_name = normalizeText($raw['full_name'] ?? '');
                    $sex = normalizeLower($raw['sex'] ?? '');
                    $education_level = normalizeLower($raw['education_level'] ?? '');
                    $profession = normalizeText($raw['profession'] ?? '');
                    $nationality = normalizeText($raw['nationality'] ?? '');

                    $schoolName = normalizeText($raw['school'] ?? '');
                    $course_type = normalizeLower($raw['course_type'] ?? '');
                    $course_level = normalizeLower($raw['course_level'] ?? '');

                    $phone = normalizePhone($raw['phone'] ?? '');
                    $cedula = normalizeCedula($raw['cedula'] ?? '');

                    $departmentName = normalizeText($raw['department'] ?? '');
                    $municipalityName = normalizeText($raw['municipality'] ?? '');
                    $community = normalizeText($raw['community'] ?? '');

                    $organization_type = normalizeLower($raw['organization_type'] ?? '');
                    $organization_name = normalizeText($raw['organization_name'] ?? '');
                    $organization_phone = normalizePhone($raw['organization_phone'] ?? '');
                    $organization_location = normalizeText($raw['organization_location'] ?? '');
                    $characterization = normalizeText($raw['characterization'] ?? '');
                    $trademark_registration = normalizeLower($raw['trademark_registration'] ?? '');

                    $number_of_members_raw = trim((string)($raw['number_of_members'] ?? ''));
                    $number_of_members = null;
                    if ($number_of_members_raw !== '') {
                        if (!is_numeric($number_of_members_raw)) {
                            $rowErrors[] = 'Número de socios inválido';
                        } else {
                            $number_of_members = (int)$number_of_members_raw;
                            if ($number_of_members < 0) {
                                $rowErrors[] = 'Número de socios negativo';
                            }
                        }
                    }

                    $course_purpose = normalizeText($raw['course_purpose'] ?? '');
                    $future_projection = normalizeText($raw['future_projection'] ?? '');
                    $observations = normalizeText($raw['observations'] ?? '');
                    $notes = normalizeText($raw['notes'] ?? '');

                    $student_year_raw = trim((string)($raw['student_year'] ?? ''));
                    $student_year = (int)$student_year_raw;
                    if ($student_year <= 0 || !preg_match('/^\d{4}$/', $student_year_raw)) {
                        $rowErrors[] = 'Año de estudiante inválido';
                    }

                    $final_grade = normalizeGrade($raw['final_grade'] ?? '');
                    if ($final_grade === false) {
                        $rowErrors[] = 'Nota final inválida';
                        $final_grade = null;
                    }

                    $enrolled_at = normalizeDateInput($raw['enrolled_at'] ?? '');
                    if ($enrolled_at === false) {
                        $rowErrors[] = 'Fecha de inscripción inválida';
                        $enrolled_at = null;
                    }

                    if ($full_name === '') {
                        $rowErrors[] = 'Nombre completo obligatorio';
                    }

                    if ($schoolName === '') {
                        $rowErrors[] = 'Escuela obligatoria';
                    }

                    if (!in_array($course_type, $allowedCourseType, true)) {
                        $rowErrors[] = 'Tipo de curso inválido';
                    }

                    if (!in_array($course_level, $allowedCourseLevel, true)) {
                        $rowErrors[] = 'Nivel de curso inválido';
                    }

                    if ($sex !== '' && !in_array($sex, $allowedSex, true)) {
                        $rowErrors[] = 'Sexo inválido';
                    }

                    if ($education_level !== '' && !in_array($education_level, $allowedEducation, true)) {
                        $rowErrors[] = 'Nivel escolar inválido';
                    }

                    if ($organization_type !== '' && !in_array($organization_type, $allowedOrganizationType, true)) {
                        $rowErrors[] = 'Tipo de organización inválido';
                    }

                    if ($trademark_registration !== '' && !in_array($trademark_registration, $allowedTrademark, true)) {
                        $rowErrors[] = 'Registro de marca inválido';
                    }

                    if ($phone !== null && strlen($phone) !== 8) {
                        $rowErrors[] = 'Teléfono debe tener 8 dígitos';
                    }

                    if ($organization_phone !== null && strlen($organization_phone) !== 8) {
                        $rowErrors[] = 'Teléfono organización debe tener 8 dígitos';
                    }

                    if ($cedula !== null && !preg_match('/^\d{13}[A-Z]$/', $cedula)) {
                        $rowErrors[] = 'Cédula inválida';
                    }

                    $school_id = null;
                    $schoolKey = normalizeLower($schoolName);
                    if ($schoolName !== '') {
                        $school_id = $schoolMap[$schoolKey] ?? null;
                        if (!$school_id) {
                            $rowErrors[] = 'Escuela no encontrada';
                        }
                    }

                    $department_id = null;
                    if ($departmentName !== '') {
                        $department_id = $departmentMap[normalizeLower($departmentName)] ?? null;
                        if (!$department_id) {
                            $rowErrors[] = 'Departamento no encontrado';
                        }
                    }

                    $municipality_id = null;
                    if ($municipalityName !== '') {
                        if (!$department_id) {
                            $rowErrors[] = 'Municipio requiere departamento válido';
                        } else {
                            $muniKey = $department_id . '|' . normalizeLower($municipalityName);
                            $municipality_id = $municipalityMap[$muniKey] ?? null;
                            if (!$municipality_id) {
                                $rowErrors[] = 'Municipio no encontrado en ese departamento';
                            }
                        }
                    }

                    $status = calcStudentStatus($final_grade);

                    $previewRows[] = [
                        'row_number' => $rowNumber,
                        'full_name' => $full_name,
                        'school' => $schoolName,
                        'course_type' => $course_type,
                        'course_level' => $course_level,
                        'department' => $departmentName,
                        'municipality' => $municipalityName,
                        'student_year' => $student_year_raw,
                        'final_grade' => $final_grade,
                        'status' => $status,
                        'errors' => $rowErrors,
                        'valid' => empty($rowErrors),
                    ];

                    if (empty($rowErrors)) {
                        $preparedValidRows[] = [
                            'teacher_id' => $teacherId,
                            'full_name' => $full_name,
                            'sex' => ($sex === '' ? null : $sex),
                            'education_level' => ($education_level === '' ? null : $education_level),
                            'profession' => normalizeNullable($profession),
                            'nationality' => normalizeNullable($nationality),
                            'school_id' => $school_id,
                            'course_type' => $course_type,
                            'course_level' => $course_level,
                            'phone' => $phone,
                            'cedula' => $cedula,
                            'department_id' => $department_id,
                            'municipality_id' => $municipality_id,
                            'community' => normalizeNullable($community),
                            'organization_type' => ($organization_type === '' ? null : $organization_type),
                            'organization_name' => normalizeNullable($organization_name),
                            'organization_phone' => $organization_phone,
                            'organization_location' => normalizeNullable($organization_location),
                            'characterization' => normalizeNullable($characterization),
                            'trademark_registration' => ($trademark_registration === '' ? null : $trademark_registration),
                            'number_of_members' => $number_of_members,
                            'course_purpose' => normalizeNullable($course_purpose),
                            'future_projection' => normalizeNullable($future_projection),
                            'enrolled_at' => $enrolled_at,
                            'student_year' => $student_year,
                            'final_grade' => $final_grade,
                            'status' => $status,
                            'observations' => normalizeNullable($observations),
                            'notes' => normalizeNullable($notes),
                        ];
                    }
                }

                $_SESSION['students_import_teacher_id'] = $teacherId;
                $_SESSION['students_import_valid_rows'] = $preparedValidRows;
                $previewReady = true;
            }
        }
    }
}

// =====================================================
// IMPORT
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    verify_csrf_or_die();

    $validRows = $_SESSION['students_import_valid_rows'] ?? [];
    $teacherId = (int)($_SESSION['students_import_teacher_id'] ?? 0);

    if (!$validRows || $teacherId <= 0) {
        $errors[] = 'No hay datos preparados para importar. Primero hacé la vista previa.';
    } else {
        try {
            $pdo->beginTransaction();

            $insStudent = $pdo->prepare("
                INSERT INTO students
                (
                    teacher_id, school_id, department_id, municipality_id,
                    full_name, sex, education_level, profession, nationality,
                    phone, cedula, course_type, course_level, enrolled_at,
                    community, final_grade, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $upCode = $pdo->prepare("
                UPDATE students
                SET student_code = ?
                WHERE id = ?
            ");

            $insOrg = $pdo->prepare("
                INSERT INTO student_organizations
                (
                    student_id, organization_type, organization_name,
                    organization_phone, organization_location,
                    characterization, trademark_registration,
                    number_of_members
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insProfile = $pdo->prepare("
                INSERT INTO student_profiles
                (
                    student_id, course_purpose, future_projection,
                    observations, notes
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $importedCount = 0;

            foreach ($validRows as $r) {
                $insStudent->execute([
                    $r['teacher_id'],
                    $r['school_id'],
                    $r['department_id'],
                    $r['municipality_id'],
                    $r['full_name'],
                    $r['sex'],
                    $r['education_level'],
                    $r['profession'],
                    $r['nationality'],
                    $r['phone'],
                    $r['cedula'],
                    $r['course_type'],
                    $r['course_level'],
                    $r['enrolled_at'],
                    $r['community'],
                    $r['final_grade'],
                    $r['status'],
                ]);

                $newId = (int)$pdo->lastInsertId();
                $studentCode = 'EST-' . $r['student_year'] . '-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);
                $upCode->execute([$studentCode, $newId]);

                $insOrg->execute([
                    $newId,
                    $r['organization_type'],
                    $r['organization_name'],
                    $r['organization_phone'],
                    $r['organization_location'],
                    $r['characterization'],
                    $r['trademark_registration'],
                    $r['number_of_members'],
                ]);

                $insProfile->execute([
                    $newId,
                    $r['course_purpose'],
                    $r['future_projection'],
                    $r['observations'],
                    $r['notes'],
                ]);

                $importedCount++;
            }

            $pdo->commit();

            unset($_SESSION['students_import_valid_rows'], $_SESSION['students_import_teacher_id']);
            $success = "Importación completada correctamente. Estudiantes importados: {$importedCount}";
            $previewRows = [];
            $previewReady = false;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Ocurrió un error al importar los estudiantes.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Importar estudiantes CSV</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1380px;width:100%;margin:0 auto;}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
    .hero{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;}
    .muted{color:var(--muted);}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}
    .btnS,.btnG,.btn2{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;font-weight:800;text-decoration:none;cursor:pointer;}
    .btnS{border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);}
    .btnG{border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.05);color:#cbd5e1;}
    .btn2{background:linear-gradient(180deg,var(--green),var(--green2));color:#06110a;border:none;}
    .alert{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(255,90,95,.10);border:1px solid rgba(255,90,95,.35);color:#fff;}
    .ok-msg{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(47,191,113,.10);border:1px solid rgba(47,191,113,.35);color:var(--green);font-weight:700;}
    .note-box{margin-top:14px;padding:14px;border-radius:14px;background:rgba(255,255,255,.04);border:1px solid var(--line);color:#e5e7eb;line-height:1.7;}
    .table-wrap{overflow-x:auto;margin-top:16px;}
    table{width:100%;min-width:1100px;border-collapse:collapse;}
    th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:top;}
    th{color:var(--muted);background:rgba(255,255,255,.04);white-space:nowrap;}
    tr:hover{background:rgba(255,255,255,.04);}
    .pill{display:inline-flex;align-items:center;justify-content:center;min-width:72px;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-weight:800;}
    .pill.ok{border-color:rgba(34,197,94,.35);color:#22c55e;background:rgba(34,197,94,.10);}
    .pill.bad{border-color:rgba(239,68,68,.35);color:#fca5a5;background:rgba(239,68,68,.10);}
    .small{font-size:12px;color:var(--muted);}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;}
    @media(max-width:860px){.field-row{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Importar estudiantes desde CSV</h2>
        <p style="margin:0;color:var(--muted);">
          Subí un archivo CSV, revisá la vista previa y luego confirmá la importación.
        </p>
      </div>
      <div class="actions" style="margin-top:0;">
        <a class="btnG" href="students.php">← Volver</a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert">
        <?php foreach ($errors as $e): ?>
          <div><?= h($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="ok-msg"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="note-box">
      <b>Columnas obligatorias del CSV:</b><br>
      full_name, school, course_type, course_level, student_year
      <br><br>
      <b>Columnas recomendadas:</b><br>
      sex, education_level, profession, nationality, phone, cedula, department, municipality, community,
      organization_type, organization_name, organization_phone, organization_location, characterization,
      trademark_registration, number_of_members, course_purpose, future_projection, enrolled_at, final_grade,
      observations, notes
    </div>

    <form method="post" enctype="multipart/form-data" style="margin-top:16px;">
              <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="preview">

      <div class="field-row">
        <div class="field">
          <label>Docente responsable</label>
          <select name="teacher_id" required>
            <option value="">Seleccionar docente</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)($_POST['teacher_id'] ?? 0) === (int)$t['id']) ? 'selected' : '' ?>>
                <?= h($t['name']) ?> (<?= h($t['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Archivo CSV</label>
          <input type="file" name="csv" accept=".csv" required>
        </div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Previsualizar importación</button>
      </div>
    </form>

    <?php if ($previewRows): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Fila</th>
              <th>Nombre</th>
              <th>Escuela</th>
              <th>Curso</th>
              <th>Nivel</th>
              <th>Departamento</th>
              <th>Municipio</th>
              <th>Año</th>
              <th>Nota final</th>
              <th>Estado</th>
              <th>Validación</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($previewRows as $r): ?>
              <tr>
                <td><?= (int)$r['row_number'] ?></td>
                <td><?= h($r['full_name']) ?></td>
                <td><?= h($r['school']) ?></td>
                <td><?= h($r['course_type']) ?></td>
                <td><?= h($r['course_level']) ?></td>
                <td><?= h($r['department'] ?: '—') ?></td>
                <td><?= h($r['municipality'] ?: '—') ?></td>
                <td><?= h($r['student_year']) ?></td>
                <td><?= h($r['final_grade'] !== null ? $r['final_grade'] : '—') ?></td>
                <td><?= h($r['status']) ?></td>
                <td>
                  <?php if ($r['valid']): ?>
                    <span class="pill ok">OK</span>
                  <?php else: ?>
                    <span class="pill bad">Error</span>
                    <div class="small" style="margin-top:6px;"><?= h(implode(' | ', $r['errors'])) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php
        $validCount = 0;
        $errorCount = 0;
        foreach ($previewRows as $r) {
            if ($r['valid']) $validCount++;
            else $errorCount++;
        }
      ?>

      <div class="actions">
        <span class="btnG">Válidas: <?= (int)$validCount ?></span>
        <span class="btnG">Con error: <?= (int)$errorCount ?></span>
      </div>

      <?php if ($validCount > 0): ?>
        <form method="post" style="margin-top:10px;">
                  <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="import">
          <div class="actions">
            <button class="btn2" type="submit">Importar filas válidas</button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

  </section>
</main>
</body>
</html>
