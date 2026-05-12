<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset($data['codigo_almacen'], $data['tipo_almacen'], $data['nombre_pv']) ||
        trim($data['codigo_almacen']) === '' ||
        trim($data['tipo_almacen'])   === '' ||
        trim($data['nombre_pv'])      === ''
    ) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos del almacén."]);
        exit;
    }

    $codigo_almacen = $data['codigo_almacen'];
    $tipo_almacen   = trim($data['tipo_almacen']);
    $nombre_pv      = trim($data['nombre_pv']);
    $usuario        = $_SESSION['username'];

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO solicitud (codigo_almacen, tipo_almacen, nombre_pv, usuario)
        VALUES (:codigo_almacen, :tipo_almacen, :nombre_pv, :usuario)
        RETURNING id, usuario, hora_creacion
    ");

    $stmt->execute([
        ':codigo_almacen' => $codigo_almacen,
        ':tipo_almacen'   => $tipo_almacen,
        ':nombre_pv'      => $nombre_pv,
        ':usuario'        => $usuario
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success"       => true,
        "message"       => "Solicitud creada correctamente.",
        "id"            => $row['id'],
        "usuario"       => $row['usuario'],
        "hora_creacion" => $row['hora_creacion']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>