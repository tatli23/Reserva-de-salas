<?php
// ============================================================
//  api/disponibilidad.php — Devuelve slots horarios en JSON
// ============================================================
require_once '../config/config.php';
requireLogin();

$pdo     = getDB();
$sala_id = (int)($_GET['sala_id'] ?? 0);
$fecha   = $_GET['fecha'] ?? '';
$excluir = (int)($_GET['excluir'] ?? 0);
$uid     = $_SESSION['user_id'];

if (!$sala_id || !$fecha) {
    jsonResponse(['slots' => []]);
}

$stmtD = $pdo->prepare(
    'SELECT r.hora_inicio, r.hora_fin, r.estado, u.nombre AS uname
     FROM reservaciones r JOIN usuarios u ON u.id = r.usuario_id
     WHERE r.sala_id = ? AND r.fecha = ? AND r.estado NOT IN ("cancelada")
     ' . ($excluir ? 'AND r.id != ' . $excluir : '') . '
     ORDER BY r.hora_inicio'
);
$stmtD->execute([$sala_id, $fecha]);
$reservas = $stmtD->fetchAll();

$slots = [];
$currentUser = currentUser();

for ($h = 8; $h < 20; $h++) {
    $slot = sprintf('%02d:00', $h);
    $tipo = 'libre'; $label = 'Libre';
    foreach ($reservas as $rv) {
        if ($rv['hora_inicio'] <= $slot && $rv['hora_fin'] > $slot) {
            if ($rv['uname'] === $currentUser['nombre']) {
                $tipo = 'propia'; $label = 'Tu reservación';
            } elseif ($rv['estado'] === 'pospuesta') {
                $tipo = 'pospuesta'; $label = 'Pospuesta — ' . $rv['uname'];
            } else {
                $tipo = 'ocupada'; $label = 'Ocupada — ' . $rv['uname'];
            }
            break;
        }
    }
    $slots[] = ['hora' => $slot, 'tipo' => $tipo, 'label' => $label];
}

jsonResponse(['slots' => $slots]);