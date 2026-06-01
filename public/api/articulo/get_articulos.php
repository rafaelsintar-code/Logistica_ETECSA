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

    // Si el JS no pasa límite, devuelve todos los registros (el JS pagina en cliente)
    if (isset($_GET['limite'])) {
        $limite = max(1, min(500, (int)$_GET['limite']));
        $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
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

        header("X-Pagina: $pagina");
        header("X-Limite: $limite");
    } else {
        // Sin límite — carga completa para paginación en cliente
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
        ");
        $stmt->execute();
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
}
?>
