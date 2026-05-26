<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params([
    'lifetime' => 28800,
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
?>
