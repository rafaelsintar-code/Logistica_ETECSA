<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode([
            "error" => "Error de conexión a la base de datos."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            centro,
            LPAD(almacen::TEXT, 4, '0') AS almacen,
            denom_almacen,
            grupo_art,
            material,
            desc_articulo,
            libre_utilizacion,
            umb,
            valor_lu,
            bloqueado
        FROM mb52
        ORDER BY material
    ");

    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($datos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Error de base de datos",
        "detalle" => $e->getMessage()
    ]);
}
