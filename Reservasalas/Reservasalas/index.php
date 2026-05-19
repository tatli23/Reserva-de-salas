<?php
// ============================================================
//  index.php — Login / Autenticación
// ============================================================
require_once 'config/config.php';

// Si ya tiene sesión, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = trim($_POST['correo']   ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$correo || !$password) {
        $error = 'Completa todos los campos.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE correo = ? AND activo = 1');
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = [
                'id'     => $user['id'],
                'nombre' => $user['nombre'],
                'correo' => $user['correo'],
                'rol'    => $user['rol'],
            ];
            // Admin → dashboard, cualquier otro → nueva reservación
            $destino = $user['rol'] === 'admin'
                ? BASE_URL . '/dashboard.php'
                : BASE_URL . '/modules/nueva_reservacion.php';
            header('Location: ' . $destino);
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso — ITSZN ReservaSalas</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">

    <!-- Logo -->
    <div class="login-logo">
      <div class="login-icon">🔒</div>
      <h1>ITSZN · ReservaSalas</h1>
      <p>Sistema de Reservación de Salas Audiovisuales</p>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulario -->
    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="correo">Correo institucional</label>
        <input
          type="email"
          id="correo"
          name="correo"
          class="form-control"
          value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
          required
          autocomplete="username"
        >
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"

          required
          autocomplete="current-password"
        >
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
        Iniciar sesión
      </button>
    </form>

        <div style="text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--gris-borde);">
      <span style="font-size:13px;color:var(--gris-muted);">¿No tienes cuenta?</span>
      <a href="<?= BASE_URL ?>/registro.php"
         style="font-size:13px;font-weight:600;color:var(--azul-medio);text-decoration:none;margin-left:6px;">
        Regístrate aquí →
      </a>
    </div>

      </div>
    </div>

  </div>
</div>
</body>
</html>