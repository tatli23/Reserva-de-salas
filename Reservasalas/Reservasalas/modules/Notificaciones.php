<?php
// ============================================================
//  modules/notificaciones.php
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo   = getDB();
$uid   = $_SESSION['user_id'];
$admin = isAdmin();

// Marcar todas como leídas al visitar
$pdo->prepare('UPDATE notificaciones SET leida=1 WHERE usuario_id=?')->execute([$uid]);

if ($admin) {
    // Admin: eventos de todos los usuarios
    $stmt = $pdo->prepare(
        "SELECT n.*, u.nombre AS desde_nombre
         FROM notificaciones n
         JOIN usuarios u ON u.id = n.usuario_id
         WHERE n.tipo IN ('nueva_reserva','posposicion','cancelacion','sala_libre')
         ORDER BY n.created_at DESC LIMIT 30"
    );
    $stmt->execute();
} else {
    // Usuario normal: solo las suyas, sin tipos de admin
    $stmt = $pdo->prepare(
        "SELECT * FROM notificaciones
         WHERE usuario_id = ?
           AND tipo NOT IN ('nueva_reserva')
         ORDER BY created_at DESC LIMIT 30"
    );
    $stmt->execute([$uid]);
}
$notifs = $stmt->fetchAll();

function iconoTipo(string $tipo): string {
    return match($tipo) {
        'confirmacion'  => '✉️',
        'cancelacion'   => '✕',
        'posposicion'   => '⏩',
        'recordatorio'  => '⏰',
        'sala_libre'    => '🏫',
        'nueva_reserva' => '✉️',
        default         => '🔔',
    };
}

function tagTipo(string $tipo): string {
    return match($tipo) {
        'confirmacion'  => "<span class='notif-tag tag-confirmacion'>CONFIRMACIÓN</span>",
        'cancelacion'   => "<span class='notif-tag tag-cancelacion'>CANCELACIÓN</span>",
        'posposicion'   => "<span class='notif-tag tag-posposicion'>POSPOSICIÓN</span>",
        'recordatorio'  => "<span class='notif-tag tag-recordatorio'>RECORDATORIO</span>",
        'sala_libre'    => "<span class='notif-tag tag-confirmacion'>SALA LIBRE</span>",
        'nueva_reserva' => "<span class='notif-tag tag-recordatorio'>NUEVA RESERVA</span>",
        default         => '',
    };
}

function tiempoRelativo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Hace un momento';
    if ($diff < 3600)  return 'Hace ' . floor($diff/60) . ' min';
    if ($diff < 86400) return 'Hace ' . floor($diff/3600) . ' h';
    return date('d M', strtotime($dt));
}

startLayout('Notificaciones', 'notificaciones');
?>

<h1 class="page-title"> Notificaciones</h1>

<div class="card">
  <div style="background:<?= $admin ? 'var(--azul-oscuro)' : 'var(--azul-medio)' ?>;
              color:#fff;border-radius:10px 10px 0 0;
              padding:12px 16px;margin:-20px -24px 16px;
              font-weight:600;font-size:14px;
              display:flex;align-items:center;gap:8px;">
    <?= $admin ? '🛡️ Panel de administrador' : ' Mis notificaciones' ?>
  </div>

  <?php if (empty($notifs)): ?>
    <p style="color:var(--gris-muted);font-size:14px;">Sin notificaciones.</p>
  <?php else: ?>
    <?php foreach ($notifs as $n): ?>
    <div class="notif-item">
      <div class="notif-icon ni-<?= $n['tipo'] ?>"><?= iconoTipo($n['tipo']) ?></div>
      <div class="notif-body">
        <div class="notif-title"><?= htmlspecialchars($n['titulo']) ?></div>
        <div class="notif-msg"><?= htmlspecialchars($n['mensaje']) ?></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
          <?= tagTipo($n['tipo']) ?>
          <span class="notif-time"><?= tiempoRelativo($n['created_at']) ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php endLayout(); ?>