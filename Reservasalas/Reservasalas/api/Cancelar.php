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
    flash('Reservación no encontrada o ya cancelada.', 'danger');
    header('Location: ' . BASE_URL . '/modules/historial.php');
    exit;
}

$pdo->prepare("UPDATE reservaciones SET estado='cancelada', updated_at=NOW() WHERE id=?")->execute([$rid]);

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

flash('Reservación cancelada.', 'warning');
header('Location: ' . BASE_URL . '/modules/historial.php');
exit;