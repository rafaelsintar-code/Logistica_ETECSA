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


try {

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
            LPAD(codigo_almacen::TEXT, 4, '0') AS codigo_almacen,
            tipo_almacen,
            nombre_pv,
            usuario,
            hora_creacion,
            hora_confirmacion,
            vale
        FROM solicitud
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