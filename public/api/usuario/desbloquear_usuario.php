<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';

if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acceso denegado."]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents("php://input"), true);

try {
    if (empty($data['username'])) {
        throw new Exception("Username requerido.");
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        UPDATE usuario
        SET intentos_fallidos = 0,
            bloqueado_hasta   = NULL
        WHERE username = :username
    ");
    $stmt->execute([':username' => $data['username']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Usuario no encontrado.");
    }

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
