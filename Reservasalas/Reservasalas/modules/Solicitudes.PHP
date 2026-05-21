<?php
// ============================================================
//  modules/solicitudes.php — Gestión de solicitudes (Admin)
// ============================================================
require_once '../config/config.php';
requireLogin();
if (!isAdmin()) {
    flash('Acceso solo para administradores.', 'danger');
    header('Location: ' . BASE_URL . '/modules/nueva_reservacion.php');
    exit;
}
require_once '../includes/layout.php';

$pdo = getDB();

// ── Acción POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $rid    = (int)($_POST['reservacion_id'] ?? 0);
    $motivo = trim($_POST['motivo_rechazo'] ?? '');

    // Cargar la reservación
    $stmt = $pdo->prepare(
        'SELECT r.*, s.codigo, s.nombre AS sala_nombre, u.nombre AS uname, u.id AS uid
         FROM reservaciones r
         JOIN salas s ON s.id = r.sala_id
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.id = ? AND r.estado = "pendiente"'
    );
    $stmt->execute([$rid]);
    $reserv = $stmt->fetch();

    if ($reserv) {

        // ── APROBAR ──────────────────────────────────────────
        if ($accion === 'aprobar') {

            // Verificar conflicto con activas (no con otras pendientes)
            $stmtChk = $pdo->prepare(
                'SELECT COUNT(*) FROM reservaciones
                 WHERE sala_id=? AND fecha=? AND id != ? AND estado IN ("activa","pospuesta")
                 AND NOT (hora_fin <= ? OR hora_inicio >= ?)'
            );
            $stmtChk->execute([
                $reserv['sala_id'], $reserv['fecha'], $rid,
                $reserv['hora_inicio'], $reserv['hora_fin']
            ]);

            if ($stmtChk->fetchColumn() > 0) {
                flash('⚠️ No se puede aprobar: el horario ya fue tomado por otra reservación activa.', 'danger');
            } else {
                // Aprobar esta
                $pdo->prepare(
                    'UPDATE reservaciones SET estado="activa", updated_at=NOW() WHERE id=?'
                )->execute([$rid]);

                // Notificar al usuario: aprobada
                $pdo->prepare(
                    'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                     VALUES (?, "confirmacion", "✅ Reservación aprobada",
                     CONCAT("Tu solicitud para sala ", ?, " el ", ?, " de ",
                     ?, "–", ?, " fue APROBADA."), ?)'
                )->execute([
                    $reserv['uid'], $reserv['codigo'], $reserv['fecha'],
                    substr($reserv['hora_inicio'],0,5), substr($reserv['hora_fin'],0,5), $rid
                ]);

                // Buscar otras solicitudes pendientes en conflicto y rechazarlas automáticamente
                $stmtConflicto = $pdo->prepare(
                    'SELECT r.*, u.nombre AS uname, u.id AS uid, s.codigo
                     FROM reservaciones r
                     JOIN usuarios u ON u.id = r.usuario_id
                     JOIN salas s ON s.id = r.sala_id
                     WHERE r.sala_id=? AND r.fecha=? AND r.id != ? AND r.estado="pendiente"
                     AND NOT (r.hora_fin <= ? OR r.hora_inicio >= ?)'
                );
                $stmtConflicto->execute([
                    $reserv['sala_id'], $reserv['fecha'], $rid,
                    $reserv['hora_inicio'], $reserv['hora_fin']
                ]);
                $conflictos = $stmtConflicto->fetchAll();

                foreach ($conflictos as $conf) {
                    // Rechazar automáticamente
                    $pdo->prepare(
                        'UPDATE reservaciones SET estado="cancelada",
                         motivo_cancel="La sala fue asignada a otro solicitante para ese horario.",
                         updated_at=NOW() WHERE id=?'
                    )->execute([$conf['id']]);

                    // Notificar con opción de elegir nueva fecha
                    $pdo->prepare(
                        'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                         VALUES (?, "cancelacion", "❌ Solicitud no asignada",
                         CONCAT("Tu solicitud para sala ", ?, " el ", ?, " de ", ?, "–", ?,
                         " no pudo ser asignada porque el horario fue otorgado a otro docente. ",
                         "Puedes hacer una nueva solicitud con otra fecha u hora."), ?)'
                    )->execute([
                        $conf['uid'], $conf['codigo'], $conf['fecha'],
                        substr($conf['hora_inicio'],0,5), substr($conf['hora_fin'],0,5), $conf['id']
                    ]);
                }

                $rechazados = count($conflictos);
                flash('✅ Reservación aprobada.' . ($rechazados > 0 ? " $rechazados solicitud(es) en conflicto notificadas." : ''), 'success');
            }

        // ── RECHAZAR ────────────────────────────────────────
        } elseif ($accion === 'rechazar') {
            $motivoFinal = $motivo ?: 'Rechazada por el administrador';

            $pdo->prepare(
                'UPDATE reservaciones SET estado="cancelada", motivo_cancel=?, updated_at=NOW() WHERE id=?'
            )->execute([$motivoFinal, $rid]);

            // Notificar al usuario con invitación a solicitar otra fecha/hora
            $pdo->prepare(
                'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
                 VALUES (?, "cancelacion", "❌ Solicitud rechazada",
                 CONCAT("Tu solicitud para sala ", ?, " el ", ?, " fue rechazada. Motivo: ", ?,
                 ". Puedes hacer una nueva solicitud con otra fecha u hora disponible."), ?)'
            )->execute([
                $reserv['uid'], $reserv['codigo'], $reserv['fecha'], $motivoFinal, $rid
            ]);

            flash('Solicitud rechazada. El usuario fue notificado y puede solicitar otra fecha.', 'warning');
        }
    }

    header('Location: ' . BASE_URL . '/modules/solicitudes.php');
    exit;
}

// ── Cargar solicitudes pendientes agrupadas por sala/fecha/hora ──
$stmt = $pdo->query(
    "SELECT r.*, s.codigo, s.nombre AS sala_nombre, u.nombre AS uname, u.correo,
            (SELECT COUNT(*) FROM reservaciones r2
             WHERE r2.sala_id=r.sala_id AND r2.fecha=r.fecha AND r2.id != r.id
               AND r2.estado='pendiente'
               AND NOT (r2.hora_fin <= r.hora_inicio OR r2.hora_inicio >= r.hora_fin)
            ) AS en_disputa
     FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE r.estado = 'pendiente'
     ORDER BY r.fecha ASC, r.hora_inicio ASC, r.created_at ASC"
);
$pendientes = $stmt->fetchAll();

// Historial reciente
$stmtH = $pdo->query(
    "SELECT r.*, s.codigo, u.nombre AS uname
     FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     JOIN usuarios u ON u.id = r.usuario_id
     WHERE r.estado IN ('activa','cancelada')
       AND r.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY r.updated_at DESC LIMIT 10"
);
$recientes = $stmtH->fetchAll();

startLayout('Solicitudes', 'solicitudes');
?>

<h1 class="page-title">Solicitudes pendientes</h1>

<?php if (empty($pendientes)): ?>
  <div class="card" style="padding:40px;text-align:center;">
    <p style="color:var(--gris-muted);font-size:15px;">No hay solicitudes pendientes por revisar.</p>
  </div>

<?php else: ?>

  <div style="margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="background:#fff3cd;color:#856404;font-size:13px;font-weight:700;
                 padding:5px 14px;border-radius:20px;border:1px solid #ffc107;">
      <?= count($pendientes) ?> solicitud<?= count($pendientes) !== 1 ? 'es' : '' ?> pendiente<?= count($pendientes) !== 1 ? 's' : '' ?>
    </span>
    <?php $enDisputa = count(array_filter($pendientes, fn($r) => $r['en_disputa'] > 0)); ?>
    <?php if ($enDisputa > 0): ?>
    <span style="background:#fdecea;color:#c0392b;font-size:13px;font-weight:700;
                 padding:5px 14px;border-radius:20px;border:1px solid #e74c3c;">
      ⚡ <?= $enDisputa ?> en disputa (mismo horario)
    </span>
    <?php endif; ?>
  </div>

  <?php foreach ($pendientes as $r): ?>
  <div class="card" style="border-left:4px solid <?= $r['en_disputa'] > 0 ? '#e74c3c' : 'var(--ambar)' ?>;margin-bottom:16px;">

    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--azul-oscuro);">
          <?= htmlspecialchars($r['codigo']) ?> — <?= htmlspecialchars($r['sala_nombre']) ?>
        </div>
        <div style="font-size:13px;color:var(--gris-muted);margin-top:3px;">
          Solicitado por <strong><?= htmlspecialchars($r['uname']) ?></strong>
          · <?= htmlspecialchars($r['correo']) ?>
          · Enviado <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php if ($r['en_disputa'] > 0): ?>
        <span style="background:#fdecea;color:#c0392b;font-size:11px;font-weight:700;
                     padding:3px 10px;border-radius:10px;border:1px solid #e74c3c;">
          ⚡ <?= $r['en_disputa'] + 1 ?> solicitudes para este horario
        </span>
        <?php endif; ?>
        <span class="badge badge-info">Pendiente</span>
      </div>
    </div>

    <!-- Detalles -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;
                background:#f8f9fc;border-radius:8px;padding:14px;margin-bottom:16px;">
      <div>
        <div style="font-size:11px;font-weight:600;color:var(--gris-muted);margin-bottom:3px;">SALA</div>
        <div style="font-size:14px;font-weight:700;"><?= htmlspecialchars($r['codigo']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;font-weight:600;color:var(--gris-muted);margin-bottom:3px;">FECHA</div>
        <div style="font-size:14px;font-weight:700;"><?= date('d \d\e F \d\e Y', strtotime($r['fecha'])) ?></div>
      </div>
      <div>
        <div style="font-size:11px;font-weight:600;color:var(--gris-muted);margin-bottom:3px;">HORARIO</div>
        <div style="font-size:14px;font-weight:700;font-family:var(--fuente-mono);">
          <?= substr($r['hora_inicio'],0,5) ?> – <?= substr($r['hora_fin'],0,5) ?>
        </div>
      </div>
      <div>
        <div style="font-size:11px;font-weight:600;color:var(--gris-muted);margin-bottom:3px;">PROPÓSITO</div>
        <div style="font-size:14px;"><?= htmlspecialchars($r['proposito']) ?></div>
      </div>
      <?php if ($r['motivo_cancel']): ?>
      <div>
        <div style="font-size:11px;font-weight:600;color:var(--ambar);margin-bottom:3px;">MOTIVO POSPOSICIÓN</div>
        <div style="font-size:13px;"><?= htmlspecialchars($r['motivo_cancel']) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($r['en_disputa'] > 0): ?>
    <div style="background:#fff8e1;border:1px solid #ffc107;border-radius:8px;
                padding:10px 14px;margin-bottom:14px;font-size:13px;color:#856404;">
      ⚡ <strong>Hay <?= $r['en_disputa'] ?> solicitud<?= $r['en_disputa'] !== 1 ? 'es' : '' ?> más</strong>
      para este mismo horario. Al aprobar esta, las demás serán rechazadas automáticamente
      y sus solicitantes serán notificados para elegir otra fecha u hora.
    </div>
    <?php endif; ?>

    <!-- Acciones -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">

      <!-- Aprobar -->
      <form method="POST" style="display:inline;">
        <input type="hidden" name="accion" value="aprobar">
        <input type="hidden" name="reservacion_id" value="<?= $r['id'] ?>">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('¿Aprobar la solicitud de <?= htmlspecialchars(addslashes($r['uname'])) ?>?<?= $r['en_disputa'] > 0 ? " Las otras " . $r['en_disputa'] . " solicitud(es) del mismo horario serán rechazadas." : "" ?>')">
          ✅ Aprobar
        </button>
      </form>

      <!-- Rechazar con motivo -->
      <form method="POST" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center;min-width:260px;">
        <input type="hidden" name="accion" value="rechazar">
        <input type="hidden" name="reservacion_id" value="<?= $r['id'] ?>">
        <input type="text" name="motivo_rechazo" class="form-control"
               placeholder="Motivo del rechazo (el usuario podrá solicitar otra fecha)"
               style="flex:1;min-width:220px;max-width:380px;">
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('¿Rechazar esta solicitud? El usuario será notificado y podrá elegir otra fecha.')">
          ❌ Rechazar
        </button>
      </form>

    </div>
  </div>
  <?php endforeach; ?>

<?php endif; ?>

<!-- Historial reciente -->
<?php if (!empty($recientes)): ?>
<div class="card" style="margin-top:28px;">
  <div class="card-title" style="margin-bottom:12px;"> Procesadas en los últimos 7 días</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Sala</th><th>Docente</th><th>Fecha</th><th>Horario</th><th>Resultado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recientes as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['codigo']) ?></strong></td>
          <td><?= htmlspecialchars($r['uname']) ?></td>
          <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
          <td style="font-family:var(--fuente-mono);font-size:13px;">
            <?= substr($r['hora_inicio'],0,5) ?>–<?= substr($r['hora_fin'],0,5) ?>
          </td>
          <td>
            <?php if ($r['estado'] === 'activa'): ?>
              <span class="badge badge-success">✅ Aprobada</span>
            <?php else: ?>
              <span class="badge badge-danger">❌ Rechazada</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endLayout(); ?>