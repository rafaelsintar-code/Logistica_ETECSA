<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


$db  = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("
    SELECT DISTINCT
        material,
        desc_articulo
    FROM mb52
    ORDER BY desc_articulo
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
