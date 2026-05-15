<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("
    SELECT DISTINCT
        almacen
    FROM mb52
    ORDER BY almacen
");

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
