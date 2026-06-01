<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/csrf.php';
session_set_cookie_params([
    'lifetime' => 28800,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
csrf_verify();
session_unset();
session_destroy();

// Detectar la URL base de public/ dinámicamente:
// SCRIPT_NAME = /<prefijo>/api/auth/logout.php
// dirname x1  = /<prefijo>/api/auth
// dirname x2  = /<prefijo>/api
// dirname x3  = /<prefijo>           ← raíz de public/
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // /…/api/auth
$appBase   = rtrim(dirname(dirname($scriptDir)), '/');                  // /…/public prefix

echo json_encode(["success" => true, "redirect" => $appBase . "/pages/login.html"]);
?>
