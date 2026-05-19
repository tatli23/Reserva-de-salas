<?php
// api/exportar.php — Exportar reservaciones a CSV
require_once '../config/config.php';
requireLogin();
if (!isAdmin()) { die('Sin permiso.'); }

$pdo = getDB();
$mes = $_GET['mes'] ?? date('Y-m');

$stmt = $pdo->prepare(
    "SELECT r.id, s.codigo AS sala, u.nombre AS docente, r.fecha, r.hora_inicio, r.hora_fin,
            r.proposito, r.estado
     FROM reservaciones r
     JOIN salas s ON s.id=r.sala_id JOIN usuarios u ON u.id=r.usuario_id
     WHERE DATE_FORMAT(r.fecha, '%Y-%m') = ?
     ORDER BY r.fecha, r.hora_inicio"
);
$stmt->execute([$mes]);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reservaciones_' . $mes . '.csv"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM para Excel
fputcsv($out, ['ID','Sala','Docente','Fecha','Hora inicio','Hora fin','Propósito','Estado']);
foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'], $row['sala'], $row['docente'], $row['fecha'],
        $row['hora_inicio'], $row['hora_fin'], $row['proposito'], $row['estado']
    ]);
}
fclose($out);
exit;