<?php
// ============================================================
//  modules/reportes.php — Reportes (Admin)
// ============================================================
require_once '../config/config.php';
requireLogin();
if (!isAdmin()) { flash('Acceso solo para administradores.', 'danger'); header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
require_once '../includes/layout.php';

$pdo = getDB();
$mes = $_GET['mes'] ?? date('Y-m');

// Total por estado
$stmtEst = $pdo->prepare(
    "SELECT estado, COUNT(*) as total FROM reservaciones
     WHERE DATE_FORMAT(fecha, '%Y-%m') = ? GROUP BY estado"
);
$stmtEst->execute([$mes]);
$porEstado = [];
foreach ($stmtEst->fetchAll() as $row) { $porEstado[$row['estado']] = $row['total']; }

// Top salas
$stmtSalas = $pdo->prepare(
    "SELECT s.codigo, COUNT(*) as total FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     WHERE DATE_FORMAT(r.fecha, '%Y-%m') = ? AND r.estado NOT IN ('cancelada')
     GROUP BY s.id ORDER BY total DESC LIMIT 7"
);
$stmtSalas->execute([$mes]);
$topSalas = $stmtSalas->fetchAll();

// Top usuarios
$stmtUsers = $pdo->prepare(
    "SELECT u.nombre, COUNT(*) as total FROM reservaciones r
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE DATE_FORMAT(r.fecha, '%Y-%m') = ? AND r.estado NOT IN ('cancelada')
     GROUP BY u.id ORDER BY total DESC LIMIT 5"
);
$stmtUsers->execute([$mes]);
$topUsers = $stmtUsers->fetchAll();

// Reservaciones del mes completo
$stmtAll = $pdo->prepare(
    "SELECT r.*, s.codigo, u.nombre AS uname FROM reservaciones r
     JOIN salas s ON s.id=r.sala_id JOIN usuarios u ON u.id=r.usuario_id
     WHERE DATE_FORMAT(r.fecha, '%Y-%m') = ?
     ORDER BY r.fecha, r.hora_inicio"
);
$stmtAll->execute([$mes]);
$todas = $stmtAll->fetchAll();

$totalMes = array_sum($porEstado);
$maxSala  = $topSalas[0]['total'] ?? 1;

startLayout('Reportes', 'reportes');
?>

<h1 class="page-title"> Reportes</h1>
<p class="page-sub">Análisis de uso de salas audiovisuales del ITSZN.</p>

<!-- Selector de mes -->
<div style="margin-bottom:20px;">
  <form method="GET" style="display:inline-flex;gap:10px;align-items:center;">
    <label style="font-size:14px;font-weight:500;">Mes:</label>
    <input type="month" name="mes" class="form-control" style="width:160px;"
           value="<?= htmlspecialchars($mes) ?>" onchange="this.form.submit()">
  </form>
</div>

<!-- Stats resumen -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <span class="stat-icon">📊</span>
    <span class="stat-label">Total reservaciones</span>
    <span class="stat-value"><?= $totalMes ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon" style="color:var(--verde);">✅</span>
    <span class="stat-label">Activas / Completadas</span>
    <span class="stat-value"><?= ($porEstado['activa'] ?? 0) + ($porEstado['completada'] ?? 0) ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon" style="color:var(--rojo);">❌</span>
    <span class="stat-label">Canceladas</span>
    <span class="stat-value"><?= $porEstado['cancelada'] ?? 0 ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-icon" style="color:var(--ambar);">⏩</span>
    <span class="stat-label">Pospuestas</span>
    <span class="stat-value"><?= $porEstado['pospuesta'] ?? 0 ?></span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

  <!-- Uso por sala -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px;">🏫 Uso por sala</div>
    <?php foreach ($topSalas as $ts):
      $pct = $maxSala > 0 ? round($ts['total'] / $maxSala * 100) : 0;
    ?>
    <div style="margin-bottom:10px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
        <span style="font-weight:600;"><?= htmlspecialchars($ts['codigo']) ?></span>
        <span style="color:var(--gris-muted);"><?= $ts['total'] ?> reservaciones</span>
      </div>
      <div style="background:var(--gris-borde);border-radius:4px;height:8px;">
        <div style="background:var(--azul-medio);height:8px;border-radius:4px;width:<?= $pct ?>%;transition:width .3s;"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Top usuarios -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px;">👥 Top usuarios</div>
    <?php foreach ($topUsers as $i => $tu): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:8px 0;
                border-bottom:1px solid #f0f2f7;">
      <div style="width:26px;height:26px;border-radius:50%;background:var(--azul-claro);
                  display:flex;align-items:center;justify-content:center;
                  font-size:11px;font-weight:700;color:var(--azul-oscuro);">
        <?= $i + 1 ?>
      </div>
      <div style="flex:1;font-size:13px;"><?= htmlspecialchars($tu['nombre']) ?></div>
      <span class="badge badge-info"><?= $tu['total'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Tabla completa -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📋 Detalle del mes</span>
    <a href="<?= BASE_URL ?>/api/exportar.php?mes=<?= urlencode($mes) ?>"
       class="btn btn-outline btn-sm">⬇ Exportar CSV</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Sala</th><th>Docente</th><th>Fecha</th><th>Horario</th><th>Propósito</th><th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($todas as $i => $r): ?>
        <tr>
          <td style="color:var(--gris-muted);font-size:12px;"><?= $i + 1 ?></td>
          <td><strong><?= htmlspecialchars($r['codigo']) ?></strong></td>
          <td><?= htmlspecialchars($r['uname']) ?></td>
          <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
          <td style="font-family:var(--fuente-mono);font-size:13px;">
            <?= substr($r['hora_inicio'],0,5) ?>–<?= substr($r['hora_fin'],0,5) ?>
          </td>
          <td><?= htmlspecialchars($r['proposito']) ?></td>
          <td><?php
            $cls = match($r['estado']) {
                'activa','completada' => 'badge-success',
                'cancelada' => 'badge-danger',
                'pospuesta' => 'badge-warning',
                default     => 'badge-info',
            };
            echo "<span class='badge $cls'>{$r['estado']}</span>";
          ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endLayout(); ?>