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
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['nombre']) || trim($data['nombre']) === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "El nombre del almacén es obligatorio."]);
        exit;
    }

    $nombre = trim($data['nombre']);

    $codigos = [
        'SAP'          => isset($data['almacen_sap'])        && $data['almacen_sap']        !== '' ? str_pad(trim($data['almacen_sap']),        4, '0', STR_PAD_LEFT) : null,
        'SIGC'         => isset($data['almacen_sigc'])       && $data['almacen_sigc']        !== '' ? str_pad(trim($data['almacen_sigc']),       4, '0', STR_PAD_LEFT) : null,
        'TFA'          => isset($data['almacen_tfa'])        && $data['almacen_tfa']         !== '' ? str_pad(trim($data['almacen_tfa']),        4, '0', STR_PAD_LEFT) : null,
        'Consignación' => isset($data['almacen_consig'])     && $data['almacen_consig']      !== '' ? str_pad(trim($data['almacen_consig']),     4, '0', STR_PAD_LEFT) : null,
        'Devolución'   => isset($data['almacen_devolucion']) && $data['almacen_devolucion']  !== '' ? str_pad(trim($data['almacen_devolucion']), 4, '0', STR_PAD_LEFT) : null,
    ];

    // Validar que al menos un código fue enviado
    if (empty(array_filter($codigos, fn($v) => $v !== null))) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Debe ingresar al menos un código de almacén."]);
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $pdo->beginTransaction();

    // Verificar que el nombre no exista ya
    $check = $pdo->prepare("SELECT COUNT(*) FROM cod_almacen WHERE nombre_pv = :nombre");
    $check->execute([':nombre' => $nombre]);
    if ($check->fetchColumn() > 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Ya existe un almacén con ese nombre."]);
        exit;
    }

    // Verificar que ningún codigo_almacen enviado ya exista
    foreach ($codigos as $tipo => $codigo) {
        if ($codigo === null) continue;
        $dup = $pdo->prepare("SELECT COUNT(*) FROM cod_almacen WHERE codigo_almacen = :codigo");
        $dup->execute([':codigo' => $codigo]);
        if ($dup->fetchColumn() > 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(["success" => false, "message" => "El código $codigo ($tipo) ya está en uso."]);
            exit;
        }
    }

    // Insertar una fila por cada código no nulo
    $stmt = $pdo->prepare("
        INSERT INTO cod_almacen (codigo_almacen, tipo_almacen, nombre_pv)
        VALUES (:codigo, :tipo, :nombre)
    ");

    foreach ($codigos as $tipo => $codigo) {
        if ($codigo === null) continue;
        $stmt->execute([
            ':codigo' => $codigo,
            ':tipo'   => $tipo,
            ':nombre' => $nombre
        ]);
    }
    $pdo->commit();

    echo json_encode(["success" => true, "message" => "Almacén agregado correctamente."]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>