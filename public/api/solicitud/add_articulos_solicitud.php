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

    if (!isset($data['solicitud_id'], $data['articulos']) || !is_array($data['articulos'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    $solicitudId = (int)$data['solicitud_id'];
    $articulos   = $data['articulos'];

    $esAdmin = strtolower($_SESSION['rol'] ?? '') === 'administrador';

    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // Verificar que la solicitud existe, no está confirmada y (si no es admin) pertenece al usuario
    $sql = "SELECT id FROM solicitud WHERE id = :id AND hora_confirmacion IS NULL";
    if (!$esAdmin) $sql .= " AND usuario = :usuario";

    $check = $pdo->prepare($sql);
    $params = [':id' => $solicitudId];
    if (!$esAdmin) $params[':usuario'] = $_SESSION['username'];
    $check->execute($params);

    if (!$check->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada, ya confirmada o sin permisos.']);
        exit;
    }

    // Reemplazar todos los artículos de esta solicitud
    $pdo->prepare('DELETE FROM solicitud_articulo WHERE solicitud_id = :id')
        ->execute([':id' => $solicitudId]);

    $insert = $pdo->prepare('
        INSERT INTO solicitud_articulo (solicitud_id, codigo_articulo, descripcion, cantidad)
        VALUES (:solicitud_id, :codigo, :descripcion, :cantidad)
    ');

    foreach ($articulos as $art) {
        $codigo      = trim((string)($art['codigo'] ?? ''));
        $descripcion = trim((string)($art['descripcion'] ?? ''));
        $cantidad    = (int)($art['cantidad'] ?? 0);

        if (!$codigo || !$descripcion || $cantidad < 1) continue;

        $insert->execute([
            ':solicitud_id' => $solicitudId,
            ':codigo'       => $codigo,
            ':descripcion'  => $descripcion,
            ':cantidad'     => $cantidad,
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
