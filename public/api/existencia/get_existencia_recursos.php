<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__. '/../../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        throw new Exception("Error de conexión a la base de datos");
    }

    // ==========================
    // FILTROS (DESDE MB52)
    // ==========================
    $where = [];
    $params = [];

    if (!empty($_GET['almacen'])) {
        $where[] = 'm52.almacen = :almacen';
        $params[':almacen'] = $_GET['almacen'];
    }

    if (!empty($_GET['articulo'])) {
        $where[] = 'm52.desc_articulo = :articulo';
        $params[':articulo'] = $_GET['articulo'];
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ==========================
    // CONSULTA PRINCIPAL
    // ==========================
    $sql = "
        SELECT
            m52.material,
            m52.desc_articulo,
            m52.almacen,
            (m52.libre_utilizacion + m52.bloqueado) AS cantidad,

            COALESCE(v.promedio_ventas, 0) AS promedio_ventas,

            CASE
                WHEN COALESCE(v.promedio_ventas, 0) = 0 THEN 0
                ELSE (m52.libre_utilizacion + m52.bloqueado) / v.promedio_ventas
            END AS disponibilidad

        FROM mb52 m52

        LEFT JOIN (
            SELECT
                material,
                almacen,
                SUM(ABS(cantidad)) / 6.0 AS promedio_ventas
            FROM mb51
            WHERE cantidad < 0
            GROUP BY material, almacen
        ) v
            ON v.material = m52.material
           AND v.almacen  = m52.almacen

        $whereSql

        ORDER BY
            m52.desc_articulo,
            m52.almacen
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(
        $stmt->fetchAll(PDO::FETCH_ASSOC),
        JSON_UNESCAPED_UNICODE
    );

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        'error' => 'Error obteniendo existencia de recursos',
        'detalle' => $e->getMessage()
    ]);
}