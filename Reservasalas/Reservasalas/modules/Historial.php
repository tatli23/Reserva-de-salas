<?php
// ============================================================
//  modules/historial.php — Historial de reservaciones
// ============================================================
require_once '../config/config.php';
requireLogin();
require_once '../includes/layout.php';

$pdo   = getDB();
$uid   = $_SESSION['user_id'];
$admin = isAdmin();

// Filtros
$fEstado = $_GET['estado'] ?? 'todas';
$fMes    = $_GET['mes']    ?? date('Y-m');
$fSala   = $_GET['sala']   ?? '0';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 10;
$offset  = ($page - 1) * $limit;

// Construir WHERE
$wheres = [];
$params = [];
if (!$admin) { $wheres[] = 'r.usuario_id = ?'; $params[] = $uid; }
if ($fEstado !== 'todas') { $wheres[] = 'r.estado = ?'; $params[] = $fEstado; }
if ($fMes)   { $wheres[] = 'DATE_FORMAT(r.fecha, "%Y-%m") = ?'; $params[] = $fMes; }
if ($fSala && $fSala !== '0') { $wheres[] = 'r.sala_id = ?'; $params[] = $fSala; }

$whereSQL = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Total para paginación
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM reservaciones r $whereSQL");
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetchColumn();
$totalPages = ceil($total / $limit);

// Reservaciones
$stmtR = $pdo->prepare(
    "SELECT r.*, s.codigo, s.nombre AS sala_nombre, u.nombre AS uname
     FROM reservaciones r
     JOIN salas s ON s.id = r.sala_id
     JOIN usuarios u ON u.id = r.usuario_id
     $whereSQL
     ORDER BY r.fecha DESC, r.hora_inicio DESC
     LIMIT $limit OFFSET $offset"
);
$stmtR->execute($params);
$reservaciones = $stmtR->fetchAll();

// Lista de salas para filtro
$salas = $pdo->query('SELECT id, codigo FROM salas ORDER BY codigo')->fetchAll();

function badgeEstado2(string $e): string {
    return match($e) {
        'activa'     => "<span class='badge badge-success'>Activa</span>",
        'pendiente'  => "<span class='badge badge-info'>Pendiente</span>",
        'completada' => "<span class='badge badge-muted'>Completada</span>",
        'cancelada'  => "<span class='badge badge-danger'>Cancelada</span>",
        'pospuesta'  => "<span class='badge badge-warning'>Pospuesta</span>",
        default      => "<span class='badge badge-muted'>$e</span>",
    };
}

startLayout('Historial', 'historial');
?>

<h1 class="page-title">Historial de reservaciones</h1>

<!-- Filtros -->
<div class="card" style="margin-bottom:20px;padding:16px 20px;">
  <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-control" onchange="this.form.submit()">
        <option value="todas"      <?= $fEstado==='todas'     ?'selected':'' ?>>Todas</option>
        <option value="activa"     <?= $fEstado==='activa'    ?'selected':'' ?>>Activa</option>
        <option value="pendiente"  <?= $fEstado==='pendiente' ?'selected':'' ?>>Pendiente</option>
        <option value="completada" <?= $fEstado==='completada'?'selected':'' ?>>Completada</option>
        <option value="cancelada"  <?= $fEstado==='cancelada' ?'selected':'' ?>>Cancelada</option>
        <option value="pospuesta"  <?= $fEstado==='pospuesta' ?'selected':'' ?>>Pospuesta</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label class="form-label">Mes</label>
      <input type="month" name="mes" class="form-control"
             value="<?= htmlspecialchars($fMes) ?>" onchange="this.form.submit()">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label class="form-label">Sala</label>
      <select name="sala" class="form-control" onchange="this.form.submit()">
        <option value="0">Todas las salas</option>
        <?php foreach ($salas as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $fSala == $s['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['codigo']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<!-- Listado -->
<div class="card" style="padding:0 0 8px;">
  <?php if (empty($reservaciones)): ?>
    <p style="padding:24px;color:var(--gris-muted);font-size:14px;">No hay reservaciones con esos filtros.</p>
  <?php else: ?>
  <?php foreach ($reservaciones as $r):
    $dotColor = match($r['estado']) {
        'activa','completada' => '#1a7a4a',
        'cancelada'           => '#c0392b',
        'pospuesta'           => '#b7600d',
        default               => '#1e4d9b',
    };
  ?>
  <div style="display:flex;gap:14px;align-items:center;padding:14px 20px;
              border-bottom:1px solid #f0f2f7;position:relative;flex-wrap:wrap;">
    <!-- Dot -->
    <div style="width:12px;height:12px;border-radius:50%;
                background:<?= $dotColor ?>;flex-shrink:0;"></div>

    <!-- Info sala -->
    <div style="flex:1;min-width:120px;">
      <div style="font-weight:700;font-size:14px;color:var(--azul-oscuro);">
        <?= htmlspecialchars($r['codigo']) ?>
      </div>
      <div style="font-size:12px;color:var(--gris-muted);">
        <?= htmlspecialchars($r['proposito']) ?>
      </div>
    </div>

    <!-- Fecha y hora -->
    <div style="min-width:160px;font-size:13px;color:var(--gris-texto);">
      <?= date('d M Y', strtotime($r['fecha'])) ?>,
      <?= substr($r['hora_inicio'],0,5) ?>–<?= substr($r['hora_fin'],0,5) ?>
    </div>

    <?php if ($admin): ?>
    <div style="min-width:120px;font-size:12px;color:var(--gris-muted);">
      <?= htmlspecialchars($r['uname']) ?>
    </div>
    <?php endif; ?>

    <!-- Estado -->
    <div style="min-width:100px;">
      <?= badgeEstado2($r['estado']) ?>
    </div>

    <!-- Acciones -->
    <div style="display:flex;gap:6px;align-items:center;">
      <?php if (in_array($r['estado'], ['activa','pendiente'])): ?>
        <a href="<?= BASE_URL ?>/modules/posponer.php?id=<?= $r['id'] ?>"
           class="btn btn-warning btn-sm"> Posponer</a>
        <a href="<?= BASE_URL ?>/api/cancelar.php?id=<?= $r['id'] ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('¿Cancelar esta reservación?')">✕ Cancelar</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Paginación -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++):
    $url = '?' . http_build_query(['estado'=>$fEstado,'mes'=>$fMes,'sala'=>$fSala,'page'=>$p]);
  ?>
    <?php if ($p === $page): ?>
      <span class="current"><?= $p ?></span>
    <?php else: ?>
      <a href="<?= $url ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endLayout(); ?>