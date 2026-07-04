<?php
require_once __DIR__ . '/session_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';
csrf_get_token();

verificar_sesion();

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
