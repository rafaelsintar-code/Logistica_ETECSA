<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("
    SELECT DISTINCT
        material
    FROM mb52
    ORDER BY material
");

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));