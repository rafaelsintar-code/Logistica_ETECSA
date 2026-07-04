<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/csrf.php'; // necesario por app_base_url(), aunque csrf_verify() no se use aquí

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

echo json_encode(["success" => true, "redirect" => app_base_url() . "/pages/login.html"]);
?>
