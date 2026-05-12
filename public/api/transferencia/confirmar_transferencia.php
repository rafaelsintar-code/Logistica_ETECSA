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

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || !isset($data['articulos']) || !is_array($data['articulos'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
        exit;
    }

    $id        = (int) $data['id'];
    $articulos = $data['articulos']; // [{codigo, cantidad}, ...]

    if (count($articulos) === 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Debe incluir al menos un artículo."]);
        exit;
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $pdo->beginTransaction();

    // Verificar que existe y no está confirmada — FOR UPDATE bloquea la fila
    $check = $pdo->prepare("
        SELECT id, almacen_origen, almacen_destino
        FROM transferencia
        WHERE id = :id
          AND hora_confirmacion IS NULL
        FOR UPDATE
    ");
    $check->execute([':id' => $id]);
    $transferencia = $check->fetch(PDO::FETCH_ASSOC);

    if (!$transferencia) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "La transferencia no existe o ya fue confirmada."]);
        exit;
    }

    $almacen_origen  = $transferencia['almacen_origen'];
    $almacen_destino = $transferencia['almacen_destino'];

    // Verificar stock suficiente y aplicar movimientos
    $stmtCheck = $pdo->prepare("
        SELECT libre_utilizacion
        FROM mb52
        WHERE almacen  = :almacen
          AND material = :material
        FOR UPDATE
    ");

    $stmtDescontar = $pdo->prepare("
        UPDATE mb52
        SET libre_utilizacion = libre_utilizacion - :cantidad
        WHERE almacen  = :almacen
          AND material = :material
    ");

    $stmtSumar = $pdo->prepare("
        UPDATE mb52
        SET libre_utilizacion = libre_utilizacion + :cantidad
        WHERE almacen  = :almacen
          AND material = :material
    ");

    foreach ($articulos as $art) {
        $codigo   = $art['codigo'];
        $cantidad = (int) $art['cantidad'];

        if ($cantidad < 1) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "message" => "La cantidad debe ser mayor a 0."]);
            exit;
        }

        // Verificar stock en origen
        $stmtCheck->execute([
            ':almacen'  => $almacen_origen,
            ':material' => $codigo
        ]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            echo json_encode([
                "success" => false,
                "message" => "El artículo $codigo no existe en el almacén origen."
            ]);
            exit;
        }

        if ((int)$row['libre_utilizacion'] < $cantidad) {
            $pdo->rollBack();
            echo json_encode([
                "success" => false,
                "message" => "Stock insuficiente para el artículo $codigo. Disponible: {$row['libre_utilizacion']}."
            ]);
            exit;
        }

        // Descontar del origen
        $stmtDescontar->execute([
            ':cantidad' => $cantidad,
            ':almacen'  => $almacen_origen,
            ':material' => $codigo
        ]);

        // Sumar al destino (si no existe la fila en destino, no se suma — depende del negocio)
        $stmtSumar->execute([
            ':cantidad' => $cantidad,
            ':almacen'  => $almacen_destino,
            ':material' => $codigo
        ]);
    }

    // Siguiente vale disponible
    $maxStmt   = $pdo->query("SELECT COALESCE(MAX(vale), 0) + 1 AS siguiente FROM transferencia");
    $siguiente = (int) $maxStmt->fetch(PDO::FETCH_ASSOC)['siguiente'];

    // Confirmar asignando vale y hora
    $update = $pdo->prepare("
        UPDATE transferencia
        SET vale              = :vale,
            hora_confirmacion = NOW()
        WHERE id = :id
        RETURNING hora_confirmacion
    ");
    $update->execute([':vale' => $siguiente, ':id' => $id]);
    $row = $update->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    echo json_encode([
        "success"           => true,
        "message"           => "Transferencia confirmada correctamente.",
        "vale"              => $siguiente,
        "hora_confirmacion" => $row['hora_confirmacion']
    ]);

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