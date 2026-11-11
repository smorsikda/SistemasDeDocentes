<?php
require_once __DIR__ . '/../../backend/helpers/csrf.php';

$me = $_SESSION['user'] ?? null;
$current = basename($_SERVER['PHP_SELF'] ?? '');

function isActive($file, $current) {
    return $file === $current ? 'active' : '';
}

function sidebarIcon(string $name): string {
    $icons = [
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'groups' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'attendance' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16M8 14l2 2 4-4"/>',
        'grades' => '<path d="M4 19V5M4 19h17"/><rect x="7" y="12" width="3" height="5" rx=".5"/><rect x="13" y="8" width="3" height="9" rx=".5"/><rect x="19" y="4" width="2" height="13" rx=".5"/>',
        'teachers' => '<circle cx="12" cy="8" r="4"/><path d="M5 21v-1a7 7 0 0 1 14 0v1M19 8l1.5 1.5L23 7"/>',
        'history' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v4h10V4M8 14h8M8 17h5"/>',
        'password' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3"/>',
        'audit' => '<path d="M8 3h8l4 4v14H4V3z"/><path d="M8 3v5h8V3M8 13h8M8 17h5"/>',
        'import' => '<path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/>',
        'help' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 5.5v14A2.5 2.5 0 0 1 6.5 17H20M9 7h7M9 11h7"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
    ];

    return '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<style>
  .layout{
    min-height:100vh;
    display:flex;
    width:100%;
    min-width:0;
  }

  .sidebar{
    width:260px;
    background:linear-gradient(180deg, #0F1F17, #0B1811);
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

  .sidebar.collapsed .sidebar-top{
    flex-direction:column;
    justify-content:center;
    gap:10px;
  }

  .sidebar.collapsed .brand{
    justify-content:center;
    width:100%;
  }

  .sidebar.collapsed .toggle-btn{
    width:38px;
    height:38px;
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
    color:var(--text);
  }

  .brand-sub{
    font-size:12px;
    color:var(--muted);
    margin-top:2px;
  }

  .toggle-btn{
    border:1px solid var(--line);
    background:rgba(0,0,0,.24);
    color:var(--text);
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
    color:var(--text);
    background:transparent;
    transition:.18s ease;
  }

  .side-link:hover{
    background:rgba(255,255,255,.035);
    border-color:var(--line);
  }

  .side-link.active{
    background:rgba(107,127,103,.16);
    border-color:rgba(107,127,103,.42);
    color:var(--accent);
  }

  .nav-icon{
    width:22px;
    text-align:center;
    display:grid;
    place-items:center;
    color:var(--muted);
    flex-shrink:0;
  }

  .icon-svg{
    display:block;
    width:19px;
    height:19px;
  }

  .nav-label{
    font-weight:700;
    font-size:14px;
  }

  .sidebar.collapsed .side-link{
    justify-content:center;
    gap:0;
    padding-left:8px;
    padding-right:8px;
  }

  .sidebar.collapsed .nav-icon{
    width:22px;
    margin:0;
  }

  .user-box{
    margin-top:18px;
    padding:12px;
    border-radius:14px;
    background:rgba(0,0,0,.24);
    border:1px solid var(--line);
  }

  .user-name{
    font-weight:800;
    color:var(--text);
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

  .sidebar-backdrop{
    display:none;
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
    .layout{
      display:block;
      min-height:100vh;
      overflow-x:clip;
    }

    .sidebar{
      position:fixed;
      left:0;
      top:0;
      bottom:0;
      z-index:100;
      transform:translateX(-105%);
      width:min(280px, calc(100vw - 48px));
      height:100vh;
      height:100dvh;
      max-height:100vh;
      box-shadow:0 18px 40px rgba(0,0,0,.35);
      overscroll-behavior:contain;
    }

    .sidebar.open{
      transform:translateX(0);
    }

    .sidebar.collapsed{
      width:min(280px, calc(100vw - 48px));
    }

    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .section-title,
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .user-box{
      display:block;
    }

    .mobile-topbar{
      display:flex;
      position:sticky;
      top:0;
      z-index:20;
    }

    .sidebar-backdrop{
      position:fixed;
      inset:0;
      z-index:99;
      width:100%;
      height:100%;
      padding:0;
      border:0;
      background:rgba(0,0,0,.58);
      backdrop-filter:blur(2px);
      cursor:pointer;
    }

    .sidebar.open + .sidebar-backdrop{
      display:block;
    }

    .content-area{
      width:100%;
      min-width:0;
    }

    .content-area > main{
      min-width:0;
      width:100%;
    }
  }

  @media (max-width: 640px){
    .mobile-topbar{
      padding:10px 12px;
    }

    .content-area > main.container{
      max-width:100%;
      padding:16px 12px;
    }

    .content-area .panel{
      padding:14px;
    }
  }
</style>

<div class="layout">
  <aside class="sidebar" id="appSidebar">
    <div class="sidebar-top">
      <div class="brand">
        <img src="/docentes/frontend/images/1.png" alt="CONATRADEC">
        <div class="brand-text">
          <div class="brand-title">CONATRADEC</div>
          <div class="brand-sub">Sistema académico</div>
        </div>
      </div>
      <button class="toggle-btn" type="button" onclick="toggleSidebar()" aria-label="Abrir o cerrar menú"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>

    <div class="sidebar-section">
      <p class="section-title">Navegación</p>
      <nav class="side-nav">
        <a class="side-link <?= isActive('dashboard.php', $current) ?>" href="dashboard.php">
          <span class="nav-icon"><?= sidebarIcon('dashboard') ?></span>
          <span class="nav-label">Dashboard</span>
        </a>

        <a class="side-link <?= isActive('students.php', $current) || isActive('student_form.php', $current) || isActive('student_profile.php', $current) || isActive('student_status.php', $current) || isActive('student_letter.php', $current) ?>" href="students.php">
          <span class="nav-icon"><?= sidebarIcon('students') ?></span>
          <span class="nav-label">Estudiantes</span>
        </a>

        <a class="side-link <?= isActive('groups.php', $current) || isActive('group_new.php', $current) || isActive('group_students.php', $current) || isActive('group_profile.php', $current) || isActive('group_report.php', $current) ?>" href="groups.php">
          <span class="nav-icon"><?= sidebarIcon('groups') ?></span>
          <span class="nav-label">Grupos</span>
        </a>

        <a class="side-link <?= isActive('attendance.php', $current) || isActive('attendance_history.php', $current) || isActive('attendance_view.php', $current) ?>" href="attendance_history.php">
          <span class="nav-icon"><?= sidebarIcon('attendance') ?></span>
          <span class="nav-label">Asistencias</span>
        </a>

        <a class="side-link <?= isActive('grades.php', $current) ?>" href="grades.php">
          <span class="nav-icon"><?= sidebarIcon('grades') ?></span>
          <span class="nav-label">Notas</span>
        </a>

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <a class="side-link <?= isActive('teachers.php', $current) || isActive('teacher_new.php', $current) || isActive('teacher_reset.php', $current) || isActive('teacher_profile.php', $current) ?>" href="teachers.php">
            <span class="nav-icon"><?= sidebarIcon('teachers') ?></span>
            <span class="nav-label">Docentes</span>
          </a>
          <a class="side-link <?= isActive('user_roles.php', $current) ?>" href="user_roles.php">
            <span class="nav-icon"><?= sidebarIcon('teachers') ?></span>
            <span class="nav-label">Administrar roles</span>
          </a>
        <?php endif; ?>

        <a class="side-link <?= isActive('students_historical.php', $current) ?>" href="students_historical.php">
  <span class="nav-icon"><?= sidebarIcon('history') ?></span>
  <span class="nav-label">Estudiantes históricos</span>
</a>


        <a class="side-link <?= isActive('change_password.php', $current) ?>" href="change_password.php">
          <span class="nav-icon"><?= sidebarIcon('password') ?></span>
          <span class="nav-label">Contraseña</span>
        </a>

        <a class="side-link <?= isActive('activity_logs.php', $current) ?>" href="activity_logs.php">
        <span class="nav-icon"><?= sidebarIcon('audit') ?></span>
        <span class="nav-label">Auditoría</span>
        </a>
        
        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <a class="side-link <?= isActive('students_import.php', $current) ?>" href="students_import.php">
            <span class="nav-icon"><?= sidebarIcon('import') ?></span>
            <span class="nav-label">Importar CSV</span>
          </a>
        <?php endif; ?>

        <a class="side-link <?= isActive('help.php', $current) ?>" href="help.php">
          <span class="nav-icon"><?= sidebarIcon('help') ?></span>
          <span class="nav-label">Guía</span>
        </a>

        <form method="post" action="logout.php" style="margin:0;">
          <?= csrf_input() ?>
          <button class="side-link" type="submit" style="width:100%;border:0;text-align:left;cursor:pointer;font:inherit;color:inherit;">
            <span class="nav-icon"><?= sidebarIcon('logout') ?></span>
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

  <button class="sidebar-backdrop" type="button" onclick="toggleSidebar()" aria-label="Cerrar menú"></button>

  <div class="content-area">
    <div class="mobile-topbar">
      <div class="mobile-brand">
        <img src="/docentes/frontend/images/1.png" alt="CONATRADEC">
        <span>CONATRADEC</span>
      </div>
      <button class="toggle-btn" type="button" onclick="toggleSidebar()" aria-label="Abrir o cerrar menú"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>
