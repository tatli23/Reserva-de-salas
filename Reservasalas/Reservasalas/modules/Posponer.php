<?php
// ============================================================
//  modules/posponer.php — Posponer una reservación
//  Usuarios: queda "pendiente" hasta que admin apruebe.
//  Admin: se aplica directo.
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo = getDB();
$uid = $_SESSION['user_id'];
$rid = (int)($_GET['id'] ?? $_POST['reservacion_id'] ?? 0);

$where = isAdmin() ? 'r.id = ?' : 'r.id = ? AND r.usuario_id = ?';
$args  = isAdmin() ? [$rid] : [$rid, $uid];
$stmt  = $pdo->prepare(
    "SELECT r.*, s.codigo, s.nombre AS sala_nombre, s.capacidad, s.equipamiento
     FROM reservaciones r JOIN salas s ON s.id = r.sala_id
     WHERE $where AND r.estado NOT IN ('cancelada','completada','rechazada')"
);
$stmt->execute($args);
$reserv = $stmt->fetch();

if (!$reserv) {
    flash('Reservación no encontrada o sin permiso.', 'danger');
    header('Location: ' . BASE_URL . '/modules/historial.php');
    exit;
}

$nueva_fecha    = $_POST['nueva_fecha']    ?? '';
$nueva_hi       = $_POST['nueva_hi']       ?? $reserv['hora_inicio'];
$nueva_hf       = $_POST['nueva_hf']       ?? $reserv['hora_fin'];
$motivo         = $_POST['motivo']         ?? '';
$errores        = [];
$disponibilidad = [];

$fechaVer = $nueva_fecha ?: date('Y-m-d', strtotime($reserv['fecha'] . ' +1 day'));

$stmtD = $pdo->prepare(
    'SELECT r2.hora_inicio, r2.hora_fin, r2.estado, u.nombre AS uname
     FROM reservaciones r2 JOIN usuarios u ON u.id = r2.usuario_id
     WHERE r2.sala_id = ? AND r2.fecha = ? AND r2.estado NOT IN ("cancelada","rechazada") AND r2.id != ?
     ORDER BY r2.hora_inicio'
);
$stmtD->execute([$reserv['sala_id'], $fechaVer, $rid]);
$reservHoras = $stmtD->fetchAll();

for ($h = 8; $h < 20; $h++) {
    $slot  = sprintf('%02d:00', $h);
    $tipo  = 'libre';
    $label = 'Libre';
    if ($nueva_fecha && $nueva_hi && $nueva_hf && $nueva_hi <= $slot && $nueva_hf > $slot) {
        $tipo = 'propia'; $label = 'Tu nueva reservación';
    }
    foreach ($reservHoras as $rh) {
        if ($rh['hora_inicio'] <= $slot && $rh['hora_fin'] > $slot) {
            $tipo  = ($rh['estado'] === 'pendiente') ? 'pendiente' : 'ocupada';
            $label = ($rh['estado'] === 'pendiente')
                   ? 'Pendiente aprobación'
                   : 'Ocupada — ' . $rh['uname'];
            break;
        }
    }
    $disponibilidad[] = ['hora' => $slot, 'tipo' => $tipo, 'label' => $label];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'posponer') {
    if (!$nueva_fecha)  $errores[] = 'Indica la nueva fecha.';
    if (!$nueva_hi)     $errores[] = 'Indica la nueva hora de inicio.';
    if (!$nueva_hf)     $errores[] = 'Indica la nueva hora de fin.';
    if (!$motivo)       $errores[] = 'Describe el motivo de la posposición.';
    if ($nueva_hf <= $nueva_hi) $errores[] = 'La hora de fin debe ser mayor a la de inicio.';
    if ($nueva_fecha < date('Y-m-d')) $errores[] = 'No puedes posponer a una fecha pasada.';

    if (empty($errores)) {
        $stmtChk = $pdo->prepare(
            'SELECT COUNT(*) FROM reservaciones
             WHERE sala_id=? AND fecha=? AND id != ? AND estado NOT IN ("cancelada","rechazada")
             AND NOT (hora_fin <= ? OR hora_inicio >= ?)'
        );
        $stmtChk->execute([$reserv['sala_id'], $nueva_fecha, $rid, $nueva_hi, $nueva_hf]);
        if ($stmtChk->fetchColumn() > 0) {
            $errores[] = 'La sala ya está ocupada en ese nuevo horario.';
        }
    }

    if (empty($errores)) {
        if (isAdmin()) {
            // Admin: aplica posposición directo
            $pdo->prepare(
                'UPDATE reservaciones SET estado="pospuesta", fecha=?, hora_inicio=?, hora_fin=?,
                 motivo_cancel=?, updated_at=NOW() WHERE id=?'
            )->execute([$nueva_fecha, $nueva_hi, $nueva_hf, $motivo, $rid]);

            // Notifica al dueño de la reservación
            $pdo->prepare(
                'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                 VALUES (?, "posposicion", "Reservación pospuesta por administrador",
                 CONCAT("Tu reservación del ", ?, " fue pospuesta por el administrador al ", ?,
                 " de ", ?, "–", ?, ". Motivo: ", ?), ?)'
            )->execute([$reserv['usuario_id'], $reserv['fecha'], $nueva_fecha, $nueva_hi, $nueva_hf, $motivo, $rid]);

            flash('✅ Reservación pospuesta correctamente.', 'success');
            header('Location: ' . BASE_URL . '/modules/historial.php');

        } else {
            // Usuario: guarda solicitud de posposición como "pendiente"
            // Se almacenan los nuevos valores en campos temporales
            $pdo->prepare(
                'UPDATE reservaciones SET estado="pendiente",
                 fecha_solicitada=?, hora_inicio_solicitada=?, hora_fin_solicitada=?,
                 motivo_cancel=?, updated_at=NOW() WHERE id=?'
            )->execute([$nueva_fecha, $nueva_hi, $nueva_hf, $motivo, $rid]);

            // Notificación al propio usuario
            $pdo->prepare(
                'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                 VALUES (?, "posposicion", "Solicitud de posposición enviada",
                 CONCAT("Tu solicitud para posponer sala ", ?, " del ", ?, " al ", ?,
                 " fue enviada al administrador y está pendiente de aprobación."), ?)'
            )->execute([$uid, $reserv['codigo'], $reserv['fecha'], $nueva_fecha, $rid]);

            // Notificación al admin
            $admRow = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1')->fetch();
            if ($admRow) {
                $u = currentUser();
                $pdo->prepare(
                    'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                     VALUES (?, "posposicion", "Solicitud de posposición",
                     CONCAT(?, " solicita posponer sala ", ?, " del ", ?, " al ", ?,
                     " de ", ?, "–", ?. ". Motivo: ", ?. " Requiere tu aprobación."), ?)'
                )->execute([
                    $admRow['id'], $u['nombre'], $reserv['codigo'],
                    $reserv['fecha'], $nueva_fecha, $nueva_hi, $nueva_hf, $motivo, $rid
                ]);
            }

            flash('📋 Solicitud de posposición enviada. El administrador la revisará y recibirás una notificación.', 'info');
            header('Location: ' . BASE_URL . '/modules/notificaciones.php');
        }
        exit;
    }
}

startLayout('Posponer Reservación', 'historial');
?>

<h1 class="page-title">⏩ Posponer reservación</h1>
<p class="page-sub">
  <?= isAdmin()
      ? 'Como administrador, la posposición se aplica de inmediato.'
      : 'Tu solicitud será enviada al administrador para aprobación.' ?>
</p>

<?php if (!isAdmin()): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  📋 Tu solicitud de posposición quedará <strong>pendiente de aprobación</strong>. Recibirás una notificación cuando el administrador la revise.
</div>
<?php endif; ?>

<?php if (!empty($errores)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errores as $e): echo '<p>• ' . htmlspecialchars($e) . '</p>'; endforeach; ?>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

  <!-- Formulario -->
  <div class="card" style="border-left:4px solid var(--ambar);">
    <div style="background:var(--ambar-claro);border-radius:8px;padding:12px 14px;margin-bottom:18px;">
      <div style="font-size:12px;font-weight:600;color:var(--ambar);margin-bottom:4px;">
        RESERVACIÓN ORIGINAL
      </div>
      <strong><?= htmlspecialchars($reserv['codigo']) ?></strong> ·
      <?= date('d M Y', strtotime($reserv['fecha'])) ?> ·
      <?= substr($reserv['hora_inicio'],0,5) ?> – <?= substr($reserv['hora_fin'],0,5) ?> ·
      <?= htmlspecialchars($reserv['proposito']) ?>
    </div>

    <form method="POST" action="">
      <input type="hidden" name="accion" value="posponer">
      <input type="hidden" name="reservacion_id" value="<?= $rid ?>">

      <div class="form-group">
        <label class="form-label">Nueva fecha</label>
        <input type="date" name="nueva_fecha" class="form-control"
               value="<?= htmlspecialchars($nueva_fecha) ?>"
               min="<?= date('Y-m-d') ?>"
               onchange="actualizarDispP(this.value)">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nueva hora inicio</label>
          <input type="time" name="nueva_hi" class="form-control"
                 value="<?= htmlspecialchars($nueva_hi) ?>" step="1800">
        </div>
        <div class="form-group">
          <label class="form-label">Nueva hora fin</label>
          <input type="time" name="nueva_hf" class="form-control"
                 value="<?= htmlspecialchars($nueva_hf) ?>" step="1800">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Motivo de posposición</label>
        <input type="text" name="motivo" class="form-control"
               placeholder="Ej. Actividad institucional imprevista"
               value="<?= htmlspecialchars($motivo) ?>">
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
        <a href="<?= BASE_URL ?>/modules/historial.php" class="btn btn-ghost">← Regresar</a>
        <button type="submit" class="btn btn-warning">
          <?= isAdmin() ? '⏩ Confirmar posposición' : '📋 Enviar solicitud' ?>
        </button>
      </div>

      <?php if (!isAdmin()): ?>
      <p style="font-size:12px;color:var(--gris-muted);margin-top:12px;">
        🔔 El administrador recibirá notificación con la fecha anterior, la nueva fecha y el motivo.
      </p>
      <?php endif; ?>
    </form>
  </div>

  <!-- Disponibilidad -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px;" id="disp-titulo">
      Disponibilidad — <?= htmlspecialchars($fechaVer) ?> · <?= htmlspecialchars($reserv['codigo']) ?>
    </div>
    <div id="disp-posponer">
      <?php foreach ($disponibilidad as $slot): ?>
      <div class="hora-item hora-<?= $slot['tipo'] ?>">
        <span class="hora-time"><?= $slot['hora'] ?></span>
        <span><?= htmlspecialchars($slot['label']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($nueva_fecha): ?>
    <div style="margin-top:12px;padding:8px;background:var(--ambar-claro);border-radius:8px;font-size:12px;color:var(--ambar);">
      Cambio: <?= $reserv['fecha'] ?> <?= substr($reserv['hora_inicio'],0,5) ?> →
      <?= $nueva_fecha ?> <?= substr($nueva_hi,0,5) ?> · Sala <?= $reserv['codigo'] ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function actualizarDispP(fecha) {
  const salaId = <?= $reserv['sala_id'] ?>;
  fetch('<?= BASE_URL ?>/api/disponibilidad.php?sala_id=' + salaId + '&fecha=' + fecha + '&excluir=<?= $rid ?>')
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('disp-posponer');
      c.innerHTML = data.slots.map(s => `
        <div class="hora-item hora-${s.tipo}">
          <span class="hora-time">${s.hora}</span>
          <span>${s.label}</span>
        </div>`).join('');
      document.getElementById('disp-titulo').textContent =
        'Disponibilidad — ' + fecha + ' · <?= $reserv['codigo'] ?>';
    });
}
</script>

<?php endLayout(); ?>