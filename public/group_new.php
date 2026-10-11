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

function isValidGroupDate(string $date): bool {
  if ($date === '') return true;
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) return false;
  return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
}

$error = '';
$success = '';

$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='teacher' AND is_active=1
    ORDER BY name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
}

function generate_group_code(PDO $pdo, string $courseType, string $courseLevel): string {
  $typeMap = [
    'barismo' => 'BAR',
    'catacion' => 'CAT'
  ];

  $levelMap = [
    'basico' => 'BAS',
    'avanzado' => 'AVA',
    'intensivo' => 'INT'
  ];

  $prefix = $typeMap[$courseType] . '-' . $levelMap[$courseLevel] . '-' . date('Y');

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM groups_table WHERE group_code LIKE ?");
  $stmt->execute([$prefix . '-%']);
  $count = (int)$stmt->fetchColumn() + 1;

  return $prefix . '-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();

  $teacher_id = (($me['role'] ?? '') === 'admin')
    ? (int)($_POST['teacher_id'] ?? 0)
    : (int)$me['id'];

  $name = trim($_POST['name'] ?? '');
  $course_type = trim($_POST['course_type'] ?? '');
  $course_level = trim($_POST['course_level'] ?? '');
  $start_date = trim($_POST['start_date'] ?? '');
  $end_date = trim($_POST['end_date'] ?? '');
  $capacity = trim($_POST['capacity'] ?? '');
  $schedule = trim($_POST['schedule'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  $capacityIsValid = $capacity === ''
    || (preg_match('/^\d+$/', $capacity) && (float)$capacity <= 2147483647 && (int)$capacity > 0);
  $teacherStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'teacher' AND is_active = 1");
  $teacherStmt->execute([$teacher_id]);
  $teacherIsActive = (int)$teacherStmt->fetchColumn() === 1;

  if ($name === '') {
    $error = 'El nombre del grupo es obligatorio.';
  } elseif (mb_strlen($name, 'UTF-8') > 200) {
    $error = 'El nombre del grupo no puede superar 200 caracteres.';
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = 'Curso inválido.';
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = 'Nivel inválido.';
  } elseif ($teacher_id <= 0 || !$teacherIsActive) {
    $error = 'Seleccioná un docente activo.';
  } elseif (!$capacityIsValid) {
    $error = 'El cupo debe ser un entero entre 1 y 2147483647.';
  } elseif (!isValidGroupDate($start_date) || !isValidGroupDate($end_date)) {
    $error = 'Ingresá fechas de calendario válidas.';
  } elseif ($start_date !== '' && $end_date !== '' && $end_date < $start_date) {
    $error = 'La fecha fin no puede ser menor que la fecha inicio.';
  } else {
    $group_code = generate_group_code($pdo, $course_type, $course_level);

    $stmt = $pdo->prepare("
      INSERT INTO groups_table
      (
        teacher_id,
        group_code,
        name,
        capacity,
        schedule,
        location,
        course_type,
        course_level,
        start_date,
        end_date,
        status,
        notes
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?)
    ");

    $stmt->execute([
      $teacher_id,
      $group_code,
      $name,
      ($capacity === '' ? null : (int)$capacity),
      ($schedule === '' ? null : $schedule),
      ($location === '' ? null : $location),
      $course_type,
      $course_level,
      ($start_date === '' ? null : $start_date),
      ($end_date === '' ? null : $end_date),
      ($notes === '' ? null : $notes)
    ]);

    $newGroupId = (int)$pdo->lastInsertId();

    log_activity(
      $pdo,
      (int)$_SESSION['user']['id'],
      'group_created',
      "Se creó el grupo {$group_code} ({$name}) con ID {$newGroupId}"
    );

    header("Location: groups.php");
    exit;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear grupo</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:900px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col6{grid-column:span 12}}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <h2 style="margin:0 0 8px;">Nuevo grupo</h2>
    <p style="margin:0;color:var(--muted);">El código se genera automáticamente al guardar.</p>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:14px;">
      <?= csrf_input(); ?>

      <div class="grid">

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <div class="field col12">
            <label>Docente</label>
            <select name="teacher_id" required>
              <option value="">Seleccionar docente</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)($_POST['teacher_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field col12">
          <label>Nombre del grupo</label>
          <input name="name" required placeholder="Ej: Grupo A Marzo 2026" value="<?= h($_POST['name'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Curso</label>
          <select name="course_type" required>
            <option value="">Seleccionar</option>
            <option value="barismo" <?= ($_POST['course_type'] ?? '') === 'barismo' ? 'selected' : '' ?>>Barismo</option>
            <option value="catacion" <?= ($_POST['course_type'] ?? '') === 'catacion' ? 'selected' : '' ?>>Catación</option>
          </select>
        </div>

        <div class="field col6">
          <label>Nivel</label>
          <select name="course_level" required>
            <option value="">Seleccionar</option>
            <option value="basico" <?= ($_POST['course_level'] ?? '') === 'basico' ? 'selected' : '' ?>>Básico</option>
            <option value="avanzado" <?= ($_POST['course_level'] ?? '') === 'avanzado' ? 'selected' : '' ?>>Avanzado</option>
            <option value="intensivo" <?= ($_POST['course_level'] ?? '') === 'intensivo' ? 'selected' : '' ?>>Intensivo</option>
          </select>
        </div>

        <div class="field col6">
          <label>Fecha inicio</label>
          <input type="date" name="start_date" value="<?= h($_POST['start_date'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Fecha fin</label>
          <input type="date" name="end_date" value="<?= h($_POST['end_date'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Cupo</label>
          <input type="number" name="capacity" min="1" placeholder="Ej: 20" value="<?= h($_POST['capacity'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Horario</label>
          <input name="schedule" placeholder="Ej: Lunes y miércoles 2:00 PM" value="<?= h($_POST['schedule'] ?? '') ?>">
        </div>

        <div class="field col12">
          <label>Ubicación</label>
          <input name="location" placeholder="Ej: Aula 2, CONATRADEC Managua" value="<?= h($_POST['location'] ?? '') ?>">
        </div>

        <div class="field col12">
          <label>Notas</label>
          <textarea name="notes" rows="4"><?= h($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
        <button class="btn" type="submit">Crear grupo</button>
        <a class="btnS" href="groups.php">← Volver</a>
      </div>
    </form>
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
</script>
</body>
</html>
