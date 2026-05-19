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
$nombre  = $_POST['nombre']          ?? '';
$correo  = $_POST['correo']          ?? '';
$rol     = $_POST['rol']             ?? 'alumno';
$num_control  = $_POST['num_control']  ?? '';
$num_empleado = $_POST['num_empleado'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validaciones básicas
    if (!trim($nombre))   $errores[] = 'Escribe tu nombre completo.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo inválido.';
    if (strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2)  $errores[] = 'Las contraseñas no coinciden.';
    if (!in_array($rol, ['alumno','docente','coordinador','personal'])) $errores[] = 'Rol no válido.';

    // Validación número de control (solo alumnos)
    if ($rol === 'alumno') {
        $nc = trim($num_control);
        if (!preg_match('/^\d{8}$/', $nc)) {
            $errores[] = 'El número de control debe tener exactamente 8 dígitos.';
        } else {
            $anio = (int) substr($nc, 0, 2);
            $mid  = substr($nc, 2, 3);
            if ($anio < 20 || $anio > 25)
                $errores[] = 'Los primeros 2 dígitos deben estar entre 20 y 25 (año de ingreso).';
            if ($mid !== '010')
                $errores[] = 'Los dígitos 3, 4 y 5 del número de control deben ser 010.';
        }
    } else {
        // Validación número de empleado
        $ne = trim($num_empleado);
        if (strlen($ne) !== 5)
            $errores[] = 'El número de empleado debe tener exactamente 5 caracteres.';
    }

    if (empty($errores)) {
        $pdo = getDB();
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
        $chk->execute([$correo]);
        if ($chk->fetch()) {
            $errores[] = 'Ese correo ya está registrado.';
        } else {
            $hash          = password_hash($password, PASSWORD_BCRYPT);
            $identificador = ($rol === 'alumno') ? trim($num_control) : trim($num_empleado);

            $ins = $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password, rol, identificador)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([trim($nombre), $correo, $hash, $rol, $identificador]);

            // Notificar al administrador
            $uid = $pdo->lastInsertId();
            $adm = $pdo->query('SELECT id FROM usuarios WHERE rol="admin" LIMIT 1')->fetch();
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

    /* ── Barra de fuerza de contraseña ── */
    .strength-bar {
      height: 4px; border-radius: 2px; margin-top: 6px;
      transition: width .3s, background .3s;
      width: 0%; background: var(--gris-borde);
    }
    .strength-text { font-size: 11px; color: var(--gris-muted); margin-top: 3px; }

    /* ── Select de tipo de usuario ── */
    .tipo-label {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--gris-muted);
      margin-bottom: 6px;
      display: block;
    }
    .rol-select {
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      background-color: #fff;
      padding-right: 38px;
      cursor: pointer;
      font-size: 14px;
    }
    .rol-select:focus {
      border-color: var(--azul-medio);
      outline: none;
      box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    }

    /* ── Campos de identificador ── */
    .id-hint {
      font-size: 11px; color: var(--gris-muted);
      margin-top: 4px; line-height: 1.5;
    }
    .id-hint code {
      background: var(--azul-claro); color: var(--azul-oscuro);
      padding: 1px 5px; border-radius: 3px; font-size: 11px;
    }
    .input-valid   { border-color: var(--verde)  !important; }
    .input-invalid { border-color: var(--rojo)   !important; }

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
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary"
       style="width:100%;justify-content:center;">
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
        <label class="form-label" for="nombre">
          Nombre completo <span class="req">*</span>
        </label>
        <input type="text" id="nombre" name="nombre" class="form-control"
               placeholder=""
               value="<?= htmlspecialchars($nombre) ?>" required>
      </div>

      <!-- Correo -->
      <div class="form-group">
        <label class="form-label" for="correo">
          Correo institucional <span class="req">*</span>
        </label>
        <input type="email" id="correo" name="correo" class="form-control"
               placeholder=""
               value="<?= htmlspecialchars($correo) ?>" required>
      </div>

      <!-- Contraseña -->
      <div class="form-group">
        <label class="form-label" for="password">
          Contraseña <span class="req">*</span>
        </label>
        <div class="pwd-wrap">
          <input type="password" id="password" name="password"
                 class="form-control" placeholder="Mínimo 6 caracteres"
                 oninput="medirFuerza(this.value)" required>
          <button type="button" class="pwd-toggle"
                  onclick="togglePwd('password', this)">👁</button>
        </div>
        <div class="strength-bar" id="strength-bar"></div>
        <div class="strength-text" id="strength-text"></div>
      </div>

      <!-- Confirmar contraseña -->
      <div class="form-group">
        <label class="form-label" for="password2">
          Confirmar contraseña <span class="req">*</span>
        </label>
        <div class="pwd-wrap">
          <input type="password" id="password2" name="password2"
                 class="form-control" placeholder="Repite tu contraseña"
                 oninput="verificarMatch()" required>
          <button type="button" class="pwd-toggle"
                  onclick="togglePwd('password2', this)">👁</button>
        </div>
        <div class="strength-text" id="match-text"></div>
      </div>

      <!-- ── Tipo de usuario (dropdown) ── -->
      <div class="form-group">
        <span class="tipo-label">Tipo de usuario</span>
        <select name="rol" id="rol" class="form-control rol-select"
                onchange="cambiarRol(this.value)" required>
          <?php
          $roles = [
            'alumno'      => 'Alumno',
            'docente'     => 'Docente',
            'coordinador' => 'Coordinador',
            'personal'    => 'Personal',
          ];
          foreach ($roles as $val => $label):
          ?>
          <option value="<?= $val ?>" <?= $rol === $val ? 'selected' : '' ?>>
            <?= $label ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ── Número de control (Alumnos) ── -->
      <div class="form-group" id="campo-alumno"
           style="<?= $rol !== 'alumno' ? 'display:none;' : '' ?>">
        <label class="form-label" for="num_control">
          Número de control <span class="req">*</span>
        </label>
        <input type="text" id="num_control" name="num_control"
               class="form-control" maxlength="8"
               placeholder="Ej. 22010045"
               value="<?= htmlspecialchars($num_control) ?>"
               oninput="validarControl(this)">
        <div class="strength-text" id="ctrl-text"></div>
      </div>

      <!-- ── Número de empleado (Docente / Coordinador / Personal) ── -->
      <div class="form-group" id="campo-empleado"
           style="<?= $rol === 'alumno' ? 'display:none;' : '' ?>">
        <label class="form-label" for="num_empleado">
          Número de empleado <span class="req">*</span>
        </label>
        <input type="text" id="num_empleado" name="num_empleado"
               class="form-control" maxlength="5"
               placeholder="Ej. E0042"
               value="<?= htmlspecialchars($num_empleado) ?>"
               oninput="validarEmpleado(this)">
        <div class="strength-text" id="emp-text"></div>
      </div>

      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center;padding:12px;margin-top:4px;">
        Crear cuenta
      </button>
    </form>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/index.php"
       class="btn btn-ghost" style="width:100%;justify-content:center;">
      ← Iniciar sesión
    </a>

  </div>
</div>

<script>
// ── Mostrar/ocultar contraseña ──────────────────────────────
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  if (inp.type === 'password') { inp.type = 'text';     btn.textContent = '🙈'; }
  else                         { inp.type = 'password'; btn.textContent = '👁'; }
}

// ── Fuerza de contraseña ────────────────────────────────────
function medirFuerza(val) {
  const bar = document.getElementById('strength-bar');
  const txt = document.getElementById('strength-text');
  let score = 0;
  if (val.length >= 6)           score++;
  if (val.length >= 10)          score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
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

// ── Coincidencia de contraseñas ─────────────────────────────
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

// ── Cambio de tipo de usuario ───────────────────────────────
function cambiarRol(val) {
  const esAlumno = (val === 'alumno');
  document.getElementById('campo-alumno').style.display   = esAlumno ? '' : 'none';
  document.getElementById('campo-empleado').style.display = esAlumno ? 'none' : '';

  // Limpiar validaciones visuales al cambiar
  ['num_control', 'num_empleado'].forEach(id => {
    document.getElementById(id).classList.remove('input-valid', 'input-invalid');
    document.getElementById(id).value = '';
  });
  document.getElementById('ctrl-text').textContent = '';
  document.getElementById('emp-text').textContent  = '';
}

// ── Validación en tiempo real: número de control ────────────
function validarControl(inp) {
  const val = inp.value;
  const txt = document.getElementById('ctrl-text');
  inp.classList.remove('input-valid', 'input-invalid');

  if (!val) { txt.textContent = ''; return; }

  if (!/^\d+$/.test(val)) {
    inp.classList.add('input-invalid');
    txt.textContent = '✕ Solo se permiten dígitos.';
    txt.style.color = 'var(--rojo)'; return;
  }
  if (val.length < 8) {
    txt.textContent = `${val.length}/8 dígitos…`;
    txt.style.color = 'var(--gris-muted)'; return;
  }

  const anio = parseInt(val.substring(0, 2), 10);
  const mid  = val.substring(2, 5);
  let msgs   = [];

  if (anio < 20 || anio > 25) msgs.push('los primeros 2 dígitos deben ser 20–25');
  if (mid !== '010')           msgs.push('los dígitos 3–5 deben ser 010');

  if (msgs.length) {
    inp.classList.add('input-invalid');
    txt.textContent = '✕ ' + msgs.join('; ') + '.';
    txt.style.color = 'var(--rojo)';
  } else {
    inp.classList.add('input-valid');
    txt.textContent = '✓ Número de control válido';
    txt.style.color = 'var(--verde)';
  }
}

// ── Validación en tiempo real: número de empleado ───────────
function validarEmpleado(inp) {
  const val = inp.value;
  const txt = document.getElementById('emp-text');
  inp.classList.remove('input-valid', 'input-invalid');

  if (!val) { txt.textContent = ''; return; }

  if (val.length < 5) {
    txt.textContent = `${val.length}/5 caracteres…`;
    txt.style.color = 'var(--gris-muted)';
  } else {
    inp.classList.add('input-valid');
    txt.textContent = '✓ Número de empleado válido';
    txt.style.color = 'var(--verde)';
  }
}

// ── Validación final antes de enviar ───────────────────────
document.getElementById('frmRegistro')?.addEventListener('submit', function(e) {
  const p1  = document.getElementById('password').value;
  const p2  = document.getElementById('password2').value;
  const rol = document.getElementById('rol').value;

  if (p1 !== p2) {
    e.preventDefault();
    alert('Las contraseñas no coinciden.'); return;
  }

  if (rol === 'alumno') {
    const nc   = document.getElementById('num_control').value.trim();
    const anio = parseInt(nc.substring(0, 2), 10);
    const mid  = nc.substring(2, 5);
    if (nc.length !== 8 || !/^\d{8}$/.test(nc) || anio < 20 || anio > 25 || mid !== '010') {
      e.preventDefault();
      alert('Número de control inválido.\nFormato esperado: AA010NNN\nEjemplo: 22010045');
    }
  } else {
    const ne = document.getElementById('num_empleado').value.trim();
    if (ne.length !== 5) {
      e.preventDefault();
      alert('El número de empleado debe tener exactamente 5 caracteres.');
    }
  }
});
</script>
</body>
</html>