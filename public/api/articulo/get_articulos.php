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
            "message" => "No se pudo establecer conexión con la base de datos."
        ]);
        exit;
    }

    $sql = "
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
    ";

    $stmt = $pdo->query($sql);
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($articulos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
