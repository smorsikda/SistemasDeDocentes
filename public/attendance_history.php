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

function groupStatusClass($status){
    if ($status === 'activo') return 'ok';
    if ($status === 'finalizado') return 'bad';
    if ($status === 'cancelado') return 'pending';
    return 'pending';
}

$where = [];
$params = [];

if (($me['role'] ?? '') === 'teacher') {
    $where[] = "g.teacher_id = ?";
    $params[] = (int)$me['id'];
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$q        = trim($_GET['q'] ?? '');

if ($dateFrom !== '') {
    $where[] = "a.attendance_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "a.attendance_date <= ?";
    $params[] = $dateTo;
}

if ($q !== '') {
    $where[] = "(g.name LIKE ? OR g.group_code LIKE ? OR u.name LIKE ?)";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like);
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
SELECT
    a.id,
    a.attendance_date,
    g.id AS group_id,
    g.group_code,
    g.name AS group_name,
    g.course_type,
    g.course_level,
    g.status AS group_status,
    u.name AS teacher_name,
    (
        SELECT COUNT(*)
        FROM attendance_items ai
        WHERE ai.attendance_id = a.id AND ai.present = 1
    ) AS total_present,
    (
        SELECT COUNT(*)
        FROM attendance_items ai
        WHERE ai.attendance_id = a.id AND ai.present = 0
    ) AS total_absent
FROM attendances a
JOIN groups_table g ON g.id = a.group_id
JOIN users u ON u.id = g.teacher_id
$sqlWhere
ORDER BY a.attendance_date DESC, a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRows = count($rows);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial de asistencias</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1320px;
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

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
    }

    .muted{
      color:var(--muted);
    }

    .stats{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      margin-top:12px;
    }

    .stat{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      color:#C7CBB8;
      font-size:13px;
      font-weight:700;
    }

    .filters{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:end;
      margin-top:16px;
      margin-bottom:6px;
    }

    .filters .field{
      margin-top:0;
      min-width:180px;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:14px;
    }

    table{
      width:100%;
      min-width:1120px;
      border-collapse:collapse;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:14px;
      vertical-align:top;
    }

    th{
      color:var(--muted);
      background:rgba(255,255,255,.04);
      white-space:nowrap;
    }

    tr:hover{
      background:rgba(255,255,255,.04);
    }

    .code{
      font-weight:800;
      color:#C7CBB8;
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(107,127,103,.35);
      background:rgba(107,127,103,.10);
      color:var(--accent);
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(61,67,55,.35);
      background:rgba(255,255,255,.06);
      color:rgba(255,255,255,.92);
      font-weight:700;
      text-decoration:none;
      cursor:pointer;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:72px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      font-weight:800;
      text-transform:capitalize;
    }

    .pill.ok{
      border-color:rgba(107,127,103,.35);
      color:#6B7F67;
      background:rgba(107,127,103,.10);
    }

    .pill.bad{
      border-color:rgba(239,68,68,.35);
      color:#fca5a5;
      background:rgba(239,68,68,.10);
    }

    .pill.pending{
      border-color:rgba(245,158,11,.35);
      color:#f59e0b;
      background:rgba(245,158,11,.10);
    }

    .empty{
      padding:20px;
      text-align:center;
      color:var(--muted);
      font-weight:700;
    }

    @media(max-width:860px){
      .filters .field{
        min-width:100%;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Historial de asistencias</h2>
        <p style="margin:0;color:var(--muted);">
          <?= (($me['role'] ?? '') === 'admin') ? 'Vista global de asistencias registradas' : 'Asistencias registradas en tus grupos' ?>
        </p>

        <div class="stats">
          <div class="stat">Registros encontrados: <?= (int)$totalRows ?></div>
        </div>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="field">
        <label>Buscar</label>
        <input name="q" value="<?= h($q) ?>" placeholder="Grupo, código o docente">
      </div>

      <div class="field">
        <label>Desde</label>
        <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
      </div>

      <div class="field">
        <label>Hasta</label>
        <input type="date" name="date_to" value="<?= h($dateTo) ?>">
      </div>

      <div class="field">
        <button class="btn" type="submit">Filtrar</button>
      </div>

      <div class="field">
        <a class="btnG" href="attendance_history.php">Limpiar</a>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:120px;">Fecha</th>
            <th>Grupo</th>
            <th style="width:150px;">Código</th>
            <th style="width:180px;">Curso / Nivel</th>
            <?php if (($me['role'] ?? '') === 'admin'): ?>
              <th style="width:170px;">Docente</th>
            <?php endif; ?>
            <th style="width:100px;">Presentes</th>
            <th style="width:100px;">Ausentes</th>
            <th style="width:130px;">Estado grupo</th>
            <th style="width:130px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= h($r['attendance_date']) ?></td>

                <td>
                  <div class="code"><?= h($r['group_name']) ?></div>
                </td>

                <td>
                  <span class="code"><?= h($r['group_code']) ?></span>
                </td>

                <td>
                  <?= h(courseLabel($r['course_type'])) ?> / <?= h(levelLabel($r['course_level'])) ?>
                </td>

                <?php if (($me['role'] ?? '') === 'admin'): ?>
                  <td><?= h($r['teacher_name']) ?></td>
                <?php endif; ?>

                <td>
                  <span class="pill ok"><?= (int)$r['total_present'] ?></span>
                </td>

                <td>
                  <span class="pill bad"><?= (int)$r['total_absent'] ?></span>
                </td>

                <td>
                  <span class="pill <?= h(groupStatusClass($r['group_status'] ?? '')) ?>">
                    <?= h($r['group_status']) ?>
                  </span>
                </td>

                <td>
                  <a class="btnS" href="attendance_view.php?id=<?= (int)$r['id'] ?>">Ver detalle</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= (($me['role'] ?? '') === 'admin') ? 9 : 8 ?>" class="empty">
                No hay asistencias registradas.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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
</script>
</body>
</html>