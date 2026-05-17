<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset($data['almacen_origen'], $data['denom_origen'], $data['almacen_destino'], $data['denom_destino']) ||
        trim($data['almacen_origen'])  === '' ||
        trim($data['denom_origen'])    === '' ||
        trim($data['almacen_destino']) === '' ||
        trim($data['denom_destino'])   === ''
    ) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos de los almacenes."]);
        exit;
    }

    if ($data['almacen_origen'] === $data['almacen_destino']) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "El almacén origen y destino no pueden ser el mismo."]);
        exit;
    }

    $almacen_origen  = $data['almacen_origen'];
    $denom_origen    = trim($data['denom_origen']);
    $almacen_destino = $data['almacen_destino'];
    $denom_destino   = trim($data['denom_destino']);
    $usuario         = $_SESSION['username'];

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO transferencia (almacen_origen, denom_origen, almacen_destino, denom_destino, usuario)
        VALUES (:almacen_origen, :denom_origen, :almacen_destino, :denom_destino, :usuario)
        RETURNING id, usuario, hora_creacion
    ");

    $stmt->execute([
        ':almacen_origen'  => $almacen_origen,
        ':denom_origen'    => $denom_origen,
        ':almacen_destino' => $almacen_destino,
        ':denom_destino'   => $denom_destino,
        ':usuario'         => $usuario
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success"       => true,
        "message"       => "Transferencia creada correctamente.",
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