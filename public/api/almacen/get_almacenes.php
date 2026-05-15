<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo establecer conexión con la base de datos."]);
        exit;
    }

    // Pivotear las filas por nombre_pv, agrupando cada tipo en su columna
    $sql = "
        SELECT
            nombre_pv AS nombre,
            MAX(CASE WHEN tipo_almacen = 'SAP'          THEN codigo_almacen END) AS almacen_sap,
            MAX(CASE WHEN tipo_almacen = 'SIGC'         THEN codigo_almacen END) AS almacen_sigc,
            MAX(CASE WHEN tipo_almacen = 'TFA'          THEN codigo_almacen END) AS almacen_tfa,
            MAX(CASE WHEN tipo_almacen = 'Consignación' THEN codigo_almacen END) AS almacen_consig,
            MAX(CASE WHEN tipo_almacen = 'Devolución'   THEN codigo_almacen END) AS almacen_devolucion
        FROM cod_almacen
        GROUP BY nombre_pv
        ORDER BY nombre_pv ASC
    ";

    $stmt = $pdo->query($sql);
    $almacenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($almacenes);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>