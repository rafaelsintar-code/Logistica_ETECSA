<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);

try {

    if (
        empty($data['username']) ||
        empty($data['nombre']) ||
        empty($data['correo']) ||
        empty($data['password'])
    ) {
        throw new Exception("Datos incompletos.");
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        INSERT INTO usuario
        (username, nombre, correo, password_hash, rol, activo, creado_en)
        VALUES
        (:username, :nombre, :correo, :password, :rol, true, NOW())
    ");

    $stmt->execute([
        ':username' => $data['username'],
        ':nombre'   => $data['nombre'],
        ':correo'   => $data['correo'],
        ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':rol'      => $data['rol'] ?? 'admin'
    ]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
