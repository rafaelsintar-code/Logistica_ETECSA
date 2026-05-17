<?php
session_start();
session_unset();
session_destroy();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

echo json_encode(["success" => true, "redirect" => "../pages/login.html"]);
?>