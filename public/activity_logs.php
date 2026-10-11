<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR al.description LIKE ? OR al.ip_address LIKE ?)";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like);
}

if ($action !== '') {
    $where[] = "al.action = ?";
    $params[] = $action;
}

if ($dateFrom !== '') {
    $where[] = "al.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[] = "al.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $dateTo;
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$actionsStmt = $pdo->query("
    SELECT DISTINCT action
    FROM activity_logs
    ORDER BY action ASC
");
$actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

$sql = "
SELECT
    al.id,
    al.user_id,
    al.action,
    al.description,
    al.ip_address,
    al.created_at,
    u.name AS user_name,
    u.email AS user_email
FROM activity_logs al
LEFT JOIN users u ON u.id = al.user_id
$sqlWhere
ORDER BY al.created_at DESC, al.id DESC
LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalLogs = count($rows);

$todayCountStmt = $pdo->query("
    SELECT COUNT(*)
    FROM activity_logs
    WHERE created_at >= CURDATE()
      AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");
$todayCount = (int)$todayCountStmt->fetchColumn();

$loginFailedStmt = $pdo->query("
    SELECT COUNT(*)
    FROM activity_logs
    WHERE action = 'login_failed'
");
$totalFailedLogins = (int)$loginFailedStmt->fetchColumn();

$loginSuccessStmt = $pdo->query("
    SELECT COUNT(*)
    FROM activity_logs
    WHERE action = 'login_success'
");
$totalSuccessLogins = (int)$loginSuccessStmt->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Auditoría del sistema</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1400px;
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

    .summary{
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:12px;
      margin:16px 0 8px;
    }

    .card{
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
    }

    .card .k{
      font-size:12px;
      color:var(--muted);
    }

    .card .v{
      font-size:26px;
      font-weight:900;
      margin-top:6px;
      color:#e5e7eb;
    }

    .filters{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:end;
      margin-top:16px;
      margin-bottom:8px;
    }

    .filters .field{
      margin-top:0;
      min-width:180px;
    }

    .filters .field.grow{
      flex:1;
      min-width:260px;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:14px;
    }

    table{
      width:100%;
      min-width:1220px;
      border-collapse:collapse;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:13px;
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

    .user-name{
      font-weight:800;
      color:#e5e7eb;
    }

    .small{
      font-size:12px;
      color:var(--muted);
      margin-top:4px;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:120px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      font-weight:800;
      background:rgba(255,255,255,.04);
      color:#e5e7eb;
    }

    .pill.success{
      border-color:rgba(34,197,94,.35);
      color:#22c55e;
      background:rgba(34,197,94,.10);
    }

    .pill.danger{
      border-color:rgba(239,68,68,.35);
      color:#fca5a5;
      background:rgba(239,68,68,.10);
    }

    .pill.warning{
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

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    @media(max-width:980px){
      .summary{
        grid-template-columns:1fr 1fr;
      }
    }

    @media(max-width:640px){
      .summary{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Auditoría del sistema</h2>
        <p style="margin:0;color:var(--muted);">
          Registro de acciones importantes realizadas dentro del sistema.
        </p>
      </div>

      <a class="btnG" href="dashboard.php">← Volver al dashboard</a>
    </div>

    <div class="summary">
      <div class="card">
        <div class="k">Registros cargados</div>
        <div class="v"><?= (int)$totalLogs ?></div>
      </div>

      <div class="card">
        <div class="k">Eventos de hoy</div>
        <div class="v"><?= (int)$todayCount ?></div>
      </div>

      <div class="card">
        <div class="k">Logins exitosos</div>
        <div class="v"><?= (int)$totalSuccessLogins ?></div>
      </div>

      <div class="card">
        <div class="k">Logins fallidos</div>
        <div class="v"><?= (int)$totalFailedLogins ?></div>
      </div>
    </div>

    <form method="get" class="filters">
      <div class="field grow">
        <label>Buscar</label>
        <input
          type="text"
          name="q"
          value="<?= h($q) ?>"
          placeholder="Usuario, correo, descripción o IP"
        >
      </div>

      <div class="field">
        <label>Acción</label>
        <select name="action">
          <option value="">Todas</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= h($a) ?>" <?= $action === $a ? 'selected' : '' ?>>
              <?= h($a) ?>
            </option>
          <?php endforeach; ?>
        </select>
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
        <a class="btnG" href="activity_logs.php">Limpiar</a>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:220px;">Usuario</th>
            <th style="width:170px;">Acción</th>
            <th>Descripción</th>
            <th style="width:150px;">IP</th>
            <th style="width:180px;">Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $pillClass = '';
                if (in_array($r['action'], ['login_success', 'student_created', 'teacher_created', 'group_created'], true)) {
                  $pillClass = 'success';
                } elseif (in_array($r['action'], ['login_failed', 'login_blocked'], true)) {
                  $pillClass = 'danger';
                } elseif (in_array($r['action'], ['password_reset', 'password_changed', 'group_finalized', 'grades_updated', 'group_students_updated', 'student_updated'], true)) {
                  $pillClass = 'warning';
                }
              ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>

                <td>
                  <?php if (!empty($r['user_name'])): ?>
                    <div class="user-name"><?= h($r['user_name']) ?></div>
                    <div class="small"><?= h($r['user_email'] ?? '—') ?></div>
                  <?php else: ?>
                    <div class="user-name">Usuario no disponible</div>
                    <div class="small">ID: <?= h($r['user_id'] ?? '—') ?></div>
                  <?php endif; ?>
                </td>

                <td>
                  <span class="pill <?= h($pillClass) ?>">
                    <?= h($r['action']) ?>
                  </span>
                </td>

                <td><?= h($r['description'] ?? '—') ?></td>
                <td><?= h($r['ip_address'] ?? '—') ?></td>
                <td><?= h($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="empty">No se encontraron registros de auditoría.</td>
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
