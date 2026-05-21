<?php
// ============================================================
//  modules/mis_reservas.php — Reservas del usuario
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo = getDB();
$uid = $_SESSION['user_id'];

// Filtro de estado
$fEstado = $_GET['estado'] ?? 'activas';

// Construir WHERE según filtro
$estadoSQL = match($fEstado) {
    'activas'    => 'r.estado IN ("activa","pendiente")',
    'pospuestas' => 'r.estado = "pospuesta"',
    default      => 'r.estado IN ("activa","pendiente")',
};

$stmt = $pdo->prepare(
    "SELECT r.*, s.codigo, s.nombre AS sala_nombre, s.capacidad, s.equipamiento
     FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     WHERE r.usuario_id = ? AND ($estadoSQL)
     ORDER BY r.fecha ASC, r.hora_inicio ASC"
);
$stmt->execute([$uid]);
$reservas = $stmt->fetchAll();

// Contadores para las pestañas
$stmtCounts = $pdo->prepare(
    "SELECT
       SUM(estado IN ('activa','pendiente')) AS activas,
       SUM(estado = 'pospuesta') AS pospuestas
     FROM reservaciones WHERE usuario_id = ?"
);
$stmtCounts->execute([$uid]);
$counts = $stmtCounts->fetch();

startLayout('Mis Reservas', 'mis_reservas');
?>

<h1 class="page-title">Mis reservas</h1>
<p class="page-sub">Consulta y gestiona todas tus reservaciones.</p>

<!-- Pestañas -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--gris-borde);padding-bottom:0;">
  <?php
  $tabs = [
    'activas'    => ['label' => 'Activas y pendientes', 'icon' => '📅', 'count' => $counts['activas'] ?? 0],
    'pospuestas' => ['label' => 'Pospuestas',           'icon' => '⏩', 'count' => $counts['pospuestas'] ?? 0],
  ];
  foreach ($tabs as $key => $tab):
    $active = $fEstado === $key;
  ?>
  <a href="?estado=<?= $key ?>"
     style="display:flex;align-items:center;gap:6px;padding:10px 16px;font-size:13px;font-weight:600;
            text-decoration:none;border-radius:8px 8px 0 0;margin-bottom:-2px;
            <?= $active
              ? 'background:#fff;border:2px solid var(--gris-borde);border-bottom:2px solid #fff;color:var(--azul-oscuro);'
              : 'color:var(--gris-muted);' ?>">
    <?= $tab['icon'] ?> <?= $tab['label'] ?>
    <?php if ($tab['count'] > 0): ?>
      <span style="background:<?= $active ? 'var(--azul-oscuro)' : 'var(--gris-borde)' ?>;
                   color:<?= $active ? '#fff' : 'var(--gris-muted)' ?>;
                   font-size:11px;padding:1px 7px;border-radius:10px;">
        <?= $tab['count'] ?>
      </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Listado -->
<?php if (empty($reservas)): ?>
  <div class="card" style="padding:40px;text-align:center;">
    <div style="font-size:48px;margin-bottom:12px;">
      <?= $fEstado === 'activas' ? '📅' : '⏩' ?>
    </div>
    <p style="color:var(--gris-muted);font-size:14px;margin-bottom:16px;">
      <?= $fEstado === 'activas' ? 'No tienes reservaciones activas ni pendientes.' : 'No hay reservaciones en esta sección.' ?>
    </p>
    <?php if ($fEstado === 'activas'): ?>
      <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php" class="btn btn-primary">
        + Nueva reservación
      </a>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($reservas as $r):
      $esHoy    = ($r['fecha'] === date('Y-m-d'));
      $esFutura = ($r['fecha'] >= date('Y-m-d'));

      $estadoColor = match($r['estado']) {
          'activa'     => '#1a7a4a',
          'pendiente'  => '#1e4d9b',
          'pospuesta'  => '#b7600d',
          'cancelada'  => '#c0392b',
          default      => '#888',
      };
      $estadoLabel = match($r['estado']) {
          'activa'    => 'Activa',
          'pendiente' => 'Pendiente aprobación',
          'pospuesta' => 'Pospuesta',
          'cancelada' => 'Cancelada',
          'completada'=> 'Completada',
          default     => $r['estado'],
      };
      $estadoBadge = match($r['estado']) {
          'activa'    => 'badge-success',
          'pendiente' => 'badge-info',
          'pospuesta' => 'badge-warning',
          'cancelada' => 'badge-danger',
          default     => 'badge-muted',
      };
      $equipamiento = json_decode($r['equipamiento'] ?? '[]', true);
    ?>
    <div class="card" style="border-left:4px solid <?= $estadoColor ?>;padding:0;">

      <!-- Cabecera de la card -->
      <div style="display:flex;justify-content:space-between;align-items:center;
                  padding:14px 20px 10px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <!-- Icono día -->
          <div style="width:46px;height:46px;border-radius:10px;
                      background:<?= $esHoy ? 'var(--azul-oscuro)' : 'var(--azul-claro)' ?>;
                      display:flex;flex-direction:column;align-items:center;justify-content:center;
                      color:<?= $esHoy ? '#fff' : 'var(--azul-oscuro)' ?>;flex-shrink:0;">
            <span style="font-size:9px;font-weight:700;text-transform:uppercase;opacity:.8;">
              <?= strtoupper(date('M', strtotime($r['fecha']))) ?>
            </span>
            <span style="font-size:20px;font-weight:800;line-height:1;">
              <?= date('d', strtotime($r['fecha'])) ?>
            </span>
          </div>
          <div>
            <div style="font-size:16px;font-weight:700;color:var(--azul-oscuro);">
              <?= htmlspecialchars($r['codigo']) ?>
              <?php if ($esHoy): ?>
                <span style="background:#fff3cd;color:#856404;font-size:11px;font-weight:700;
                             padding:2px 8px;border-radius:10px;margin-left:6px;">HOY</span>
              <?php endif; ?>
            </div>
            <div style="font-size:13px;color:var(--gris-muted);margin-top:2px;">
              <?= htmlspecialchars($r['sala_nombre']) ?>
            </div>
          </div>
        </div>
        <span class="badge <?= $estadoBadge ?>"><?= $estadoLabel ?></span>
      </div>

      <!-- Línea divisoria -->
      <div style="height:1px;background:var(--gris-borde);margin:0 20px;"></div>

      <!-- Detalles -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
                  gap:12px;padding:14px 20px;">
        <div>
          <div style="font-size:10px;font-weight:700;color:var(--gris-muted);
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Fecha</div>
          <div style="font-size:13px;font-weight:600;">
            <?= date('d \d\e F \d\e Y', strtotime($r['fecha'])) ?>
          </div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;color:var(--gris-muted);
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Horario</div>
          <div style="font-size:13px;font-weight:600;font-family:var(--fuente-mono);">
            <?= substr($r['hora_inicio'],0,5) ?> – <?= substr($r['hora_fin'],0,5) ?>
          </div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;color:var(--gris-muted);
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Propósito</div>
          <div style="font-size:13px;"><?= htmlspecialchars($r['proposito']) ?></div>
        </div>
        <?php if (!empty($equipamiento)): ?>
        <div>
          <div style="font-size:10px;font-weight:700;color:var(--gris-muted);
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Equipamiento</div>
          <div style="font-size:12px;color:var(--gris-muted);">
            <?= implode(' · ', $equipamiento) ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($r['estado'] === 'pospuesta' && $r['motivo_cancel']): ?>
        <div style="grid-column:1/-1;">
          <div style="font-size:10px;font-weight:700;color:var(--ambar);
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Motivo de posposición</div>
          <div style="font-size:13px;color:var(--ambar);">
            <?= htmlspecialchars($r['motivo_cancel']) ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($r['estado'] === 'cancelada' && $r['motivo_cancel']): ?>
        <div style="grid-column:1/-1;">
          <div style="font-size:10px;font-weight:700;color:#c0392b;
                      text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Motivo de cancelación / rechazo</div>
          <div style="font-size:13px;color:#c0392b;">
            <?= htmlspecialchars($r['motivo_cancel']) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Acciones (solo reservas futuras activas/pendientes/pospuestas) -->
      <?php if ($esFutura && in_array($r['estado'], ['activa','pendiente','pospuesta'])): ?>
      <div style="display:flex;gap:8px;align-items:center;padding:12px 20px;
                  background:#f8f9fc;border-top:1px solid var(--gris-borde);
                  border-radius:0 0 12px 12px;flex-wrap:wrap;">

        <a href="<?= BASE_URL ?>/modules/posponer.php?id=<?= $r['id'] ?>"
           class="btn btn-warning btn-sm">
          ⏩ Posponer
        </a>

        <a href="<?= BASE_URL ?>/api/cancelar.php?id=<?= $r['id'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('¿Cancelar esta reservación? Esta acción no se puede deshacer.')">
          ✕ Cancelar
        </a>

        <?php if ($esHoy && $r['estado'] === 'activa'): ?>
        <a href="<?= BASE_URL ?>/api/sala_libre.php?id=<?= $r['id'] ?>"
           class="btn btn-outline btn-sm"
           onclick="return confirm('¿Notificar que la sala fue desocupada?')">
          🔔 Sala desocupada
        </a>
        <?php endif; ?>

        <?php if ($r['estado'] === 'pendiente'): ?>
        <span style="font-size:12px;color:var(--gris-muted);margin-left:4px;">
          ⏳ Esperando aprobación del administrador
        </span>
        <?php endif; ?>

      </div>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>
  </div>

  <!-- Botón nueva reservación -->
  <?php if ($fEstado === 'activas'): ?>
  <div style="margin-top:20px;text-align:center;">
    <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php" class="btn btn-primary">
      + Nueva reservación
    </a>
  </div>
  <?php endif; ?>

<?php endif; ?>

<?php endLayout(); ?>