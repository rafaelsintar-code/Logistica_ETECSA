<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("
    SELECT DISTINCT
        LPAD(almacen::TEXT, 4, '0') AS almacen
    FROM mb52
    ORDER BY almacen
");

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
