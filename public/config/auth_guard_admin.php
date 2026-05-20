<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params([
    'lifetime' => 28800,
    'path'     => '/',
    'secure'   => false,
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
    $esAjax = isset($_SERVER['HTTP_ACCEPT']) &&
              strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }
    header("Location: /branch2/public/pages/login.html");
    exit;
}

// Con sesión pero sin rol de administrador → 403
if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    $esAjax = isset($_SERVER['HTTP_ACCEPT']) &&
              strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Acceso denegado."]);
        exit;
    }
    header("Location: /branch2/public/pages/visitante/index.html");
    exit;
}
?>
