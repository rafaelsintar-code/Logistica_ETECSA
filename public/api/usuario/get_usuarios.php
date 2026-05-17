<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';

if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acceso denegado."]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->query("
        SELECT
            username,
            nombre,
            correo,
            rol,
            activo,
            intentos_fallidos,
            bloqueado_hasta,
            ultimo_acceso,
            creado_en
        FROM usuario
        ORDER BY username
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error cargando usuarios"]);
}
?>
