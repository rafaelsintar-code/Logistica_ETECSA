<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__. '/../config/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {

    $db  = new Database();
    $pdo = $db->getConnection();

    $where = [];
    $params = [];

    if (!empty($_GET['almacen'])) {
        if (!ctype_digit(ltrim(trim($_GET['almacen']), '0') ?: '0')) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro almacen inválido.']);
            exit;
        }
        $where[]            = 'm52.almacen = :almacen';
        $params[':almacen'] = str_pad(trim($_GET['almacen']), 4, '0', STR_PAD_LEFT);
    }

    if (!empty($_GET['articulo'])) {
        $where[] = 'm52.material = :articulo';
        $params[':articulo'] = $_GET['articulo'];
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            m52.material,
            m52.desc_articulo,
            LPAD(m52.almacen::TEXT, 4, '0') AS almacen,
            ca.nombre_pv,
            (m52.libre_utilizacion + m52.bloqueado) AS cantidad,

            COALESCE(v.promedio_ventas, 0) AS promedio_ventas,

            CASE
                WHEN COALESCE(v.promedio_ventas, 0) = 0 THEN 0
                ELSE (m52.libre_utilizacion + m52.bloqueado) / v.promedio_ventas
            END AS disponibilidad

        FROM mb52 m52

        LEFT JOIN cod_almacen ca
            ON ca.codigo_almacen = m52.almacen

        LEFT JOIN (
            SELECT
                material,
                almacen,
                SUM(ABS(cantidad)) / 6.0 AS promedio_ventas
            FROM mb51
            WHERE cantidad < 0
              AND clase_mov LIKE 'Y%'
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
        'detalle' => 'Consulte los logs del servidor.'
    ]);
}