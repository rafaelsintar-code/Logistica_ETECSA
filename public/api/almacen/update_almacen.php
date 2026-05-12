<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['nombre']) || trim($data['nombre']) === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Falta el nombre del almacén."]);
        exit;
    }

    $nombre = trim($data['nombre']);

    $codigos = [
        'SAP'          => isset($data['almacen_sap'])        && $data['almacen_sap']        !== '' ? $data['almacen_sap']        : null,
        'SIGC'         => isset($data['almacen_sigc'])       && $data['almacen_sigc']        !== '' ? $data['almacen_sigc']       : null,
        'TFA'          => isset($data['almacen_tfa'])        && $data['almacen_tfa']         !== '' ? $data['almacen_tfa']        : null,
        'Consignación' => isset($data['almacen_consig'])     && $data['almacen_consig']      !== '' ? $data['almacen_consig']     : null,
        'Devolución'   => isset($data['almacen_devolucion']) && $data['almacen_devolucion']  !== '' ? $data['almacen_devolucion'] : null,
    ];

    $database = new Database();
    $pdo = $database->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    // Verificar que el almacén existe
    $check = $pdo->prepare("SELECT COUNT(*) FROM cod_almacen WHERE nombre_pv = :nombre");
    $check->execute([':nombre' => $nombre]);
    if ($check->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "No se encontró el almacén especificado."]);
        exit;
    }

    // Verificar que los códigos nuevos no colisionen con OTROS nombres
    foreach ($codigos as $tipo => $codigo) {
        if ($codigo === null) continue;
        $dup = $pdo->prepare("
            SELECT COUNT(*) FROM cod_almacen 
            WHERE codigo_almacen = :codigo AND nombre_pv != :nombre
        ");
        $dup->execute([':codigo' => $codigo, ':nombre' => $nombre]);
        if ($dup->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(["success" => false, "message" => "El código $codigo ($tipo) ya está en uso por otro almacén."]);
            exit;
        }
    }

    $pdo->beginTransaction();

    // Borrar todas las filas actuales del almacén y reinsertar las nuevas
    $del = $pdo->prepare("DELETE FROM cod_almacen WHERE nombre_pv = :nombre");
    $del->execute([':nombre' => $nombre]);

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

    echo json_encode(["success" => true, "message" => "Almacén actualizado correctamente."]);

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