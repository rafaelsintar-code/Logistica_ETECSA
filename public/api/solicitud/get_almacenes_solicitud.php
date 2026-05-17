<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo conectar a la base de datos."]);
        exit;
    }

    $stmt = $pdo->query("
        SELECT LPAD(codigo_almacen::TEXT, 4, '0') AS codigo_almacen, tipo_almacen, nombre_pv
        FROM cod_almacen
        ORDER BY nombre_pv ASC, tipo_almacen ASC
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>