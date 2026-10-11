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

function normalizeGrade($value){
  $value = trim((string)$value);
  if ($value === '') return null;
  $value = str_replace(',', '.', $value);
  if (!is_numeric($value)) return false;
  $num = (float)$value;
  if ($num < 0 || $num > 100) return false;
  return round($num, 2);
}

function calcFinalGrade($grades){
  $hasAny = false;
  $sum = 0;

  foreach ($grades as $g) {
    if ($g !== null) {
      $hasAny = true;
      $sum += (float)$g;
    }
  }

  return $hasAny ? round($sum, 2) : null;
}

function calcStudentStatus($final){
  if ($final === null) return 'pendiente';
  return $final >= 60 ? 'aprobado' : 'desaprobado';
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}

$groupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
$saved = isset($_GET['saved']) && $_GET['saved'] == '1';

// =====================================================
// Traer grupos
// =====================================================
$whereGroups = "";
$paramsGroups = [];

if (($me['role'] ?? '') === 'teacher') {
  $whereGroups = "WHERE g.teacher_id = ?";
  $paramsGroups[] = (int)$me['id'];
}

$sqlGroups = "
SELECT
  g.id,
  g.group_code,
  g.name,
  g.course_type,
  g.course_level,
  g.status,
  u.name AS teacher_name
FROM groups_table g
JOIN users u ON u.id = g.teacher_id
$whereGroups
ORDER BY
  CASE WHEN g.status='activo' THEN 0 ELSE 1 END,
  g.created_at DESC
";

$stmt = $pdo->prepare($sqlGroups);
$stmt->execute($paramsGroups);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// Traer grupo seleccionado
// =====================================================
$group = null;
$isFinalized = false;

if ($groupId > 0) {
  $stmt = $pdo->prepare("
    SELECT g.*, u.name AS teacher_name
    FROM groups_table g
    JOIN users u ON u.id = g.teacher_id
    WHERE g.id = ?
    LIMIT 1
  ");
  $stmt->execute([$groupId]);
  $group = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$group) {
    http_response_code(404);
    exit("Grupo no encontrado");
  }

  if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit("Acceso denegado");
  }

  $isFinalized = (($group['status'] ?? '') === 'finalizado');
}

// =====================================================
// Guardar notas
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $group) {
  verify_csrf_or_die();

  if ($isFinalized) {
    exit("Este grupo ya fue finalizado y no admite cambios en las notas.");
  }

  $grades = $_POST['grades'] ?? [];
  $gradeError = '';

  if (!is_array($grades)) {
    $gradeError = 'Los datos de notas enviados no son válidos.';
  } else {
    $stmt = $pdo->prepare('SELECT student_id FROM group_students WHERE group_id = ?');
    $stmt->execute([$groupId]);
    $allowedStudentIds = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    foreach ($grades as $studentId => $studentGrades) {
      if (!ctype_digit((string)$studentId) || !isset($allowedStudentIds[(int)$studentId]) || !is_array($studentGrades)) {
        $gradeError = 'La lista incluye estudiantes o datos que no pertenecen a este grupo.';
        break;
      }
      foreach (['exam1', 'exam2', 'exam3', 'exam4', 'exam5'] as $exam) {
        $grade = normalizeGrade($studentGrades[$exam] ?? '');
        if ($grade === false) {
          $gradeError = 'Cada nota debe ser un número entre 0 y 100.';
          break 2;
        }
      }
    }
  }

  $selExisting = $pdo->prepare("
    SELECT id
    FROM student_grades
    WHERE group_id = ? AND student_id = ?
    LIMIT 1
  ");

  $insGrade = $pdo->prepare("
    INSERT INTO student_grades
    (group_id, student_id, exam1, exam2, exam3, exam4, exam5, final_grade, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $updGrade = $pdo->prepare("
    UPDATE student_grades
    SET exam1 = ?, exam2 = ?, exam3 = ?, exam4 = ?, exam5 = ?, final_grade = ?, status = ?
    WHERE group_id = ? AND student_id = ?
  ");

  if ($gradeError !== '') {
    $error = $gradeError;
  } else try {
    $pdo->beginTransaction();

    $updatedStudentsCount = 0;

    foreach ($grades as $studentId => $g) {
      $studentId = (int)$studentId;

      $exam1 = normalizeGrade($g['exam1'] ?? null);
      $exam2 = normalizeGrade($g['exam2'] ?? null);
      $exam3 = normalizeGrade($g['exam3'] ?? null);
      $exam4 = normalizeGrade($g['exam4'] ?? null);
      $exam5 = normalizeGrade($g['exam5'] ?? null);

      $final = calcFinalGrade([$exam1, $exam2, $exam3, $exam4, $exam5]);
      $status = calcStudentStatus($final);

      $selExisting->execute([$groupId, $studentId]);
      $exists = $selExisting->fetchColumn();

      if ($exists) {
        $updGrade->execute([
          $exam1, $exam2, $exam3, $exam4, $exam5, $final, $status,
          $groupId, $studentId
        ]);
      } else {
        $insGrade->execute([
          $groupId, $studentId,
          $exam1, $exam2, $exam3, $exam4, $exam5, $final, $status
        ]);
      }

      $updatedStudentsCount++;
    }

    $pdo->commit();

    log_activity(
      $pdo,
      (int)$_SESSION['user']['id'],
      'grades_updated',
      "Se guardaron notas del grupo {$group['group_code']} ({$group['name']}) con ID {$groupId}. Estudiantes actualizados: {$updatedStudentsCount}"
    );

    header("Location: grades.php?group_id={$groupId}&saved=1");
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $error = "Ocurrió un error al guardar las notas.";
  }
}

// =====================================================
// Traer estudiantes del grupo + notas existentes
// =====================================================
$students = [];
if ($group) {
  $stmt = $pdo->prepare("
    SELECT
      s.id,
      s.full_name,
      s.student_code,
      sc.name AS school_name,
      sg.exam1,
      sg.exam2,
      sg.exam3,
      sg.exam4,
      sg.exam5,
      sg.final_grade,
      sg.status AS group_status
    FROM group_students gs
    JOIN students s
      ON s.id = gs.student_id
    LEFT JOIN schools sc
      ON sc.id = s.school_id
    LEFT JOIN student_grades sg
      ON sg.student_id = s.id
     AND sg.group_id = gs.group_id
    WHERE gs.group_id = ?
    ORDER BY s.full_name ASC
  ");
  $stmt->execute([$groupId]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Notas</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1480px;width:100%;margin:0 auto;}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);}
    .hero{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;}
    .muted{color:var(--muted);}
    .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:14px;}
    .filters .field{margin-top:0;min-width:260px;}
    .ok-msg{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(47,191,113,.10);border:1px solid rgba(47,191,113,.35);color:var(--green);font-weight:700;}
    .warn-msg{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(245,158,11,.10);border:1px solid rgba(245,158,11,.35);color:#f59e0b;font-weight:700;}
    .error-msg{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.35);color:#ef4444;font-weight:700;}
    .group-info{margin-top:14px;padding:14px;border-radius:14px;border:1px solid var(--line);background:rgba(255,255,255,.04);display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
    .group-info .k{font-size:12px;color:var(--muted);margin-bottom:4px;}
    .group-info .v{font-weight:800;color:#e5e7eb;}
    .table-wrap{overflow-x:auto;margin-top:14px;}
    table{width:100%;min-width:1050px;border-collapse:collapse;}
    th, td{padding:12px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle;}
    th{color:var(--muted);font-weight:700;background:rgba(255,255,255,.04);white-space:nowrap;}
    tr:hover{background:rgba(255,255,255,.04);}
    .student-name{font-weight:800;color:#e5e7eb;}
    .small{font-size:12px;color:var(--muted);margin-top:4px;}
    .grade-input{width:90px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:rgba(255,255,255,.05);color:#fff;outline:none;}
    .grade-input:focus{border-color:rgba(47,191,113,.45);box-shadow:0 0 0 3px rgba(47,191,113,.10);}
    .grade-input[disabled]{opacity:.55;cursor:not-allowed;}
    .final-box{display:inline-block;min-width:90px;padding:10px 12px;border-radius:10px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800;text-align:center;}
    .status-pill{display:inline-flex;align-items:center;justify-content:center;min-width:110px;padding:8px 10px;border-radius:999px;font-size:12px;font-weight:800;text-transform:capitalize;border:1px solid var(--line);}
    .status-pill.aprobado{color:#22c55e;border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.10);}
    .status-pill.desaprobado{color:#ef4444;border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10);}
    .status-pill.pendiente{color:#f59e0b;border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.10);}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}
    .btnS{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800;text-decoration:none;cursor:pointer;}
    .btnG{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.05);color:#cbd5e1;font-weight:800;text-decoration:none;cursor:pointer;}
    .btnMuted{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.04);color:#94a3b8;font-weight:800;text-decoration:none;cursor:not-allowed;}
    .note-help{margin-top:10px;color:var(--muted);font-size:12px;}
    .empty{padding:20px;text-align:center;color:var(--muted);font-weight:700;}
    @media(max-width:960px){.group-info{grid-template-columns:1fr 1fr;}}
    @media(max-width:640px){.group-info{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Notas</h2>
        <p style="margin:0;color:var(--muted);">
          Seleccioná un grupo y registrá las notas de hasta 5 evaluaciones. Podés dejar casillas vacías si el grupo tuvo menos evaluaciones.
        </p>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="field">
        <label>Grupo</label>
        <select name="group_id" onchange="this.form.submit()">
          <option value="0">Seleccionar grupo</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= $groupId === (int)$g['id'] ? 'selected' : '' ?>>
              <?= h($g['group_code']) ?> • <?= h($g['name']) ?> • <?= h(courseLabel($g['course_type'])) ?> / <?= h(levelLabel($g['course_level'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if ($saved): ?>
      <div class="ok-msg">Notas guardadas correctamente.</div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($group && $isFinalized): ?>
      <div class="warn-msg">Este grupo ya fue finalizado. Las notas se muestran solo en modo lectura.</div>
    <?php endif; ?>

    <?php if ($group): ?>
      <div class="group-info">
        <div>
          <div class="k">Código</div>
          <div class="v"><?= h($group['group_code']) ?></div>
        </div>
        <div>
          <div class="k">Grupo</div>
          <div class="v"><?= h($group['name']) ?></div>
        </div>
        <div>
          <div class="k">Curso</div>
          <div class="v"><?= h(courseLabel($group['course_type'])) ?> / <?= h(levelLabel($group['course_level'])) ?></div>
        </div>
        <div>
          <div class="k">Docente</div>
          <div class="v"><?= h($group['teacher_name']) ?></div>
        </div>
      </div>

      <div class="note-help">
        Ingresá notas entre 0 y 100. Podés dejar casillas vacías si no aplica esa evaluación.
      </div>

      <form method="post">
        <?= csrf_input(); ?>
        <input type="hidden" name="group_id" value="<?= (int)$groupId ?>">

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Estudiante</th>
                <th>Prueba 1</th>
                <th>Prueba 2</th>
                <th>Prueba 3</th>
                <th>Prueba 4</th>
                <th>Prueba 5</th>
                <th>Nota final</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($students): ?>
                <?php foreach ($students as $s): ?>
                  <?php $displayStatus = $s['group_status'] ?: 'pendiente'; ?>
                  <tr>
                    <td>
                      <div class="student-name"><?= h($s['full_name']) ?></div>
                      <div class="small">
                        <?= h($s['student_code'] ?: '—') ?> • <?= h($s['school_name'] ?: '—') ?>
                      </div>
                    </td>

                    <td><input class="grade-input" type="number" name="grades[<?= (int)$s['id'] ?>][exam1]" min="0" step="0.01" value="<?= h($s['exam1'] ?? '') ?>" <?= $isFinalized ? 'disabled' : '' ?>></td>
                    <td><input class="grade-input" type="number" name="grades[<?= (int)$s['id'] ?>][exam2]" min="0" step="0.01" value="<?= h($s['exam2'] ?? '') ?>" <?= $isFinalized ? 'disabled' : '' ?>></td>
                    <td><input class="grade-input" type="number" name="grades[<?= (int)$s['id'] ?>][exam3]" min="0" step="0.01" value="<?= h($s['exam3'] ?? '') ?>" <?= $isFinalized ? 'disabled' : '' ?>></td>
                    <td><input class="grade-input" type="number" name="grades[<?= (int)$s['id'] ?>][exam4]" min="0" step="0.01" value="<?= h($s['exam4'] ?? '') ?>" <?= $isFinalized ? 'disabled' : '' ?>></td>
                    <td><input class="grade-input" type="number" name="grades[<?= (int)$s['id'] ?>][exam5]" min="0" step="0.01" value="<?= h($s['exam5'] ?? '') ?>" <?= $isFinalized ? 'disabled' : '' ?>></td>

                    <td>
                      <span class="final-box"><?= h($s['final_grade'] ?? '—') ?></span>
                    </td>

                    <td>
                      <span class="status-pill <?= h($displayStatus) ?>">
                        <?= h($displayStatus) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="empty">Este grupo no tiene estudiantes asignados.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($students): ?>
          <div class="actions">
            <?php if (!$isFinalized): ?>
              <button class="btn" type="submit">Guardar notas</button>
            <?php else: ?>
              <span class="btnMuted">Grupo finalizado</span>
            <?php endif; ?>
            <a class="btnG" href="groups.php">← Volver a grupos</a>
          </div>
        <?php endif; ?>
      </form>
    <?php else: ?>
      <div class="empty">Seleccioná un grupo para comenzar a registrar notas.</div>
    <?php endif; ?>
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
