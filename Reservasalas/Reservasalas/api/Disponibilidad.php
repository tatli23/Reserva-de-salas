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
$admin   = isAdmin();

if (!$sala_id || !$fecha) {
    jsonResponse(['slots' => []]);
}

$stmtD = $pdo->prepare(
    'SELECT r.hora_inicio, r.hora_fin, r.estado, u.nombre AS uname
     FROM reservaciones r JOIN usuarios u ON u.id = r.usuario_id
     WHERE r.sala_id = ? AND r.fecha = ? AND r.estado NOT IN ("cancelada","rechazada")
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

    // Recopilar todas las reservas que cubren este slot
    $matching = [];
    foreach ($reservas as $rv) {
        if ($rv['hora_inicio'] <= $slot && $rv['hora_fin'] > $slot) {
            $matching[] = $rv;
        }
    }

    if (!empty($matching)) {
        // Prioridad: activa > pospuesta > pendiente
        $activas   = array_filter($matching, fn($r) => in_array($r['estado'], ['activa','pospuesta']));
        $pendientes = array_filter($matching, fn($r) => $r['estado'] === 'pendiente');

        if (!empty($activas)) {
            $rv = array_values($activas)[0];
            if ($rv['uname'] === $currentUser['nombre']) {
                $tipo = 'propia'; $label = 'Tu reservación';
            } else {
                $tipo  = 'ocupada';
                $label = $admin ? 'Ocupada — ' . $rv['uname'] : 'Ocupada';
            }
        } elseif (!empty($pendientes)) {
            $esMia = array_filter($pendientes, fn($r) => $r['uname'] === $currentUser['nombre']);
            if (!empty($esMia)) {
                $tipo = 'propia'; $label = 'Tu solicitud pendiente';
            } elseif ($admin && count($pendientes) > 1) {
                $nombres = implode(', ', array_map(fn($r) => $r['uname'], array_values($pendientes)));
                $tipo  = 'disputa';
                $label = count($pendientes) . ' solicitudes: ' . $nombres;
            } else {
                $tipo  = 'pendiente';
                $label = $admin ? 'Pendiente — ' . array_values($pendientes)[0]['uname'] : 'Pendiente aprobación';
            }
        }
    }

    $slots[] = ['hora' => $slot, 'tipo' => $tipo, 'label' => $label];
}

jsonResponse(['slots' => $slots]);