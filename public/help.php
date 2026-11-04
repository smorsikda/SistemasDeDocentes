<?php
require __DIR__ . '/../backend/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Guía rápida</title>
  <link rel="stylesheet" href="/docentes/frontend/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:980px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col6{grid-column:span 12}}
    .step{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,.05);
    }
    .step h3{margin:0 0 6px;font-size:15px}
    .step p{margin:0;color:var(--muted);line-height:1.55}
    .kbd{
      display:inline-block;
      padding:2px 8px;
      border-radius:10px;
      border:1px solid rgba(255,255,255,.18);
      background:rgba(0,0,0,.22);
      color:rgba(255,255,255,.85);
      font-size:12px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .btnS{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800}
    ul{margin:10px 0 0 18px;color:var(--muted)}
    li{margin:6px 0}
  </style>
</head>
<body>
  <?php require __DIR__ . '/../frontend/partials/sidebar.php'; ?>
    </header>

    <main class="container">
      <section class="panel">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 6px;">Guía rápida de uso</h2>
            <p style="margin:0;color:var(--muted);">
              Usuario: <b><?= h($me['name']) ?></b> • Rol: <b><?= h($me['role']) ?></b>
            </p>
          </div>
          <a class="btnS" href="students.php">Ir a Estudiantes</a>
        </div>

        <div class="grid" style="margin-top:14px;">
          <div class="step col6">
            <h3>1) Agregar estudiante</h3>
            <p>
              Entrá a <span class="kbd">Estudiantes</span> y presioná <span class="kbd">+ Agregar estudiante</span>.
              Completá curso (Barismo/Catación), nivel, datos personales y guardá.
            </p>
          </div>

          <div class="step col6">
            <h3>2) Nota y estado automático</h3>
            <p>
              La nota final es numérica (0–100). Si la dejás vacía, queda <b>Pendiente</b>.
              Si la nota es <b>&ge; 60</b> → <b>Aprobado</b>. Si es <b>&lt; 60</b> → <b>Desaprobado</b>.
            </p>
          </div>

          <div class="step col6">
            <h3>3) Editar datos</h3>
            <p>
              En la tabla de estudiantes, presioná <span class="kbd">Editar</span> para corregir información,
              añadir observaciones o colocar la nota.
            </p>
          </div>

          <div class="step col6">
            <h3>4) Descargar CSV</h3>
            <p>
              En <span class="kbd">Estudiantes</span>, usá <span class="kbd">Descargar CSV</span>.
              Si aplicás filtros (curso, nivel, estado, depto), el CSV se descarga con esos resultados.
            </p>
          </div>

          <div class="step col6">
            <h3>5) Constancia / Carta del estudiante</h3>
            <p>
              En la lista de estudiantes, usarás el botón <span class="kbd">Constancia</span>.
              Se abre un documento imprimible y podés elegir <span class="kbd">Guardar como PDF</span>.
            </p>
          </div>

          <div class="step col6">
            <h3>Soporte</h3>
            <p>
              Si olvidaste la contraseña, solicitá al administrador un <b>restablecimiento</b>.
              Al entrar, el sistema te pedirá cambiarla por seguridad.
            </p>
          </div>

          <div class="step col12">
            <h3>Buenas prácticas</h3>
            <ul>
              <li>Ingresá el <b>departamento</b> correctamente (importante para reportes).</li>
              <li>Usá observaciones para notas internas (por ejemplo: “faltó examen práctico”).</li>
              <li>Antes de exportar, filtrá por curso/nivel para obtener reportes limpios.</li>
            </ul>
          </div>
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
  </div>
</body>
</html>