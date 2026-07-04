<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard.php';
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

    // Carga completa — el JS gestiona la paginación en cliente
    $stmt = $pdo->prepare("
        SELECT
            codigo_articulo,
            codigo_sigc,
            descripcion,
            familia,
            precio_usd,
            precio_cup,
            acta_precio,
            garantia
        FROM articulo
        ORDER BY familia ASC
    ");
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
}
?>
