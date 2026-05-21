<?php
// api/cancelar.php
require_once '../config/config.php';
requireLogin();

$pdo = getDB();
$uid = $_SESSION['user_id'];
$rid = (int)($_GET['id'] ?? 0);

$where = isAdmin() ? 'id = ?' : 'id = ? AND usuario_id = ?';
$args  = isAdmin() ? [$rid] : [$rid, $uid];

$stmt = $pdo->prepare("SELECT * FROM reservaciones WHERE $where AND estado NOT IN ('cancelada','completada')");
$stmt->execute($args);
$r = $stmt->fetch();

if (!$r) {
    $_SESSION['flash'] = ['msg' => 'Reservación no encontrada o ya cancelada.', 'tipo' => 'danger'];
    header('Location: ' . BASE_URL . '/modules/mis_reservas.php');
    exit;
}

// Marcar como cancelada solo si aún está pendiente/activa (evita doble ejecución)
$upd = $pdo->prepare("UPDATE reservaciones SET estado='cancelada', updated_at=NOW() WHERE id=? AND estado NOT IN ('cancelada','completada')");
$upd->execute([$rid]);
if ($upd->rowCount() === 0) {
    // Ya fue cancelada antes (doble clic), redirigir sin duplicar notificación
    $_SESSION['flash'] = ['msg' => 'La reservación ya había sido cancelada.', 'tipo' => 'warning'];
    header('Location: ' . BASE_URL . '/modules/mis_reservas.php');
    exit;
}

// Notificación usuario
$pdo->prepare(
    "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
     VALUES (?, 'cancelacion', 'Reservación cancelada',
     CONCAT('Tu reservación en sala ', (SELECT codigo FROM salas WHERE id=?),
     ' fue cancelada.'), ?)"
)->execute([$uid, $r['sala_id'], $rid]);

// Notificación admin
$admRow = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1')->fetch();
if ($admRow && $admRow['id'] !== $uid) {
    $u = currentUser();
    $pdo->prepare(
        "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
         VALUES (?, 'cancelacion', 'Reservación cancelada',
         CONCAT(?, ' canceló sala ', (SELECT codigo FROM salas WHERE id=?),
         ' del ', ?), ?)"
    )->execute([$admRow['id'], $u['nombre'], $r['sala_id'], $r['fecha'], $rid]);
}

$_SESSION['flash'] = ['msg' => 'Reservación cancelada.', 'tipo' => 'warning'];
header('Location: ' . BASE_URL . '/modules/mis_reservas.php');
exit;