<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';
require __DIR__ . '/../backend/helpers/csrf.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$resultMessages = [
  'changed' => ['success', 'El rol se actualizó correctamente.'],
  'unchanged' => ['success', 'La cuenta ya tenía ese rol.'],
  'self' => ['error', 'No puedes cambiar tu propio rol desde esta pantalla.'],
  'last_admin' => ['error', 'No puedes quitar el rol al último administrador activo.'],
  'inactive' => ['error', 'Solo puedes cambiar el rol de cuentas activas.'],
  'not_found' => ['error', 'No se encontró la cuenta seleccionada.'],
  'invalid' => ['error', 'La solicitud no es válida.'],
  'failed' => ['error', 'No se pudo actualizar el rol. Inténtalo de nuevo.'],
];
$result = (string)($_GET['result'] ?? '');
$notice = $resultMessages[$result] ?? null;

$users = $pdo->query(<<<'SQL'
  SELECT id, name, email, role, is_active, created_at
  FROM users
  ORDER BY name ASC, id ASC
SQL)->fetchAll(PDO::FETCH_ASSOC);

function h($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administrar roles</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .container{padding:26px;max-width:1200px;width:100%;margin:0 auto;}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
    .hero{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
    .muted{color:var(--muted);}
    .notice{padding:12px 14px;border-radius:12px;margin:14px 0;border:1px solid var(--line);}
    .notice.success{border-color:rgba(107,127,103,.55);background:rgba(107,127,103,.12);}
    .notice.error{border-color:rgba(255,90,95,.45);background:rgba(255,90,95,.10);}
    .table-wrap{overflow-x:auto;}
    table{width:100%;border-collapse:collapse;min-width:780px;margin-top:12px;}
    th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;font-size:14px;vertical-align:middle;}
    th{color:var(--muted);font-weight:700;background:rgba(255,255,255,.04);}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;}
    .pill.admin{border-color:rgba(107,127,103,.55);color:var(--accent);}
    .pill.teacher{color:var(--muted);}
    .pill.inactive{border-color:rgba(255,90,95,.45);color:rgba(255,255,255,.88);}
    .role-form{display:flex;align-items:center;gap:8px;}
    .role-form select{min-width:145px;}
    .role-form button{white-space:nowrap;}
    .btnS{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:12px;border:1px solid rgba(107,127,103,.35);background:rgba(107,127,103,.10);color:var(--accent);font-weight:800;cursor:pointer;}
    .disabled-note{color:var(--muted);font-size:13px;}
    @media(max-width:640px){.container{padding:16px 10px;}.panel{padding:14px;}}
  </style>
</head>
<body>
<?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;">Administrar roles</h2>
        <p class="muted" style="margin:0;">Asigna a cada cuenta los permisos que necesita. Los cambios quedan registrados en Auditoría.</p>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="notice <?= h($notice[0]) ?>" role="status"><?= h($notice[1]) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Rol actual</th>
            <th>Estado</th>
            <th>Creado</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td><?= h($user['name']) ?></td>
              <td><?= h($user['email']) ?></td>
              <td><span class="pill <?= $user['role'] === 'admin' ? 'admin' : 'teacher' ?>"><?= $user['role'] === 'admin' ? 'Administrador' : 'Docente' ?></span></td>
              <td>
                <?php if ((int)$user['is_active'] === 1): ?>
                  <span class="pill admin">Activo</span>
                <?php else: ?>
                  <span class="pill inactive">Inactivo</span>
                <?php endif; ?>
              </td>
              <td><?= h($user['created_at']) ?></td>
              <td>
                <?php if ((int)$user['is_active'] !== 1): ?>
                  <span class="disabled-note">Activa la cuenta antes de cambiar el rol.</span>
                <?php elseif ((int)$user['id'] === (int)$_SESSION['user']['id']): ?>
                  <span class="disabled-note">Tu propia cuenta está protegida.</span>
                <?php else: ?>
                  <form class="role-form" method="post" action="user_role_update.php" onsubmit="return confirmRoleChange(this)">
                    <?= csrf_input() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <select name="role" aria-label="Nuevo rol para <?= h($user['name']) ?>">
                      <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>Docente</option>
                      <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                    <button class="btnS" type="submit">Guardar rol</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="6" class="muted">No hay cuentas registradas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
function confirmRoleChange(form) {
  const selectedRole = form.elements.role.value === 'admin' ? 'administrador' : 'docente';
  return window.confirm(`¿Confirmas cambiar esta cuenta al rol de ${selectedRole}?`);
}

function toggleSidebar() {
  const sidebar = document.getElementById('appSidebar');
  if (window.innerWidth <= 960) sidebar.classList.toggle('open');
  else sidebar.classList.toggle('collapsed');
}
</script>
</div>
</div>
</body>
</html>
