<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


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

    // Leer JSON recibido
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['codigo_articulo'])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Falta el código del artículo."
        ]);
        exit;
    }

    $codigo = trim($data['codigo_articulo']);

    // Eliminar
    $stmt = $pdo->prepare("
        DELETE FROM articulo
        WHERE codigo_articulo = :codigo
    ");
    $stmt->execute([
        ':codigo' => $codigo
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Artículo eliminado correctamente."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No se encontró el artículo especificado."
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al eliminar: " . $e->getMessage()
    ]);
}
?>
