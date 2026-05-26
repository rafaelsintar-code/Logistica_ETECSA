<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard.php';
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


    // Paginación — parámetros opcionales, por defecto 100 registros
    $limite = isset($_GET['limite']) ? max(1, min(500, (int)$_GET['limite'])) : 100;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina'])           : 1;
    $offset = ($pagina - 1) * $limite;

    $stmt = $pdo->prepare("
        SELECT
            codigo_articulo,
            codigo_sigc,
            descripcion,
            familia,
            precio_usd,
            precio_cup,
            acta_precio,
            garantia
        FROM articulo
        ORDER BY familia ASC
        LIMIT :limite OFFSET :offset
    ");
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cabeceras de paginación para uso futuro del frontend
    header("X-Pagina: $pagina");
    header("X-Limite: $limite");

    echo json_encode($datos);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
}
?>
