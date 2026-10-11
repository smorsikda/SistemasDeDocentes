<?php
require_once __DIR__ . '/../../app/helpers/csrf.php';

$me = $_SESSION['user'] ?? null;
$current = basename($_SERVER['PHP_SELF'] ?? '');

function isActive($file, $current) {
    return $file === $current ? 'active' : '';
}
?>
<style>
  .layout{
    min-height:100vh;
    display:flex;
  }

  .sidebar{
    width:260px;
    background:linear-gradient(180deg, rgba(7,18,14,.96), rgba(8,16,14,.92));
    border-right:1px solid var(--line);
    padding:18px 14px;
    position:sticky;
    top:0;
    height:100vh;
    overflow-y:auto;
    transition:.25s ease;
  }

  .sidebar.collapsed{
    width:84px;
  }

  .sidebar-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:18px;
  }

  .brand{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
  }

  .brand img{
    width:38px;
    height:38px;
    object-fit:contain;
    flex-shrink:0;
  }

  .brand-text{
    min-width:0;
  }

  .brand-title{
    font-weight:900;
    font-size:15px;
    line-height:1.1;
    color:#fff;
  }

  .brand-sub{
    font-size:12px;
    color:var(--muted);
    margin-top:2px;
  }

  .toggle-btn{
    border:1px solid var(--line);
    background:rgba(255,255,255,.05);
    color:#e5e7eb;
    border-radius:12px;
    width:38px;
    height:38px;
    cursor:pointer;
    font-size:18px;
    flex-shrink:0;
  }

  .sidebar.collapsed .brand-text,
  .sidebar.collapsed .section-title,
  .sidebar.collapsed .nav-label,
  .sidebar.collapsed .user-box{
    display:none;
  }

  .sidebar-section{
    margin-top:18px;
  }

  .section-title{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.7px;
    color:var(--muted);
    margin:0 0 10px;
    padding:0 10px;
  }

  .side-nav{
    display:flex;
    flex-direction:column;
    gap:6px;
  }

  .side-link{
    display:flex;
    align-items:center;
    gap:12px;
    padding:11px 12px;
    border-radius:14px;
    border:1px solid transparent;
    text-decoration:none;
    color:#e5e7eb;
    background:transparent;
    transition:.18s ease;
  }

  .side-link:hover{
    background:rgba(255,255,255,.05);
    border-color:var(--line);
  }

  .side-link.active{
    background:rgba(47,191,113,.12);
    border-color:rgba(47,191,113,.35);
    color:var(--green);
  }

  .nav-icon{
    width:22px;
    text-align:center;
    font-size:16px;
    flex-shrink:0;
  }

  .nav-label{
    font-weight:700;
    font-size:14px;
  }

  .user-box{
    margin-top:18px;
    padding:12px;
    border-radius:14px;
    background:rgba(255,255,255,.04);
    border:1px solid var(--line);
  }

  .user-name{
    font-weight:800;
    color:#fff;
    font-size:14px;
  }

  .user-role{
    margin-top:4px;
    color:var(--muted);
    font-size:12px;
  }

  .content-area{
    flex:1;
    min-width:0;
  }

  .mobile-topbar{
    display:none;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 18px;
    border-bottom:1px solid var(--line);
    background:rgba(0,0,0,.35);
    backdrop-filter:blur(8px);
  }

  .mobile-brand{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:800;
  }

  .mobile-brand img{
    width:34px;
    height:34px;
    object-fit:contain;
  }

  @media (max-width: 960px){
    .sidebar{
      position:fixed;
      left:0;
      top:0;
      z-index:50;
      transform:translateX(-100%);
      width:280px;
      box-shadow:0 18px 40px rgba(0,0,0,.35);
    }

    .sidebar.open{
      transform:translateX(0);
    }

    .sidebar.collapsed{
      width:280px;
    }

    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .section-title,
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .user-box{
      display:block;
    }

    .mobile-topbar{
      display:flex;
    }
  }
</style>

<div class="layout">
  <aside class="sidebar" id="appSidebar">
    <div class="sidebar-top">
      <div class="brand">
        <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
        <div class="brand-text">
          <div class="brand-title">CONATRADEC</div>
          <div class="brand-sub">Sistema académico</div>
        </div>
      </div>
      <button class="toggle-btn" type="button" onclick="toggleSidebar()">☰</button>
    </div>

    <div class="sidebar-section">
      <p class="section-title">Navegación</p>
      <nav class="side-nav">
        <a class="side-link <?= isActive('dashboard.php', $current) ?>" href="dashboard.php">
          <span class="nav-icon">🏠</span>
          <span class="nav-label">Dashboard</span>
        </a>

        <a class="side-link <?= isActive('students.php', $current) || isActive('student_form.php', $current) || isActive('student_profile.php', $current) || isActive('student_status.php', $current) || isActive('student_letter.php', $current) ?>" href="students.php">
          <span class="nav-icon">🎓</span>
          <span class="nav-label">Estudiantes</span>
        </a>

        <a class="side-link <?= isActive('groups.php', $current) || isActive('group_new.php', $current) || isActive('group_students.php', $current) || isActive('group_profile.php', $current) || isActive('group_report.php', $current) ?>" href="groups.php">
          <span class="nav-icon">🧩</span>
          <span class="nav-label">Grupos</span>
        </a>

        <a class="side-link <?= isActive('attendance.php', $current) || isActive('attendance_history.php', $current) || isActive('attendance_view.php', $current) ?>" href="attendance_history.php">
          <span class="nav-icon">📝</span>
          <span class="nav-label">Asistencias</span>
        </a>

        <a class="side-link <?= isActive('grades.php', $current) ?>" href="grades.php">
          <span class="nav-icon">📊</span>
          <span class="nav-label">Notas</span>
        </a>

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <a class="side-link <?= isActive('teachers.php', $current) || isActive('teacher_new.php', $current) || isActive('teacher_reset.php', $current) || isActive('teacher_profile.php', $current) ?>" href="teachers.php">
            <span class="nav-icon">👨‍🏫</span>
            <span class="nav-label">Docentes</span>
          </a>
        <?php endif; ?>

        <a class="side-link <?= isActive('students_historical.php', $current) ?>" href="students_historical.php">
  <span class="nav-icon">🗂️</span>
  <span class="nav-label">Estudiantes históricos</span>
</a>


        <a class="side-link <?= isActive('change_password.php', $current) ?>" href="change_password.php">
          <span class="nav-icon">🔒</span>
          <span class="nav-label">Contraseña</span>
        </a>

        <a class="side-link <?= isActive('activity_logs.php', $current) ?>" href="activity_logs.php">
        <span class="nav-icon">📜</span>
        <span class="nav-label">Auditoría</span>
        </a>
        
        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <a class="side-link <?= isActive('students_import.php', $current) ?>" href="students_import.php">
            <span class="nav-icon">📥</span>
            <span class="nav-label">Importar CSV</span>
          </a>
        <?php endif; ?>

        <a class="side-link <?= isActive('help.php', $current) ?>" href="help.php">
          <span class="nav-icon">📘</span>
          <span class="nav-label">Guía</span>
        </a>

        <form method="post" action="logout.php" style="margin:0;">
          <?= csrf_input() ?>
          <button class="side-link" type="submit" style="width:100%;border:0;text-align:left;cursor:pointer;font:inherit;color:inherit;">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Salir</span>
          </button>
        </form>
      </nav>
    </div>

    <div class="user-box">
      <div class="user-name"><?= h($me['name'] ?? 'Usuario') ?></div>
      <div class="user-role">Rol: <?= h($me['role'] ?? '—') ?></div>
    </div>
  </aside>

  <div class="content-area">
    <div class="mobile-topbar">
      <div class="mobile-brand">
        <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
        <span>CONATRADEC</span>
      </div>
      <button class="toggle-btn" type="button" onclick="toggleSidebar()">☰</button>
    </div>
