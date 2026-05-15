<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "No se pudo establecer conexión con la base de datos."
        ]);
        exit;
    }

    $sql = "
        SELECT id_activo, nombre_activo, tipo_activo
        FROM activo
        ORDER BY id_activo ASC
    ";

    $stmt = $pdo->query($sql);
    $activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($activos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
}
