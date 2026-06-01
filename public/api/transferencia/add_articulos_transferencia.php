<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['transferencia_id'], $data['articulos']) || !is_array($data['articulos'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    $transferenciaId = (int)$data['transferencia_id'];
    $articulos       = $data['articulos'];

    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // Verificar que la transferencia existe y no está confirmada
    $check = $pdo->prepare("
        SELECT id FROM transferencia
        WHERE id = :id AND hora_confirmacion IS NULL
    ");
    $check->execute([':id' => $transferenciaId]);
    if (!$check->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transferencia no encontrada o ya confirmada.']);
        exit;
    }

    // Reemplazar todos los artículos de esta transferencia
    $pdo->prepare('DELETE FROM transferencia_articulo WHERE transferencia_id = :id')
        ->execute([':id' => $transferenciaId]);

    $insert = $pdo->prepare('
        INSERT INTO transferencia_articulo (transferencia_id, codigo_articulo, descripcion, cantidad)
        VALUES (:transferencia_id, :codigo, :descripcion, :cantidad)
    ');

    foreach ($articulos as $art) {
        $codigo      = trim((string)($art['codigo'] ?? ''));
        $descripcion = trim((string)($art['descripcion'] ?? ''));
        $cantidad    = (int)($art['cantidad'] ?? 0);

        if (!$codigo || !$descripcion || $cantidad < 1) continue;

        $insert->execute([
            ':transferencia_id' => $transferenciaId,
            ':codigo'           => $codigo,
            ':descripcion'      => $descripcion,
            ':cantidad'         => $cantidad,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}
?>
