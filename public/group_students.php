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

function courseLabel($t){
    return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
    if ($l === 'avanzado') return 'Avanzado';
    if ($l === 'intensivo') return 'Intensivo';
    return 'Básico';
}

function availabilityLabel(array $s): string {
    $hasBarismo = (int)($s['has_barismo_active'] ?? 0) === 1;
    $hasCatacion = (int)($s['has_catacion_active'] ?? 0) === 1;

    if ($hasBarismo && $hasCatacion) return 'Barismo y Catación activos';
    if ($hasBarismo) return 'Barismo activo';
    if ($hasCatacion) return 'Catación activa';
    return 'Disponible';
}

function availabilityClass(array $s, string $currentCourseType): string {
    $hasBarismo = (int)($s['has_barismo_active'] ?? 0) === 1;
    $hasCatacion = (int)($s['has_catacion_active'] ?? 0) === 1;

    if ($hasBarismo && $hasCatacion) {
        return 'status-d';
    }

    if ($currentCourseType === 'barismo' && $hasBarismo) {
        return 'status-d';
    }

    if ($currentCourseType === 'catacion' && $hasCatacion) {
        return 'status-d';
    }

    if ($hasBarismo || $hasCatacion) {
        return 'status-p';
    }

    return 'status-a';
}

// Acepta id o group_id
$groupId = (int)($_GET['id'] ?? $_GET['group_id'] ?? $_POST['group_id'] ?? $_POST['id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    exit('Grupo inválido');
}

// Traer grupo
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
    exit('Grupo no encontrado');
}

// Permisos: docente solo sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit('Acceso denegado');
}

$isFinalized = (($group['status'] ?? '') === 'finalizado');
$error = '';

// Búsqueda
$q = trim($_GET['q'] ?? '');

// Sacar año del grupo desde group_code, ejemplo BAR-BAS-2026-001
preg_match('/(20\d{2})/', (string)$group['group_code'], $groupYearMatch);
$groupYear = $groupYearMatch[1] ?? '';

// =====================================================
// Construir filtro de estudiantes elegibles
// =====================================================
$where = [];
$params = [];

if ($groupYear !== '') {
    $where[] = "s.student_code LIKE ?";
    $params[] = 'EST-' . $groupYear . '-%';
}

if (($me['role'] ?? '') === 'teacher') {
    $where[] = "s.teacher_id = ?";
    $params[] = (int)$me['id'];
}

if ($q !== '') {
    $where[] = "(s.full_name LIKE ? OR s.student_code LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// =====================================================
// Guardar asignaciones
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    if ($isFinalized) {
        exit('Este grupo ya fue finalizado y no admite cambios.');
    }

    $studentIdsRaw = $_POST['student_ids'] ?? [];

    if (!is_array($studentIdsRaw)) {
        $studentIdsRaw = [];
    }

    $studentIds = [];
    foreach ($studentIdsRaw as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
            $studentIds[] = $sid;
        }
    }

    $studentIds = array_values(array_unique($studentIds));

    try {
        $pdo->beginTransaction();

        // Bloquea el grupo mientras se validan cupos y cambios de matrícula.
        $lockedGroupStmt = $pdo->prepare("SELECT capacity, status FROM groups_table WHERE id = ? FOR UPDATE");
        $lockedGroupStmt->execute([$groupId]);
        $lockedGroup = $lockedGroupStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lockedGroup || $lockedGroup['status'] === 'finalizado') {
            throw new RuntimeException('Este grupo ya no admite cambios.');
        }

        $capacity = $lockedGroup['capacity'] !== null ? (int)$lockedGroup['capacity'] : null;
        if ($capacity !== null && count($studentIds) > $capacity) {
            throw new RuntimeException("El grupo admite como máximo {$capacity} estudiantes.");
        }

        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

            $validationSql = "
                SELECT s.id
                FROM students s
                $sqlWhere
                " . ($sqlWhere ? "AND" : "WHERE") . " s.id IN ($placeholders)
            ";

            $validationParams = array_merge($params, $studentIds);

            $valStmt = $pdo->prepare($validationSql);
            $valStmt->execute($validationParams);
            $validIds = $valStmt->fetchAll(PDO::FETCH_COLUMN);
            $validIds = array_map('intval', $validIds);
            sort($validIds);

            $submittedIds = $studentIds;
            sort($submittedIds);

            if ($validIds !== $submittedIds) {
                throw new RuntimeException('Se intentó inscribir uno o más estudiantes no permitidos para este grupo.');
            }

            // Serializa asignaciones concurrentes del mismo estudiante a grupos activos.
            $studentLockStmt = $pdo->prepare("SELECT id FROM students WHERE id IN ($placeholders) ORDER BY id FOR UPDATE");
            $studentLockStmt->execute($studentIds);
            $studentLockStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

            $activeConflictSql = "
                SELECT
                    s.full_name,
                    s.student_code,
                    g2.name AS conflict_group_name,
                    g2.group_code AS conflict_group_code
                FROM group_students gs2
                JOIN groups_table g2
                    ON g2.id = gs2.group_id
                JOIN students s
                    ON s.id = gs2.student_id
                WHERE gs2.student_id IN ($placeholders)
                  AND g2.status = 'activo'
                  AND g2.course_type = ?
                  AND g2.id <> ?
                LIMIT 1
            ";

            $activeConflictParams = array_merge($studentIds, [
                $group['course_type'],
                $groupId
            ]);

            $conflictStmt = $pdo->prepare($activeConflictSql);
            $conflictStmt->execute($activeConflictParams);
            $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                throw new RuntimeException(
                    'El estudiante ' . ($conflict['full_name'] ?: 'seleccionado') .
                    ' (' . ($conflict['student_code'] ?: 'sin código') . ')' .
                    ' ya pertenece a otro grupo activo de ' . courseLabel($group['course_type']) .
                    ': ' . trim(($conflict['conflict_group_code'] ?: '') . ' ' . ($conflict['conflict_group_name'] ?: '')) . '.'
                );
            }
        }

        $existingStmt = $pdo->prepare("SELECT student_id FROM group_students WHERE group_id = ? FOR UPDATE");
        $existingStmt->execute([$groupId]);
        $existingIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
        $removedIds = array_values(array_diff($existingIds, $studentIds));

        if ($removedIds) {
            $removedPlaceholders = implode(',', array_fill(0, count($removedIds), '?'));
            $historySql = "
                SELECT gs.student_id
                FROM group_students gs
                WHERE gs.group_id = ?
                  AND gs.student_id IN ($removedPlaceholders)
                  AND (
                    EXISTS (
                      SELECT 1 FROM student_grades sg
                      WHERE sg.group_id = gs.group_id AND sg.student_id = gs.student_id
                    )
                    OR EXISTS (
                      SELECT 1
                      FROM attendance_items ai
                      JOIN attendances a ON a.id = ai.attendance_id
                      WHERE a.group_id = gs.group_id AND ai.student_id = gs.student_id
                    )
                  )
                LIMIT 1
            ";
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute(array_merge([$groupId], $removedIds));
            if ($historyStmt->fetchColumn()) {
                throw new RuntimeException('No se puede quitar a un estudiante que ya tiene notas o asistencias registradas en este grupo.');
            }

            $deleteSql = "DELETE FROM group_students WHERE group_id = ? AND student_id IN ($removedPlaceholders)";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute(array_merge([$groupId], $removedIds));
        }

        if (!empty($studentIds)) {
            $newIds = array_values(array_diff($studentIds, $existingIds));
            $ins = $pdo->prepare("INSERT INTO group_students (group_id, student_id) VALUES (?, ?)");

            foreach ($newIds as $studentId) {
                $ins->execute([$groupId, $studentId]);
            }
        }

        $pdo->commit();

        $assignedCount = count($studentIds);

        log_activity(
            $pdo,
            (int)$_SESSION['user']['id'],
            'group_students_updated',
            "Se actualizaron los estudiantes del grupo {$group['group_code']} ({$group['name']}) con ID {$groupId}. Total inscritos: {$assignedCount}"
        );

        header("Location: group_students.php?id={$groupId}&saved=1");
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (get_class($e) === RuntimeException::class) {
            $error = $e->getMessage();
        } else {
            error_log('Group enrollment update failed: ' . $e->getMessage());
            $error = 'Ocurrió un error al guardar los estudiantes del grupo.';
        }
    }
}

// =====================================================
// Traer estudiantes elegibles
// =====================================================
$sql = "
    SELECT
        s.id,
        s.full_name,
        s.student_code,
        MAX(CASE WHEN g2.course_type = 'barismo' AND g2.status = 'activo' THEN 1 ELSE 0 END) AS has_barismo_active,
        MAX(CASE WHEN g2.course_type = 'catacion' AND g2.status = 'activo' THEN 1 ELSE 0 END) AS has_catacion_active
    FROM students s
    LEFT JOIN group_students gs2
        ON gs2.student_id = s.id
    LEFT JOIN groups_table g2
        ON g2.id = gs2.group_id
    $sqlWhere
    GROUP BY
        s.id,
        s.full_name,
        s.student_code
    ORDER BY s.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traer estudiantes ya asignados
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
$assignedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assignedIds = array_map(function($r){
    return (int)$r['id'];
}, $assignedStudents);

$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asignar estudiantes</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1380px;
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

    .muted{
      color:var(--muted);
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:8px;
    }

    .group-meta{
      margin-top:10px;
      padding:14px;
      border-radius:14px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.04);
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:12px;
    }

    .group-meta .k{
      font-size:12px;
      color:var(--muted);
      margin-bottom:4px;
    }

    .group-meta .v{
      font-weight:800;
      color:#e5e7eb;
    }

    .ok-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(47,191,113,.10);
      border:1px solid rgba(47,191,113,.35);
      color:var(--green);
      font-weight:700;
    }

    .warn-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(245,158,11,.10);
      border:1px solid rgba(245,158,11,.35);
      color:#f59e0b;
      font-weight:700;
    }

    .err-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(239,68,68,.10);
      border:1px solid rgba(239,68,68,.35);
      color:#fecaca;
      font-weight:700;
    }

    .toolbar{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
      margin:14px 0 18px;
    }

    .toolbar input[type="text"]{
      flex:1;
      min-width:260px;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.05);
      color:white;
      outline:none;
    }

    .mini-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      cursor:pointer;
      text-decoration:none;
      line-height:1.2;
      white-space:nowrap;
      min-height:42px;
    }

    .mini-btn.gray{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
    }

    .mini-btn.red{
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
      color:#fca5a5;
    }

    .mini-btn.disabled{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.04);
      color:#94a3b8;
      cursor:not-allowed;
      pointer-events:none;
    }

    .layout-2col{
      display:grid;
      grid-template-columns:minmax(0, 1.45fr) minmax(320px, .85fr);
      gap:16px;
      align-items:start;
      margin-top:12px;
    }

    .box{
      border:1px solid var(--line);
      border-radius:16px;
      background:rgba(255,255,255,.04);
      overflow:hidden;
      min-width:0;
    }

    .box-head{
      padding:14px 16px;
      border-bottom:1px solid var(--line);
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }

    .box-title{
      font-weight:900;
      color:#e5e7eb;
      margin:0;
    }

    .box-sub{
      font-size:12px;
      color:var(--muted);
      margin-top:4px;
    }

    .table-wrap{
      overflow-x:auto;
      width:100%;
    }

    table{
      width:100%;
      border-collapse:collapse;
      min-width:740px;
      table-layout:fixed;
    }

    th,td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      vertical-align:middle;
      font-size:14px;
      overflow-wrap:anywhere;
      word-break:break-word;
    }

    th{
      color:var(--muted);
      background:rgba(255,255,255,.04);
      white-space:nowrap;
    }

    .col-name{width:39%;}
    .col-code{width:23%;}
    .col-status{width:18%;}
    .col-action{width:20%;}

    .code{
      font-weight:800;
      color:#e5e7eb;
      white-space:nowrap;
    }

    .chip{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      line-height:1.2;
      max-width:100%;
      text-align:center;
    }

    .status-p{
      color:#fde68a;
      border-color:rgba(245,158,11,.35);
      background:rgba(245,158,11,.10);
    }

    .status-a{
      color:var(--green);
      border-color:rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
    }

    .status-d{
      color:#fca5a5;
      border-color:rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
    }

    .action-cell{
      text-align:center;
    }

    .action-cell .mini-btn,
    .action-cell .mini-btn.disabled{
      min-width:112px;
      width:100%;
      max-width:130px;
      justify-content:center;
      margin:0 auto;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:16px;
    }

    .empty{
      padding:22px;
      text-align:center;
      color:var(--muted);
      font-weight:700;
    }

    .preview-list{
      padding:12px;
      display:flex;
      flex-direction:column;
      gap:10px;
    }

    .preview-item{
      border:1px solid var(--line);
      border-radius:14px;
      padding:12px;
      background:rgba(255,255,255,.03);
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
    }

    .preview-name{
      font-weight:800;
      color:#e5e7eb;
    }

    .preview-meta{
      font-size:12px;
      color:var(--muted);
      margin-top:4px;
    }

    .save-row{
      padding:14px 16px;
      border-top:1px solid var(--line);
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    .hidden-targets{
      display:none;
    }

    @media (max-width: 1200px){
      .container{
        padding:20px;
      }

      .layout-2col{
        grid-template-columns:1fr;
      }
    }

    @media (max-width: 960px){
      .group-meta{
        grid-template-columns:1fr 1fr;
      }

      .toolbar{
        flex-direction:column;
        align-items:stretch;
      }

      .toolbar input[type="text"]{
        min-width:0;
        width:100%;
      }

      table{
        min-width:700px;
      }

      .col-name{width:38%;}
      .col-code{width:22%;}
      .col-status{width:18%;}
      .col-action{width:22%;}
    }

    @media (max-width: 640px){
      .container{
        padding:14px;
      }

      .panel{
        padding:14px;
      }

      .group-meta{
        grid-template-columns:1fr;
      }

      .box-head{
        padding:12px;
      }

      .save-row{
        padding:12px;
      }

      table{
        min-width:660px;
      }

      th, td{
        padding:10px;
        font-size:13px;
      }

      .col-name{width:36%;}
      .col-code{width:24%;}
      .col-status{width:18%;}
      .col-action{width:22%;}
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 8px;">Asignar estudiantes al grupo</h2>
        <p style="margin:0;color:var(--muted);">
          Inscribí estudiantes al grupo y revisá la previsualización antes de guardar.
        </p>
      </div>
    </div>

    <div class="group-meta">
      <div>
        <div class="k">Grupo</div>
        <div class="v"><?= h($group['name']) ?></div>
      </div>
      <div>
        <div class="k">Código</div>
        <div class="v"><?= h($group['group_code']) ?></div>
      </div>
      <div>
        <div class="k">Curso</div>
        <div class="v"><?= h(courseLabel($group['course_type'])) ?> / <?= h(levelLabel($group['course_level'])) ?></div>
      </div>
      <div>
        <div class="k">Estado</div>
        <div class="v"><?= h($group['status']) ?></div>
      </div>
    </div>

    <?php if ($saved): ?>
      <div class="ok-msg">Estudiantes del grupo guardados correctamente.</div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="err-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($isFinalized): ?>
      <div class="warn-msg">
        Este grupo ya fue finalizado. La información se muestra en modo solo lectura y no admite cambios.
      </div>
    <?php endif; ?>

    <form method="get" class="toolbar">
      <input type="hidden" name="id" value="<?= $groupId ?>">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar estudiante por nombre o código...">
      <button class="mini-btn" type="submit">Buscar</button>
      <a class="mini-btn gray" href="group_students.php?id=<?= $groupId ?>">Limpiar</a>
    </form>

    <form method="post" id="groupStudentsForm">
      <?= csrf_input() ?>
      <input type="hidden" name="group_id" value="<?= $groupId ?>">

      <div class="layout-2col">
        <div class="box">
          <div class="box-head">
            <div>
              <div class="box-title">Estudiantes disponibles</div>
              <div class="box-sub">
                Se muestran estudiantes del mismo año. Un estudiante puede estar en 1 grupo activo de Barismo y 1 de Catación.
              </div>
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th class="col-name">Nombre</th>
                  <th class="col-code">Código</th>
                  <th class="col-status">Disponibilidad</th>
                  <th class="col-action">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($students): ?>
                  <?php foreach ($students as $s): ?>
                    <?php
                      $alreadyAssigned = in_array((int)$s['id'], $assignedIds, true);
                      $availabilityText = availabilityLabel($s);
                      $availabilityCss = availabilityClass($s, (string)$group['course_type']);
                    ?>
                    <tr>
                      <td><?= h($s['full_name']) ?></td>
                      <td class="code"><?= h($s['student_code'] ?: '—') ?></td>
                      <td>
                        <span class="chip <?= h($availabilityCss) ?>">
                          <?= h($availabilityText) ?>
                        </span>
                      </td>
                      <td class="action-cell">
                        <?php if ($isFinalized): ?>
                          <span class="mini-btn disabled">Finalizado</span>
                        <?php else: ?>
                          <button
                            type="button"
                            class="mini-btn js-add-student"
                            data-student-id="<?= (int)$s['id'] ?>"
                            data-student-name="<?= h($s['full_name']) ?>"
                            data-student-code="<?= h($s['student_code'] ?: '—') ?>"
                          >
                            <?= $alreadyAssigned ? 'Inscrito' : 'Inscribir' ?>
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="empty">
                      No se encontraron estudiantes con esa búsqueda.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="box">
          <div class="box-head">
            <div>
              <div class="box-title">Previsualización de inscritos</div>
              <div class="box-sub">Revisá los estudiantes antes de guardar.</div>
            </div>
            <?php if (!$isFinalized): ?>
              <button type="button" class="mini-btn gray" onclick="clearPreview()">Vaciar</button>
            <?php endif; ?>
          </div>

          <div id="previewList" class="preview-list"></div>
          <div id="hiddenTargets" class="hidden-targets"></div>

          <div class="save-row">
            <?php if (!$isFinalized): ?>
              <button class="btn" type="submit">Guardar estudiantes del grupo</button>
            <?php else: ?>
              <span class="mini-btn disabled">Solo lectura</span>
            <?php endif; ?>
            <a class="mini-btn gray" href="groups.php">← Volver</a>
          </div>
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

const previewList = document.getElementById('previewList');
const hiddenTargets = document.getElementById('hiddenTargets');

let selectedStudents = new Map();

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function renderPreview() {
  previewList.innerHTML = '';
  hiddenTargets.innerHTML = '';

  if (selectedStudents.size === 0) {
    previewList.innerHTML = '<div class="empty">Todavía no has inscrito estudiantes en la previsualización.</div>';
    return;
  }

  for (const [id, student] of selectedStudents.entries()) {
    const item = document.createElement('div');
    item.className = 'preview-item';
    item.innerHTML = `
      <div>
        <div class="preview-name">${escapeHtml(student.name)}</div>
        <div class="preview-meta">${escapeHtml(student.code)}</div>
      </div>
      <div>
        <button type="button" class="mini-btn red" onclick="removeStudent(${id})">Quitar</button>
      </div>
    `;
    previewList.appendChild(item);

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'student_ids[]';
    hidden.value = id;
    hiddenTargets.appendChild(hidden);
  }
}

function addStudent(id, name, code) {
  id = parseInt(id, 10);
  if (!id || selectedStudents.has(id)) return;

  selectedStudents.set(id, {
    name,
    code
  });

  renderPreview();
}

function removeStudent(id) {
  id = parseInt(id, 10);
  selectedStudents.delete(id);
  renderPreview();
}

function clearPreview() {
  selectedStudents.clear();
  renderPreview();
}

<?php foreach ($assignedStudents as $a): ?>
selectedStudents.set(<?= (int)$a['id'] ?>, {
  name: <?= json_encode((string)$a['full_name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  code: <?= json_encode((string)($a['student_code'] ?: '—'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
});
<?php endforeach; ?>

document.querySelectorAll('.js-add-student').forEach((button) => {
  button.addEventListener('click', () => {
    addStudent(button.dataset.studentId, button.dataset.studentName, button.dataset.studentCode);
  });
});

renderPreview();

document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('appSidebar');
  if (!sidebar) return;

  if (window.innerWidth > 960) {
    sidebar.classList.remove('open');
  }
});

window.addEventListener('resize', function () {
  const sidebar = document.getElementById('appSidebar');
  if (!sidebar) return;

  if (window.innerWidth > 960) {
    sidebar.classList.remove('open');
  }
});
</script>
</body>
</html>
