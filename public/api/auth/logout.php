<?php
header('Content-Type: application/json; charset=utf-8');

// Verificar el método ANTES de iniciar o destruir la sesión.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

session_set_cookie_params([
    'lifetime' => 28800,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
session_unset();
session_destroy();

echo json_encode(["success" => true, "redirect" => "/branch2/public/pages/login.html"]);
?>
