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
            "message" => "Error de conexión a la base de datos."
        ]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || !isset($data['nombre']) || !isset($data['tipo'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios."
        ]);
        exit;
    }

    $id     = trim($data['id']);
    $nombre = trim($data['nombre']);
    $tipo   = trim($data['tipo']);

    // Verificar duplicado
    $check = $pdo->prepare("SELECT COUNT(*) FROM activo WHERE id_activo = :id");
    $check->execute([':id' => $id]);

    if ($check->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "El ID del activo ya existe."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO activo (id_activo, nombre_activo, tipo_activo)
        VALUES (:id, :nombre, :tipo)
    ");

    $stmt->execute([
        ':id'     => $id,
        ':nombre' => $nombre,
        ':tipo'   => $tipo
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Activo agregado correctamente."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en base de datos: " . $e->getMessage()
    ]);
}
