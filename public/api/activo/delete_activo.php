<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Falta el ID del activo."
        ]);
        exit;
    }

    $id = $data['id'];

    $stmt = $pdo->prepare("DELETE FROM activo WHERE id_activo = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Activo eliminado correctamente."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el activo."
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al eliminar: " . $e->getMessage()
    ]);
}
