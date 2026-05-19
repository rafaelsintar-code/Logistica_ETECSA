<?php
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['id_usuario'])) {
    // Detectar petición AJAX/fetch por Accept header O por XMLHttpRequest header
    // Los fetch() del navegador no siempre envían Accept: application/json
    // a menos que se lo indiquemos explícitamente, pero sí envían un Accept
    // que no es text/html. Además verificamos X-Requested-With como fallback.
    $accept       = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $xRequested   = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
    $esAjax = strpos($accept, 'application/json') !== false
           || strtolower($xRequested) === 'xmlhttprequest'
           || strpos($accept, 'text/html') === false;

    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }
    header("Location: /branch2/public/pages/login.html");
    exit;
}
?>
