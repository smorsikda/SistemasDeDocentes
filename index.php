<?php
require __DIR__ . '/../backend/config/db.php';
require __DIR__ . '/../backend/middleware/auth.php';

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount === 0) {
    header("Location: install.php");
    exit;
}

if (isLogged()) {
  // Si ya está logueado, respetar la regla de cambio obligatorio
  if ((int)($_SESSION['user']['must_change_password'] ?? 0) === 1) {
    header("Location: change_password.php"); exit;
  }
  header("Location: dashboard.php"); exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user'] = [
      'id' => (int)$user['id'],
      'name' => $user['name'],
      'role' => $user['role'],
      'must_change_password' => (int)($user['must_change_password'] ?? 0),
    ];

    if ((int)($user['must_change_password'] ?? 0) === 1) {
      header("Location: change_password.php");
      exit;
    }
    header("Location: dashboard.php");
    exit;
  } else {
    $error = "Credenciales incorrectas";
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login | Docentes</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <img src="/docentes/frontend/images/1.png" alt="CONATRADEC">
        <div class="title">
          <h1 style="margin:0;font-size:18px;">CONATRADEC</h1>
          <p style="margin:4px 0 0;color:var(--muted);font-size:13px;">Sistema de Docentes</p>
        </div>
      </div>

      <h2 style="margin:12px 0 6px;">Iniciar sesión</h2>
      <p>Ingresá con tu correo y contraseña.</p>

      <?php if (!empty($error)): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required placeholder="docente@conatradec.local">
        </div>

        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button class="btn" type="submit">Entrar</button>
      </form>

      <div class="footer-links">
        <span class="small">© <?= date('Y') ?> CONATRADEC</span>
        <a class="small" href="logout.php">Limpiar sesión</a>
      </div>
    </div>
  </div>
</body>
</html>