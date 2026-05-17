<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $stmt = $pdo->query("
        SELECT
            id,
            LPAD(almacen_origen::TEXT, 4, '0')  AS almacen_origen,
            denom_origen,
            LPAD(almacen_destino::TEXT, 4, '0') AS almacen_destino,
            denom_destino,
            usuario,
            hora_creacion,
            hora_confirmacion,
            vale
        FROM transferencia
        ORDER BY hora_creacion DESC
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