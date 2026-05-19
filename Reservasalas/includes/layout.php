<?php
// includes/layout.php
// Uso: require 'includes/layout.php';
//      startLayout('Título de página', 'item-activo');
//      ... contenido ...
//      endLayout();

function startLayout(string $title, string $activeMenu = ''): void {
    global $_activeMenu;
    $_activeMenu = $activeMenu;
    $user = currentUser();
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]),
                    array_slice(explode(' ', $user['nombre'] ?? 'U'), 0, 2)));
    // Contar notificaciones no leídas
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
        $stmt->execute([$_SESSION['user_id']]);
        $unread = (int)$stmt->fetchColumn();
    } catch (Exception $e) { $unread = 0; }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> — ITSZN ReservaSalas</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <!-- Iconos Phosphor (outline) -->
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js"></script>
</head>
<body>

<!-- ── Barra superior ─────────────────────────────────────── -->
<header class="topbar">
  <a href="<?= BASE_URL ?>/dashboard.php" class="topbar-logo">
    <svg width="26" height="26" viewBox="0 0 32 32" fill="none">
      <rect width="32" height="32" rx="8" fill="rgba(255,255,255,.15)"/>
      <path d="M8 22V12l8-4 8 4v10l-8 4-8-4z" stroke="#fff" stroke-width="1.8" fill="none"/>
      <path d="M16 8v16M8 12l8 4 8-4" stroke="#fff" stroke-width="1.4"/>
    </svg>
    ReservaSalas <span class="inst">· ITSZN</span>
  </a>
  <div class="topbar-spacer"></div>
  <a href="<?= BASE_URL ?>/modules/notificaciones.php" class="topbar-notif" title="Notificaciones">
    🔔
    <?php if ($unread > 0): ?>
    <span class="notif-badge"><?= $unread > 9 ? '9+' : $unread ?></span>
    <?php endif; ?>
  </a>
  <div class="topbar-user">
    <div class="topbar-avatar"><?= $initials ?></div>
    <span><?= htmlspecialchars($user['nombre'] ?? '') ?></span>
    <a href="<?= BASE_URL ?>/logout.php" style="color:#fff;opacity:.6;font-size:12px;text-decoration:none;margin-left:4px;">(salir)</a>
  </div>
</header>

<!-- ── Layout ─────────────────────────────────────────────── -->
<div class="layout">

  <!-- Sidebar -->
  <nav class="sidebar">
    <a href="<?= BASE_URL ?>/dashboard.php"
       class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
      📊 Dashboard
    </a>
    <a href="<?= BASE_URL ?>/modules/calendario.php"
       class="<?= $activeMenu === 'calendario' ? 'active' : '' ?>">
      📅 Calendario
    </a>
    <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php"
       class="<?= $activeMenu === 'reservacion' ? 'active' : '' ?>">
      ➕ Nueva reservación
    </a>
    <a href="<?= BASE_URL ?>/modules/historial.php"
       class="<?= $activeMenu === 'historial' ? 'active' : '' ?>">
      📋 Historial
    </a>
    <a href="<?= BASE_URL ?>/modules/notificaciones.php"
       class="<?= $activeMenu === 'notificaciones' ? 'active' : '' ?>">
      🔔 Notificaciones
    </a>
    <?php if (isAdmin()): ?>
    <div class="sidebar-sep"></div>
    <div class="sidebar-section">Administración</div>
    <a href="<?= BASE_URL ?>/modules/salas.php"
       class="<?= $activeMenu === 'salas' ? 'active' : '' ?>">
      🏫 Salas
    </a>
    <a href="<?= BASE_URL ?>/modules/reportes.php"
       class="<?= $activeMenu === 'reportes' ? 'active' : '' ?>">
      📈 Reportes
    </a>
    <a href="<?= BASE_URL ?>/modules/usuarios.php"
       class="<?= $activeMenu === 'usuarios' ? 'active' : '' ?>">
      👥 Usuarios
    </a>
    <?php endif; ?>
  </nav>

  <!-- Contenido principal -->
  <main class="main">
    <?php
    // Flash messages
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $tipo = $flash['tipo'] ?? 'info';
        echo "<div class='alert alert-{$tipo}'>{$flash['msg']}</div>";
    }
    ?>
    <?php
}

function endLayout(): void {
    ?>
  </main>
</div><!-- /layout -->

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php
}

// Helper para mensajes flash
function flash(string $msg, string $tipo = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}