<?php
// ============================================================
//  registro.php — Registro de nuevo usuario
// ============================================================
require_once 'config/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errores = [];
$ok      = false;
$nombre  = $_POST['nombre']   ?? '';
$correo  = $_POST['correo']   ?? '';
$rol     = $_POST['rol']      ?? 'docente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validaciones
    if (!trim($nombre))   $errores[] = 'Escribe tu nombre completo.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo inválido.';
    if (strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2)  $errores[] = 'Las contraseñas no coinciden.';
    if (!in_array($rol, ['docente','coordinador','personal'])) $errores[] = 'Rol no válido.';

    if (empty($errores)) {
        $pdo  = getDB();
        // ¿Ya existe ese correo?
        $chk  = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
        $chk->execute([$correo]);
        if ($chk->fetch()) {
            $errores[] = 'Ese correo ya está registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)'
            );
            $ins->execute([trim($nombre), $correo, $hash, $rol]);

            // Notificar al administrador
            $uid  = $pdo->lastInsertId();
            $adm  = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1')->fetch();
            if ($adm) {
                $pdo->prepare(
                    'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje)
                     VALUES (?, "nueva_reserva", "Nuevo usuario registrado",
                     CONCAT("Se registró el usuario ", ?, " (", ?, ") con rol ", ?))'
                )->execute([$adm['id'], trim($nombre), $correo, $rol]);
            }

            $ok = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro — ITSZN ReservaSalas</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .pwd-wrap { position: relative; }
    .pwd-toggle {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      cursor: pointer; font-size: 16px;
      background: none; border: none; color: var(--gris-muted);
      padding: 0; line-height: 1;
    }
    .pwd-wrap .form-control { padding-right: 40px; }
    .req { color: var(--rojo); }
    .strength-bar {
      height: 4px; border-radius: 2px; margin-top: 6px;
      transition: width .3s, background .3s;
      width: 0%; background: var(--gris-borde);
    }
    .strength-text { font-size: 11px; color: var(--gris-muted); margin-top: 3px; }
    .rol-cards {
      display: grid; grid-template-columns: repeat(3,1fr); gap: 8px;
    }
    .rol-card {
      border: 1.5px solid var(--gris-borde);
      border-radius: var(--radio);
      padding: 10px 8px;
      cursor: pointer;
      text-align: center;
      transition: all .15s;
      font-size: 13px;
    }
    .rol-card:hover { border-color: var(--azul-medio); background: var(--azul-claro); }
    .rol-card.selected {
      border-color: var(--azul-medio);
      background: var(--azul-claro);
      color: var(--azul-oscuro);
      font-weight: 600;
    }
    .rol-card .rol-icon { font-size: 22px; display: block; margin-bottom: 4px; }
    .rol-card input[type=radio] { display: none; }
    .divider {
      text-align: center; font-size: 12px; color: var(--gris-muted);
      position: relative; margin: 20px 0;
    }
    .divider::before, .divider::after {
      content: ''; display: inline-block; width: 38%; height: 1px;
      background: var(--gris-borde); vertical-align: middle; margin: 0 8px;
    }
  </style>
</head>
<body>
<div class="login-page">
  <div class="login-card" style="max-width:460px;">

    <!-- Logo -->
    <div class="login-logo">
      <div class="login-icon">📝</div>
      <h1>Crear cuenta</h1>
      <p>ITSZN · ReservaSalas</p>
    </div>

    <!-- Éxito -->
    <?php if ($ok): ?>
    <div class="alert alert-success" style="margin-bottom:16px;">
      ✅ ¡Cuenta creada! Ahora puedes iniciar sesión.
    </div>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary" style="width:100%;justify-content:center;">
      Ir al login →
    </a>
    <div class="divider">o</div>
    <?php endif; ?>

    <!-- Errores -->
    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger" style="margin-bottom:16px;">
      <?php foreach ($errores as $e): ?>
      <p style="margin:2px 0;">• <?= htmlspecialchars($e) ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$ok): ?>
    <form method="POST" action="" id="frmRegistro" autocomplete="off">

      <!-- Nombre -->
      <div class="form-group">
        <label class="form-label" for="nombre">Nombre completo <span class="req">*</span></label>
        <input type="text" id="nombre" name="nombre" class="form-control"
               placeholder="Ej. Juan López García"
               value="<?= htmlspecialchars($nombre) ?>" required>
      </div>

      <!-- Correo -->
      <div class="form-group">
        <label class="form-label" for="correo">Correo institucional <span class="req">*</span></label>
        <input type="email" id="correo" name="correo" class="form-control"
               placeholder="usuario@itszn.edu.mx"
               value="<?= htmlspecialchars($correo) ?>" required>
      </div>

      <!-- Contraseña -->
      <div class="form-group">
        <label class="form-label" for="password">Contraseña <span class="req">*</span></label>
        <div class="pwd-wrap">
          <input type="password" id="password" name="password"
                 class="form-control" placeholder="Mínimo 6 caracteres"
                 oninput="medirFuerza(this.value)" required>
          <button type="button" class="pwd-toggle" onclick="togglePwd('password', this)">👁</button>
        </div>
        <div class="strength-bar" id="strength-bar"></div>
        <div class="strength-text" id="strength-text"></div>
      </div>

      <!-- Confirmar contraseña -->
      <div class="form-group">
        <label class="form-label" for="password2">Confirmar contraseña <span class="req">*</span></label>
        <div class="pwd-wrap">
          <input type="password" id="password2" name="password2"
                 class="form-control" placeholder="Repite tu contraseña"
                 oninput="verificarMatch()" required>
          <button type="button" class="pwd-toggle" onclick="togglePwd('password2', this)">👁</button>
        </div>
        <div class="strength-text" id="match-text"></div>
      </div>

      <!-- Rol -->
      <div class="form-group">
        <label class="form-label">Rol <span class="req">*</span></label>
        <div class="rol-cards">
          <?php
          $roles = [
            'docente'      => ['icon' => '👨‍🏫', 'label' => 'Docente'],
            'coordinador'  => ['icon' => '📋', 'label' => 'Coordinador'],
            'personal'     => ['icon' => '👤', 'label' => 'Personal'],
          ];
          foreach ($roles as $val => $info):
            $sel = ($rol === $val) ? 'selected' : '';
          ?>
          <label class="rol-card <?= $sel ?>" id="card-<?= $val ?>">
            <input type="radio" name="rol" value="<?= $val ?>"
                   <?= $sel ? 'checked' : '' ?>
                   onchange="selCard('<?= $val ?>')">
            <span class="rol-icon"><?= $info['icon'] ?></span>
            <?= $info['label'] ?>
          </label>
          <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:var(--gris-muted);margin-top:6px;">
          ℹ️ El rol de Administrador solo puede asignarse desde el panel de administración.
        </p>
      </div>

      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center;padding:12px;margin-top:4px;">
        Crear cuenta
      </button>
    </form>
    <?php endif; ?>

    <div class="divider">¿ya tienes cuenta?</div>
    <a href="<?= BASE_URL ?>/index.php"
       class="btn btn-ghost" style="width:100%;justify-content:center;">
      ← Iniciar sesión
    </a>

  </div>
</div>

<script>
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
  else { inp.type = 'password'; btn.textContent = '👁'; }
}

function medirFuerza(val) {
  const bar  = document.getElementById('strength-bar');
  const txt  = document.getElementById('strength-text');
  let score  = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const niveles = [
    { pct: '0%',   bg: 'transparent', label: '' },
    { pct: '25%',  bg: '#c0392b',     label: 'Muy débil' },
    { pct: '50%',  bg: '#e67e22',     label: 'Débil' },
    { pct: '75%',  bg: '#f1c40f',     label: 'Regular' },
    { pct: '90%',  bg: '#2ecc71',     label: 'Fuerte' },
    { pct: '100%', bg: '#1a7a4a',     label: '¡Muy fuerte!' },
  ];
  const n = niveles[Math.min(score, 5)];
  bar.style.width      = n.pct;
  bar.style.background = n.bg;
  txt.textContent      = n.label;
  txt.style.color      = n.bg;
}

function verificarMatch() {
  const p1  = document.getElementById('password').value;
  const p2  = document.getElementById('password2').value;
  const txt = document.getElementById('match-text');
  if (!p2) { txt.textContent = ''; return; }
  if (p1 === p2) {
    txt.textContent = '✓ Las contraseñas coinciden';
    txt.style.color = 'var(--verde)';
  } else {
    txt.textContent = '✕ No coinciden';
    txt.style.color = 'var(--rojo)';
  }
}

function selCard(val) {
  document.querySelectorAll('.rol-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('card-' + val).classList.add('selected');
}

// Validación final antes de enviar
document.getElementById('frmRegistro')?.addEventListener('submit', function(e) {
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1 !== p2) {
    e.preventDefault();
    alert('Las contraseñas no coinciden.');
  }
});
</script>
</body>
</html>