<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Leer JSON recibido
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset($data['codigo_articulo']) ||
        !isset($data['descripcion']) ||
        !isset($data['familia']) ||
        !isset($data['acta_precio']) ||
        !isset($data['garantia'])
    ) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
        exit;
    }

    $codigo_articulo = trim($data['codigo_articulo']);
    $codigo_sigc     = isset($data['codigo_sigc']) && $data['codigo_sigc'] !== '' ? trim($data['codigo_sigc']) : null;
    $descripcion     = trim($data['descripcion']);
    $familia         = trim($data['familia']);
    $precio_usd      = isset($data['precio_usd']) && $data['precio_usd'] !== '' ? trim($data['precio_usd']) : null;
    $precio_cup      = isset($data['precio_cup']) && $data['precio_cup'] !== '' ? trim($data['precio_cup']) : null;
    $acta_precio     = trim($data['acta_precio']);
    $garantia        = trim($data['garantia']);

    // Conexión
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    // Verificar duplicado de descripción (excluyendo este artículo)
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM articulo 
        WHERE descripcion = :descripcion 
          AND codigo_articulo <> :codigo
    ");
    $check->execute([
        ':descripcion' => $descripcion,
        ':codigo' => $codigo_articulo
    ]);

    if ($check->fetchColumn() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Ya existe otro artículo con esa descripción."
        ]);
        exit;
    }

    // Actualizar artículo
    $stmt = $pdo->prepare("
        UPDATE articulo
        SET
            codigo_sigc = :codigo_sigc,
            descripcion = :descripcion,
            familia = :familia,
            precio_usd = :precio_usd,
            precio_cup = :precio_cup,
            acta_precio = :acta_precio,
            garantia = :garantia
        WHERE codigo_articulo = :codigo_articulo
    ");

    $stmt->execute([
        ':codigo_sigc'     => $codigo_sigc,
        ':descripcion'     => $descripcion,
        ':familia'         => $familia,
        ':precio_usd'      => $precio_usd,
        ':precio_cup'      => $precio_cup,
        ':acta_precio'     => $acta_precio,
        ':garantia'        => $garantia,
        ':codigo_articulo' => $codigo_articulo
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Artículo actualizado correctamente."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error en base de datos: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
