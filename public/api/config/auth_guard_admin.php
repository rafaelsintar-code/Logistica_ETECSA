<?php
const SESSION_TIMEOUT = 300; // 5 minutos de inactividad

ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';
csrf_get_token();

// Sin sesión → redirigir al login
if (empty($_SESSION['id_usuario'])) {
    if (es_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }
    header("Location: " . app_base_url() . "/pages/login.html");
    exit;
}

// Verificar inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    if (es_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión expirada por inactividad."]);
        exit;
    }
    header("Location: " . app_base_url() . "/pages/login.html");
    exit;
}

// Actualizar timestamp de última actividad
$_SESSION['last_activity'] = time();

// Con sesión pero sin rol de administrador → 403
if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    if (es_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Acceso denegado."]);
        exit;
    }
    header("Location: " . app_base_url() . "/pages/visitante/index.html");
    exit;
}
?>
