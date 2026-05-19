<?php
// ============================================================
//  dashboard.php
// ============================================================
require_once 'config/config.php';
requireLogin();
require_once 'includes/layout.php';

$pdo    = getDB();
$uid    = $_SESSION['user_id'];
$admin  = isAdmin();

// ── Estadísticas ──────────────────────────────────────────────
if ($admin) {
    $stmt = $pdo->query('SELECT COUNT(*) FROM reservaciones WHERE estado IN ("activa","pendiente")');
    $activas = $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM reservaciones WHERE DATE(fecha) = CURDATE()');
    $hoy = $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM salas WHERE activa = 1');
    $salas = $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM reservaciones WHERE MONTH(fecha) = MONTH(CURDATE())');
    $mes  = $stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservaciones WHERE usuario_id=? AND estado IN ("activa","pendiente")');
    $stmt->execute([$uid]); $activas = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservaciones WHERE usuario_id=? AND DATE(fecha) = CURDATE()');
    $stmt->execute([$uid]); $hoy = $stmt->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(*) FROM salas WHERE activa = 1');
    $salas = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservaciones WHERE usuario_id=? AND MONTH(fecha) = MONTH(CURDATE())');
    $stmt->execute([$uid]); $mes = $stmt->fetchColumn();
}

// ── Próximas reservaciones ────────────────────────────────────
$base = $admin
    ? 'SELECT r.*, s.codigo, s.nombre AS sala_nombre, u.nombre AS usuario_nombre
       FROM reservaciones r
       JOIN salas s ON s.id = r.sala_id
       JOIN usuarios u ON u.id = r.usuario_id
       WHERE r.estado IN ("activa","pendiente") AND r.fecha >= CURDATE()
       ORDER BY r.fecha, r.hora_inicio LIMIT 8'
    : 'SELECT r.*, s.codigo, s.nombre AS sala_nombre, u.nombre AS usuario_nombre
       FROM reservaciones r
       JOIN salas s ON s.id = r.sala_id
       JOIN usuarios u ON u.id = r.usuario_id
       WHERE r.usuario_id = ? AND r.estado IN ("activa","pendiente") AND r.fecha >= CURDATE()
       ORDER BY r.fecha, r.hora_inicio LIMIT 8';

$stmt = $pdo->prepare($base);
$admin ? $stmt->execute() : $stmt->execute([$uid]);
$proximas = $stmt->fetchAll();

// ── Estado badge ──────────────────────────────────────────────
function badgeEstado(string $e): string {
    return match($e) {
        'activa'     => "<span class='badge badge-success'>Activa</span>",
        'pendiente'  => "<span class='badge badge-info'>Pendiente</span>",
        'completada' => "<span class='badge badge-muted'>Completada</span>",
        'cancelada'  => "<span class='badge badge-danger'>Cancelada</span>",
        'pospuesta'  => "<span class='badge badge-warning'>Pospuesta</span>",
        default      => "<span class='badge badge-muted'>$e</span>",
    };
}

startLayout('Dashboard', 'dashboard');
?>

<h1 class="page-title">Dashboard</h1>
<p class="page-sub">
  Bienvenido, <?= htmlspecialchars(currentUser()['nombre']) ?> ·
  <?= date('d \d\e F \d\e Y') ?>
</p>

<!-- Estadísticas -->
<div class="stats-grid">
  <div class="stat-card">
    <span class="stat-icon">📅</span>
    <span class="stat-label"><?= $admin ? 'Reservaciones activas' : 'Mis reservaciones activas' ?></span>
    <span class="stat-value"><?= $activas ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon">🗓️</span>
    <span class="stat-label">Reservaciones hoy</span>
    <span class="stat-value"><?= $hoy ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon">🏫</span>
    <span class="stat-label">Salas disponibles</span>
    <span class="stat-value"><?= $salas ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon">📈</span>
    <span class="stat-label">Este mes</span>
    <span class="stat-value"><?= $mes ?></span>
  </div>
</div>

<!-- Acciones rápidas -->
<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
  <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php" class="btn btn-primary">
    ➕ Nueva reservación
  </a>
  <a href="<?= BASE_URL ?>/modules/calendario.php" class="btn btn-outline">
    📅 Ver calendario
  </a>
  <a href="<?= BASE_URL ?>/modules/historial.php" class="btn btn-ghost">
    📋 Mi historial
  </a>
</div>

<!-- Próximas reservaciones -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📌 Próximas reservaciones</span>
    <a href="<?= BASE_URL ?>/modules/historial.php" class="btn btn-ghost btn-sm">Ver todo</a>
  </div>
  <?php if (empty($proximas)): ?>
    <p style="color:var(--gris-muted);font-size:14px;padding:12px 0;">
      No hay reservaciones próximas.
      <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php">Crear una</a>
    </p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Sala</th>
          <?php if ($admin): ?><th>Docente</th><?php endif; ?>
          <th>Fecha</th>
          <th>Horario</th>
          <th>Propósito</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($proximas as $r): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($r['codigo']) ?></strong><br>
            <span style="font-size:12px;color:var(--gris-muted);"><?= htmlspecialchars($r['sala_nombre']) ?></span>
          </td>
          <?php if ($admin): ?>
          <td><?= htmlspecialchars($r['usuario_nombre']) ?></td>
          <?php endif; ?>
          <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
          <td style="font-family:var(--fuente-mono);font-size:13px;">
            <?= substr($r['hora_inicio'],0,5) ?> – <?= substr($r['hora_fin'],0,5) ?>
          </td>
          <td><?= htmlspecialchars($r['proposito']) ?></td>
          <td><?= badgeEstado($r['estado']) ?></td>
          <td style="white-space:nowrap;">
            <a href="<?= BASE_URL ?>/modules/posponer.php?id=<?= $r['id'] ?>"
               class="btn btn-warning btn-sm" title="Posponer">⏩</a>
            <a href="<?= BASE_URL ?>/api/cancelar.php?id=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('¿Cancelar esta reservación?')"
               title="Cancelar">✕</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php endLayout(); ?>