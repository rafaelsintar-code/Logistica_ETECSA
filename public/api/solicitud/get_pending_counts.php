<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard_admin.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmtSol = $pdo->query("SELECT COUNT(*) FROM solicitud WHERE hora_confirmacion IS NULL");
    $pendingSolicitudes = (int) $stmtSol->fetchColumn();

    $stmtTrans = $pdo->query("SELECT COUNT(*) FROM transferencia WHERE hora_confirmacion IS NULL");
    $pendingTransferencias = (int) $stmtTrans->fetchColumn();

    echo json_encode([
        'success'          => true,
        'solicitudes'      => $pendingSolicitudes,
        'transferencias'   => $pendingTransferencias,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}
?>
