<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);

try {

    if (!isset($data['username']) || $data['username'] === '') {
        throw new Exception("Username requerido.");
    }

    if (!isset($data['nombre']) || !isset($data['correo'])) {
        throw new Exception("Nombre y correo obligatorios.");
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    $activo = false;
    if (isset($data['activo'])) {
        $activo = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN);
    }

    $sql = "
        UPDATE usuario
        SET
            nombre = :nombre,
            correo = :correo,
            rol = :rol,
            activo = :activo,
            actualizado_en = NOW()
    ";

    if (!empty($data['password'])) {
        $sql .= ", password_hash = :password";
    }

    $sql .= " WHERE username = :username";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':username', $data['username'], PDO::PARAM_STR);
    $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
    $stmt->bindValue(':correo', $data['correo'], PDO::PARAM_STR);
    $stmt->bindValue(':rol', $data['rol'] ?? 'Administrador', PDO::PARAM_STR);
    $stmt->bindValue(':activo', $activo, PDO::PARAM_BOOL);

    if (!empty($data['password'])) {
        $stmt->bindValue(
            ':password',
            password_hash($data['password'], PASSWORD_DEFAULT),
            PDO::PARAM_STR
        );
    }

    $stmt->execute();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
