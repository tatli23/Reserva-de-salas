<?php
// ============================================================
//  modules/calendario.php — Calendario interactivo
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo = getDB();
$uid = $_SESSION['user_id'];

// Mes y año actual o seleccionado
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$mesStr = sprintf('%04d-%02d', $year, $month);
$hoyStr = date('Y-m-d');

// Reservaciones del mes
$stmt = $pdo->prepare(
    'SELECT r.fecha, r.hora_inicio, r.hora_fin, r.estado, r.proposito, r.id,
            s.codigo, u.nombre AS uname
     FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE DATE_FORMAT(r.fecha, "%Y-%m") = ?
     ORDER BY r.hora_inicio'
);
$stmt->execute([$mesStr]);
$rawReserv = $stmt->fetchAll();

// Agrupar por fecha
$porFecha = [];
foreach ($rawReserv as $rv) {
    $porFecha[$rv['fecha']][] = $rv;
}

// Próximas del usuario
$stmtProx = $pdo->prepare(
    'SELECT r.*, s.codigo FROM reservaciones r JOIN salas s ON s.id=r.sala_id
     WHERE r.usuario_id = ? AND r.fecha >= CURDATE() AND r.estado IN ("activa","pendiente")
     ORDER BY r.fecha, r.hora_inicio LIMIT 5'
);
$stmtProx->execute([$uid]);
$proximas = $stmtProx->fetchAll();

// Reservación de hoy del usuario
$stmtHoy = $pdo->prepare(
    'SELECT r.*, s.codigo, s.nombre AS sala_nombre FROM reservaciones r JOIN salas s ON s.id=r.sala_id
     WHERE r.usuario_id = ? AND r.fecha = CURDATE() AND r.estado IN ("activa","pendiente")
     ORDER BY r.hora_inicio LIMIT 1'
);
$stmtHoy->execute([$uid]);
$hoyRes = $stmtHoy->fetch();

// Calcular primer día del mes
$primerDia = (int)date('N', mktime(0,0,0,$month,1,$year)); // 1=Lun … 7=Dom
$diasMes   = (int)date('t', mktime(0,0,0,$month,1,$year));
$mesesES   = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$prevM = $month - 1; $prevY = $year;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year;
if ($nextM > 12) { $nextM = 1; $nextY++; }

startLayout('Calendario', 'calendario');
?>

<h1 class="page-title">📅 Calendario</h1>
<p class="page-sub">Visualiza disponibilidad de salas por mes. Haz clic en un día para reservar.</p>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;">

  <!-- Calendario -->
  <div class="card" style="padding:16px;">
    <!-- Cabecera navegación -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h2 style="font-size:17px;font-weight:700;color:var(--azul-oscuro);">
        ◀ <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>"
              style="color:inherit;text-decoration:none;">prev</a>
        &nbsp;&nbsp;<?= $mesesES[$month] ?> <?= $year ?>&nbsp;&nbsp;
        <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>"
           style="color:inherit;text-decoration:none;">sig</a> ▶
      </h2>
      <div style="display:flex;gap:6px;">
        <span style="font-size:12px;display:flex;align-items:center;gap:4px;">
          <span style="width:10px;height:10px;border-radius:3px;background:var(--verde-claro);display:inline-block;"></span>
          Disponible
        </span>
        <span style="font-size:12px;display:flex;align-items:center;gap:4px;">
          <span style="width:10px;height:10px;border-radius:3px;background:var(--rojo-claro);display:inline-block;"></span>
          Ocupada
        </span>
        <span style="font-size:12px;display:flex;align-items:center;gap:4px;">
          <span style="width:10px;height:10px;border-radius:3px;background:var(--azul-claro);display:inline-block;"></span>
          Hoy
        </span>
      </div>
    </div>

    <!-- Grid calendario -->
    <div class="cal-grid">
      <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?>
        <div class="cal-day-header"><?= $d ?></div>
      <?php endforeach; ?>

      <?php
      // Celdas vacías antes del día 1
      for ($i = 1; $i < $primerDia; $i++):
      ?>
        <div class="cal-cell otro-mes"></div>
      <?php endfor; ?>

      <?php for ($dia = 1; $dia <= $diasMes; $dia++):
        $fechaCell = sprintf('%04d-%02d-%02d', $year, $month, $dia);
        $esHoy     = ($fechaCell === $hoyStr);
        $eventos   = $porFecha[$fechaCell] ?? [];
        $clsCell   = $esHoy ? 'cal-cell hoy' : 'cal-cell';
      ?>
        <div class="<?= $clsCell ?>"
             onclick="irAReservar('<?= $fechaCell ?>')"
             title="Clic para reservar <?= $fechaCell ?>">
          <div class="cal-num"><?= $dia ?><?= $esHoy ? ' ●' : '' ?></div>
          <?php foreach (array_slice($eventos, 0, 2) as $ev):
            $cls = $esHoy ? 'cal-evento ev-hoy' :
                   ($ev['estado'] === 'cancelada' ? 'cal-evento ev-ocupada' : 'cal-evento ev-disponible');
          ?>
            <div class="<?= $cls ?>" title="<?= htmlspecialchars($ev['proposito']) ?>">
              <?= htmlspecialchars($ev['codigo']) ?> <?= substr($ev['hora_inicio'],0,5) ?>
            </div>
          <?php endforeach; ?>
          <?php if (count($eventos) > 2): ?>
            <div style="font-size:10px;color:var(--gris-muted);margin-top:2px;">+<?= count($eventos)-2 ?> más</div>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Panel lateral -->
  <div style="display:flex;flex-direction:column;gap:14px;">

    <!-- Hoy -->
    <div class="card" style="background:var(--azul-oscuro);border-color:var(--azul-oscuro);">
      <div style="color:rgba(255,255,255,.7);font-size:12px;font-weight:600;margin-bottom:8px;">
        HOY — <?= date('d \d\e F', strtotime($hoyStr)) ?>
      </div>
      <?php if ($hoyRes): ?>
        <div style="color:#fff;font-weight:700;font-size:15px;"><?= htmlspecialchars($hoyRes['codigo']) ?></div>
        <div style="color:rgba(255,255,255,.8);font-size:13px;margin:4px 0;">
          <?= substr($hoyRes['hora_inicio'],0,5) ?> – <?= substr($hoyRes['hora_fin'],0,5) ?>
        </div>
        <div style="color:rgba(255,255,255,.65);font-size:12px;margin-bottom:10px;">
          <?= htmlspecialchars($hoyRes['proposito']) ?>
        </div>
        <span class="badge badge-success">Activa</span>
      <?php else: ?>
        <p style="color:rgba(255,255,255,.6);font-size:13px;">Sin reservaciones hoy.</p>
        <a href="<?= BASE_URL ?>/modules/nueva_reservacion.php" class="btn btn-outline"
           style="margin-top:8px;color:#fff;border-color:rgba(255,255,255,.4);">
          + Reservar
        </a>
      <?php endif; ?>
    </div>

    <!-- Próximas -->
    <div class="card">
      <div class="card-title" style="margin-bottom:12px;">📌 Próximas</div>
      <?php if (empty($proximas)): ?>
        <p style="font-size:13px;color:var(--gris-muted);">Sin reservaciones próximas.</p>
      <?php else: ?>
        <?php foreach ($proximas as $p): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:8px 0;border-bottom:1px solid #f0f2f7;">
          <div>
            <strong style="font-size:13px;"><?= htmlspecialchars($p['codigo']) ?></strong>
            <div style="font-size:12px;color:var(--gris-muted);">
              <?= date('d M', strtotime($p['fecha'])) ?>
            </div>
          </div>
          <span style="font-family:var(--fuente-mono);font-size:12px;color:var(--azul-medio);">
            <?= substr($p['hora_inicio'],0,5) ?>
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function irAReservar(fecha) {
  window.location.href = '<?= BASE_URL ?>/modules/nueva_reservacion.php?fecha=' + fecha;
}
</script>

<?php endLayout(); ?>