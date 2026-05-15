<?php
require_once '../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || !isset($data['nombre']) || !isset($data['tipo'])) {
        http_response_code(400);
        echo json_encode(["error" => "Datos incompletos."]);
        exit;
    }

    $id     = $data['id'];
    $nombre = trim($data['nombre']);
    $tipo   = trim($data['tipo']);

    $database = new Database();
    $pdo = $database->getConnection();

    // Verificar nombre duplicado
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM activo 
        WHERE nombre_activo = :nombre AND id_activo <> :id
    ");

    $check->execute([
        ':nombre' => $nombre,
        ':id'     => $id
    ]);

    if ($check->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(["error" => "Ya existe otro activo con ese nombre."]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE activo
        SET nombre_activo = :nombre,
            tipo_activo   = :tipo
        WHERE id_activo = :id
    ");

    $stmt->execute([
        ':nombre' => $nombre,
        ':tipo'   => $tipo,
        ':id'     => $id
    ]);

    echo json_encode(["message" => "Activo actualizado correctamente."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
