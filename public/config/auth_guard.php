<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['id_usuario'])) {
    // Si es una petición AJAX/API → responder con JSON
    if (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
    ) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada.", "redirect" => "../pages/login.html"]);
        exit;
    }

    // Si es carga de página → redirigir al login
    header("Location: ../pages/login.html");
    exit;
}
?>