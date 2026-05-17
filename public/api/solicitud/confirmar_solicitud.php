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

    if (!isset($data['id']) || trim($data['id']) === '') {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "ID de solicitud no proporcionado."]);
        exit;
    }

    $id = (int) $data['id'];

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
        SELECT id FROM solicitud
        WHERE id = :id
          AND hora_confirmacion IS NULL
        FOR UPDATE
    ");
    $check->execute([':id' => $id]);

    if (!$check->fetch()) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "La solicitud no existe o ya fue confirmada."]);
        exit;
    }

    // Siguiente vale disponible
    $maxStmt   = $pdo->query("SELECT COALESCE(MAX(vale), 0) + 1 AS siguiente FROM solicitud");
    $siguiente = (int) $maxStmt->fetch(PDO::FETCH_ASSOC)['siguiente'];

    // Confirmar asignando vale y hora
    $update = $pdo->prepare("
        UPDATE solicitud
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
        "message"           => "Solicitud confirmada correctamente.",
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