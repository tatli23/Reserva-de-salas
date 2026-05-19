<?php
// ============================================================
//  modules/nueva_reservacion.php
//  → Las reservaciones quedan en estado "pendiente" hasta
//    que el administrador las aprueba.
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo = getDB();
$uid = $_SESSION['user_id'];

// Cargar salas activas
$salas = $pdo->query('SELECT * FROM salas WHERE activa = 1 ORDER BY codigo')->fetchAll();

// Valores
$sala_id     = (int)($_POST['sala_id']     ?? $_GET['sala_id']     ?? ($salas[0]['id'] ?? 0));
$fecha       = $_POST['fecha']             ?? $_GET['fecha']        ?? date('Y-m-d');
$hora_inicio = $_POST['hora_inicio']       ?? '09:00';
$hora_fin    = $_POST['hora_fin']          ?? '11:00';
$proposito   = $_POST['proposito']         ?? '';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'confirmar') {

    if (!$sala_id)     $errores[] = 'Selecciona una sala.';
    if (!$fecha)       $errores[] = 'Indica la fecha.';
    if (!$hora_inicio) $errores[] = 'Indica la hora de inicio.';
    if (!$hora_fin)    $errores[] = 'Indica la hora de fin.';
    if (!$proposito)   $errores[] = 'Describe el propósito de la reservación.';
    if ($hora_fin <= $hora_inicio) $errores[] = 'La hora de fin debe ser mayor a la de inicio.';
    if ($fecha < date('Y-m-d'))    $errores[] = 'No puedes reservar en fechas pasadas.';

    if (empty($errores)) {
        // Verificar conflictos (solo con reservaciones activas/pendientes/pospuestas)
        $stmtChk = $pdo->prepare(
            'SELECT COUNT(*) FROM reservaciones
             WHERE sala_id = ? AND fecha = ? AND estado NOT IN ("cancelada","rechazada")
             AND NOT (hora_fin <= ? OR hora_inicio >= ?)'
        );
        $stmtChk->execute([$sala_id, $fecha, $hora_inicio, $hora_fin]);
        if ($stmtChk->fetchColumn() > 0) {
            $errores[] = 'La sala ya está ocupada o tiene solicitud pendiente en ese horario.';
        }
    }

    if (empty($errores)) {
        // Insertar en estado PENDIENTE (requiere aprobación del admin)
        $ins = $pdo->prepare(
            'INSERT INTO reservaciones (sala_id, usuario_id, fecha, hora_inicio, hora_fin, proposito, estado)
             VALUES (?, ?, ?, ?, ?, ?, "pendiente")'
        );
        $ins->execute([$sala_id, $uid, $fecha, $hora_inicio, $hora_fin, $proposito]);
        $rid = $pdo->lastInsertId();

        // Notificación al usuario: solicitud enviada
        $pdo->prepare(
            'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
             VALUES (?, "confirmacion", "Solicitud enviada al administrador",
             CONCAT("Tu solicitud para sala ", (SELECT codigo FROM salas WHERE id=?),
             " el ", ?, " de ", ?, " a ", ?, " fue enviada. Espera la aprobación."), ?)'
        )->execute([$uid, $sala_id, $fecha, $hora_inicio, $hora_fin, $rid]);

        // Notificación al administrador: nueva solicitud para aprobar
        $stmtAdmin = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1');
        if ($adminRow = $stmtAdmin->fetch()) {
            $user = currentUser();
            $pdo->prepare(
                'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                 VALUES (?, "nueva_reserva", "⏳ Solicitud de reservación — requiere aprobación",
                 CONCAT(?, " solicita sala ", (SELECT codigo FROM salas WHERE id=?),
                 " el ", ?, " de ", ?, " a ", ?, ". Propósito: ", ?), ?)'
            )->execute([
                $adminRow['id'], $user['nombre'], $sala_id,
                $fecha, $hora_inicio, $hora_fin, $proposito, $rid
            ]);
        }

        flash('✅ Solicitud enviada correctamente. Espera la aprobación del administrador.', 'info');
        header('Location: ' . BASE_URL . '/modules/mis_solicitudes.php');
        exit;
    }
}

// Disponibilidad
$disponibilidad = [];
if ($sala_id && $fecha) {
    $stmtD = $pdo->prepare(
        'SELECT r.hora_inicio, r.hora_fin, r.estado, u.nombre AS uname
         FROM reservaciones r JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.sala_id = ? AND r.fecha = ? AND r.estado NOT IN ("cancelada","rechazada")
         ORDER BY r.hora_inicio'
    );
    $stmtD->execute([$sala_id, $fecha]);
    $reservHoras = $stmtD->fetchAll();

    for ($h = 8; $h < 20; $h++) {
        $slot  = sprintf('%02d:00', $h);
        $tipo  = 'libre'; $label = 'Libre';
        foreach ($reservHoras as $rh) {
            if ($rh['hora_inicio'] <= $slot && $rh['hora_fin'] > $slot) {
                if ($rh['uname'] === currentUser()['nombre']) {
                    $tipo  = 'propia'; $label = 'Tu solicitud';
                } elseif ($rh['estado'] === 'pospuesta') {
                    $tipo  = 'pospuesta'; $label = 'Pospuesta — ' . $rh['uname'];
                } elseif ($rh['estado'] === 'pendiente') {
                    $tipo  = 'ocupada'; $label = 'Solicitud pendiente';
                } else {
                    $tipo  = 'ocupada'; $label = 'Ocupada — ' . $rh['uname'];
                }
                break;
            }
        }
        $disponibilidad[] = ['hora' => $slot, 'tipo' => $tipo, 'label' => $label];
    }
}

$salaActual = null;
foreach ($salas as $s) { if ($s['id'] == $sala_id) { $salaActual = $s; break; } }

startLayout('Nueva Reservación', 'reservacion');
?>

<h1 class="page-title">Nueva reservación</h1>
<p class="page-sub">
  Tu solicitud será enviada al administrador para aprobación.
  Recibirás una notificación cuando sea aprobada o rechazada.
</p>

<?php if (!empty($errores)): ?>
  <div class="alert alert-danger">
    <ul style="margin:0;padding-left:18px;">
      <?php foreach ($errores as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

  <!-- Formulario -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px;">Datos de la solicitud</div>

    <form method="POST" action="">
      <input type="hidden" name="accion" value="confirmar">

      <div class="form-group">
        <label class="form-label" for="sala_id">Sala</label>
        <select name="sala_id" id="sala_id" class="form-control" onchange="actualizarDisp()">
          <?php foreach ($salas as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id'] == $sala_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" class="form-control"
               value="<?= htmlspecialchars($fecha) ?>"
               min="<?= date('Y-m-d') ?>"
               onchange="actualizarDisp()">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="hora_inicio">Hora inicio</label>
          <input type="time" id="hora_inicio" name="hora_inicio" class="form-control"
                 value="<?= htmlspecialchars($hora_inicio) ?>" step="1800">
        </div>
        <div class="form-group">
          <label class="form-label" for="hora_fin">Hora fin</label>
          <input type="time" id="hora_fin" name="hora_fin" class="form-control"
                 value="<?= htmlspecialchars($hora_fin) ?>" step="1800">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="proposito">Propósito</label>
        <input type="text" id="proposito" name="proposito" class="form-control"
               placeholder="Ej. Clase de Redes de Computadoras"
               value="<?= htmlspecialchars($proposito) ?>">
      </div>

      <!-- Info -->
      <div style="background:#f0f7ff;border:1px solid #c8dff7;border-radius:8px;
                  padding:12px 14px;margin-bottom:16px;font-size:13px;color:#1e4d9b;">
        ℹ️ Al enviar, el administrador recibirá una notificación para <strong>aprobar o rechazar</strong>
        tu solicitud. Se te notificará la decisión.
      </div>

      <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary">📨 Enviar solicitud</button>
        <a href="<?= BASE_URL ?>/modules/mis_solicitudes.php" class="btn btn-ghost">Ver mis solicitudes</a>
      </div>
    </form>
  </div>

  <!-- Disponibilidad -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px;">
      Disponibilidad — <?= $salaActual ? htmlspecialchars($salaActual['codigo']) : '—' ?>
    </div>
    <div id="disp-container">
      <?php foreach ($disponibilidad as $slot): ?>
      <div class="hora-item hora-<?= $slot['tipo'] ?>">
        <span class="hora-time"><?= $slot['hora'] ?></span>
        <span><?= htmlspecialchars($slot['label']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($salaActual): ?>
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gris-borde);
                font-size:12px;color:var(--gris-muted);">
      <?= htmlspecialchars($salaActual['codigo']) ?> ·
      Cap. <?= $salaActual['capacidad'] ?> pers. ·
      <?= implode(' · ', json_decode($salaActual['equipamiento'] ?? '[]', true)) ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function actualizarDisp() {
  const sala  = document.getElementById('sala_id').value;
  const fecha = document.getElementById('fecha').value;
  if (!sala || !fecha) return;
  fetch('<?= BASE_URL ?>/api/disponibilidad.php?sala_id=' + sala + '&fecha=' + fecha)
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('disp-container');
      c.innerHTML = data.slots.map(s => `
        <div class="hora-item hora-${s.tipo}">
          <span class="hora-time">${s.hora}</span>
          <span>${s.label}</span>
        </div>`).join('');
    });
}
</script>

<?php endLayout(); ?>