<?php
// api/sala_libre.php — Notificar que la sala fue desocupada
require_once '../config/config.php';
requireLogin();

$pdo = getDB();
$uid = $_SESSION['user_id'];
$rid = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT r.*, s.codigo FROM reservaciones r JOIN salas s ON s.id=r.sala_id WHERE r.id=? AND r.usuario_id=?');
$stmt->execute([$rid, $uid]);
$r = $stmt->fetch();

if ($r) {
    // Notificación al admin
    $admRow = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1')->fetch();
    if ($admRow) {
        $u = currentUser();
        $pdo->prepare(
            "INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, reservacion_id)
             VALUES (?, 'sala_libre', 'Sala liberada — desocupada',
             CONCAT(?, ' notificó que sala ', ?, ' ya está desocupada y lista para la siguiente reservación.'), ?)"
        )->execute([$admRow['id'], $u['nombre'], $r['codigo'], $rid]);
    }
    flash('✅ Administrador notificado de que la sala fue liberada.', 'success');
} else {
    flash('No se pudo notificar.', 'danger');
}

header('Location: ' . BASE_URL . '/modules/historial.php');
exit;