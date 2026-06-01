<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || trim($data['id']) === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID de transferencia no proporcionado."]);
        exit;
    }

    $id       = (int) $data['id'];
    $esAdmin  = strtolower($_SESSION['rol'] ?? '') === 'administrador';
    $username = $_SESSION['username'];

    $db  = new Database();
    $pdo = $db->getConnection();

    // Solo se puede eliminar si no está confirmada.
    // El administrador puede eliminar cualquiera; el visitante solo las suyas.
    if ($esAdmin) {
        $stmt = $pdo->prepare("
            DELETE FROM transferencia
            WHERE id = :id
              AND hora_confirmacion IS NULL
        ");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("
            DELETE FROM transferencia
            WHERE id      = :id
              AND usuario = :usuario
              AND hora_confirmacion IS NULL
        ");
        $stmt->execute([':id' => $id, ':usuario' => $username]);
    }

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró la transferencia, ya fue confirmada o no le pertenece."
        ]);
        exit;
    }

    echo json_encode(["success" => true, "message" => "Transferencia eliminada correctamente."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
}
?>