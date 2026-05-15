<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }

    $almacen = isset($_GET['almacen']) ? trim($_GET['almacen']) : '';

    if ($almacen === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Código de almacén no proporcionado."]);
        exit;
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            material          AS codigo_articulo,
            desc_articulo,
            libre_utilizacion AS stock
        FROM mb52
        WHERE almacen = :almacen
          AND libre_utilizacion > 0
        ORDER BY desc_articulo ASC
    ");
    $stmt->execute([':almacen' => $almacen]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>