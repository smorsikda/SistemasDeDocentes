<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';

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

$groupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
$date = trim((string)($_POST['date'] ?? $_GET['date'] ?? date('Y-m-d')));
$error = '';
$saved = isset($_GET['saved']) && $_GET['saved'] == '1';

function isValidAttendanceDate(string $date): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) return false;
    return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
}

if ($groupId <= 0) {
    exit('Grupo inválido');
}

if (!isValidAttendanceDate($date)) {
    $error = 'La fecha seleccionada no es válida.';
    $date = date('Y-m-d');
}

// =====================================================
// Traer grupo
// =====================================================
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
    exit('Grupo no encontrado');
}

// Si es docente, solo puede entrar a sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

// =====================================================
// Traer estudiantes del grupo
// =====================================================
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.full_name,
        s.student_code
    FROM group_students gs
    JOIN students s ON s.id = gs.student_id
    WHERE gs.group_id = ?
    ORDER BY s.full_name ASC
");
$stmt->execute([$groupId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Guardar asistencia únicamente por POST; una visita GET no crea registros.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $attendanceStatus = $_POST['attendance'] ?? [];
    $validStudentIds = array_fill_keys(array_map(static fn($student) => (string)(int)$student['id'], $students), true);

    if ($error === '' && !is_array($attendanceStatus)) {
        $error = 'Los datos de asistencia enviados no son válidos.';
    }
    if ($error === '' && array_diff(array_map('strval', array_keys($attendanceStatus)), array_keys($validStudentIds))) {
        $error = 'La asistencia contiene estudiantes que no pertenecen a este grupo.';
    }
    if ($error === '') {
        foreach ($students as $student) {
            $studentId = (string)(int)$student['id'];
            if (!array_key_exists($studentId, $attendanceStatus) || !in_array((string)$attendanceStatus[$studentId], ['0', '1'], true)) {
                $error = 'Seleccioná presente o ausente para cada estudiante.';
                break;
            }
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $lockGroup = $pdo->prepare('SELECT status FROM groups_table WHERE id = ? FOR UPDATE');
            $lockGroup->execute([$groupId]);
            if ($lockGroup->fetchColumn() === 'finalizado') {
                throw new RuntimeException('Este grupo ya fue finalizado y no admite nuevas asistencias.');
            }

            $insAttendance = $pdo->prepare('INSERT INTO attendances (group_id, teacher_id, attendance_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
            $insAttendance->execute([$groupId, (int)$group['teacher_id'], $date]);
            $attendanceId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare('INSERT IGNORE INTO attendance_items (attendance_id, student_id, present) VALUES (?, ?, 0)');
            $updateItem = $pdo->prepare('UPDATE attendance_items SET present = ? WHERE attendance_id = ? AND student_id = ?');
            foreach ($students as $student) {
                $studentId = (int)$student['id'];
                $insItem->execute([$attendanceId, $studentId]);
                $updateItem->execute([(int)$attendanceStatus[(string)$studentId], $attendanceId, $studentId]);
            }
            $pdo->commit();
            header("Location: attendance.php?group_id={$groupId}&date=" . rawurlencode($date) . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Ocurrió un error al guardar la asistencia.';
        }
    }
}

// Consultar registros existentes sin escribir en la base.
$stmt = $pdo->prepare('SELECT id FROM attendances WHERE group_id = ? AND attendance_date = ? LIMIT 1');
$stmt->execute([$groupId, $date]);
$attendanceId = (int)($stmt->fetchColumn() ?: 0);
$presentMap = [];
if ($attendanceId > 0) {
    $stmt = $pdo->prepare('SELECT student_id, present FROM attendance_items WHERE attendance_id = ?');
    $stmt->execute([$attendanceId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $presentMap[(int)$row['student_id']] = (int)$row['present'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asistencia</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1180px;width:100%;margin:0 auto}
    .sheet{background:#ffffff;color:#111827;border-radius:18px;box-shadow:0 18px 50px rgba(0,0,0,.25);overflow:hidden}
    .sheet-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:24px 24px 10px;flex-wrap:wrap}
    .sheet-brand img{height:62px;object-fit:contain}
    .sheet-brand h2{margin:10px 0 8px;font-size:28px;line-height:1.1}
    .meta{display:grid;grid-template-columns:repeat(2, minmax(220px, 1fr));gap:10px 18px;margin-top:10px}
    .meta p{margin:0;font-size:14px;color:#374151}
    .meta b{color:#111827}
    .date-box{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
    .date-box label{display:block;font-size:13px;margin-bottom:6px;color:#374151;font-weight:700}
    .date-box input[type="date"]{background:#f3f4f6;color:#111827;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px}
    .toolbar-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:1px solid #bbf7d0;background:#ecfdf5;color:#16a34a;font-weight:800;text-decoration:none;cursor:pointer}
    .toolbar-btn.back{border-color:#d1d5db;background:#f9fafb;color:#374151}
    .toolbar-btn.print{border-color:#bbf7d0;background:#ecfdf5;color:#16a34a}
    .table-wrap{padding:16px 24px 8px}
    table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px;overflow:hidden;border:1px solid #e5e7eb;border-radius:14px}
    thead th{background:#111827;color:#ffffff;text-align:left;padding:14px 16px;font-size:13px;letter-spacing:.2px}
    tbody td{padding:14px 16px;border-top:1px solid #e5e7eb;background:#ffffff;vertical-align:middle}
    tbody tr:nth-child(even) td{background:#f9fafb}
    .col-num{width:90px;font-weight:800;color:#374151}
    .student-name{font-weight:700;color:#111827}
    .student-code{font-size:12px;color:#6b7280;margin-top:4px}
    .attendance-cell{min-width:280px}
    .attendance-group{display:flex;gap:12px;flex-wrap:wrap}
    .attendance-option input{display:none}
    .attendance-option span{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:2px solid #d1d5db;background:#ffffff;color:#374151;font-weight:800;cursor:pointer;transition:.15s ease;user-select:none}
    .attendance-option.present input:checked + span{background:#dcfce7;border-color:#22c55e;color:#166534}
    .attendance-option.absent input:checked + span{background:#fee2e2;border-color:#ef4444;color:#991b1b}
    .attendance-option span:hover{transform:translateY(-1px)}
    .sheet-footer{padding:20px 24px 26px}
    .sign{margin-top:26px;padding-top:18px;width:320px;border-top:1px solid #9ca3af;color:#374151;font-size:14px}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    .save-btn{width:100%;padding:14px 18px;border:none;border-radius:14px;background:linear-gradient(180deg, #34d399, #16a34a);color:#052e16;font-weight:900;cursor:pointer;font-size:15px}
    .save-btn:hover{filter:brightness(.98)}
    .empty{padding:24px;text-align:center;color:#6b7280;font-weight:600}
    .ok-msg{margin:0 24px 8px;padding:12px 14px;border-radius:12px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;font-weight:700}
    .error-msg{margin:0 24px 8px;padding:12px 14px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:700}
    @media print{
      body{background:#fff}
      .no-print,.sidebar,#appSidebar{display:none !important}
      .container{padding:0;max-width:none}
      .sheet{box-shadow:none;border-radius:0}
      .attendance-option span{border:1px solid #9ca3af;background:#fff !important;color:#111 !important}
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="sheet">
    <div class="sheet-head">
      <div class="sheet-brand">
        <img src="/docentes/assets/images/1.png" alt="Logo CONATRADEC">
        <h2>Hoja de Asistencia</h2>

        <div class="meta">
          <p><b>Grupo:</b> <?= h($group['name']) ?></p>
          <p><b>Código:</b> <?= h($group['group_code']) ?></p>
          <p><b>Curso:</b> <?= h(courseLabel($group['course_type'])) ?></p>
          <p><b>Nivel:</b> <?= h(levelLabel($group['course_level'])) ?></p>
          <p><b>Docente:</b> <?= h($group['teacher_name']) ?></p>
          <p><b>Fecha:</b> <?= h($date) ?></p>
        </div>
      </div>

      <div class="date-box no-print">
        <form method="get">
          <input type="hidden" name="group_id" value="<?= $groupId ?>">
          <label for="date">Cambiar fecha</label>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input id="date" type="date" name="date" value="<?= h($date) ?>">
            <button class="toolbar-btn" type="submit">Actualizar</button>
          </div>
        </form>
      </div>
    </div>

    <?php if ($saved): ?>
      <div class="ok-msg">Asistencia guardada correctamente.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if (($group['status'] ?? '') === 'finalizado'): ?>
      <div class="error-msg">Este grupo ya fue finalizado; la asistencia está en modo de solo lectura.</div>
    <?php endif; ?>

    <form method="post">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="group_id" value="<?= $groupId ?>">
      <input type="hidden" name="date" value="<?= h($date) ?>">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px;">Número</th>
              <th>Nombre</th>
              <th style="width:330px;">Asistencia</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students): ?>
              <?php $i = 1; foreach ($students as $s): ?>
                <?php
                  $studentId = (int)$s['id'];
                  $isPresent = isset($presentMap[$studentId]) ? (int)$presentMap[$studentId] : 0;
                ?>
                <tr>
                  <td class="col-num"><?= $i++ ?></td>
                  <td>
                    <div class="student-name"><?= h($s['full_name']) ?></div>
                    <div class="student-code"><?= h($s['student_code'] ?: '—') ?></div>
                  </td>
                  <td class="attendance-cell">
                    <div class="attendance-group">
                      <label class="attendance-option present">
                        <input type="radio" name="attendance[<?= $studentId ?>]" value="1" <?= $isPresent === 1 ? 'checked' : '' ?> <?= ($group['status'] ?? '') === 'finalizado' ? 'disabled' : '' ?>>
                        <span>✔ Presente</span>
                      </label>

                      <label class="attendance-option absent">
                        <input type="radio" name="attendance[<?= $studentId ?>]" value="0" <?= $isPresent === 0 ? 'checked' : '' ?> <?= ($group['status'] ?? '') === 'finalizado' ? 'disabled' : '' ?>>
                        <span>✘ Ausente</span>
                      </label>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="empty">No hay estudiantes asignados a este grupo.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="sheet-footer">
        <div class="sign">
          Firma del docente
        </div>

        <div class="actions no-print">
          <button class="save-btn" type="submit" <?= ($group['status'] ?? '') === 'finalizado' ? 'disabled' : '' ?>>Guardar asistencia</button>
          <button class="toolbar-btn print" type="button" onclick="window.print()">Imprimir / Exportar PDF</button>
          <a class="toolbar-btn back" href="groups.php">← Volver</a>
        </div>
      </div>
    </form>
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
