<?php
// ============================================================
//  ReservaSalas ITSZN — Configuración principal
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'reserva_salas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'ReservaSalas');
define('APP_INST', 'ITSZN');
define('BASE_URL', 'http://localhost/ReservaSalas');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Inicio de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Conexión PDO ──────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ── Helpers de sesión ─────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isAdmin(): bool {
    return ($_SESSION['user']['rol'] ?? '') === 'admin';
}

// ── Respuesta JSON ────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}