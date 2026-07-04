<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard_admin.php';

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

    // ── Paginación ───────────────────────────────────────────────────────────
    $limite = isset($_GET['limite']) ? max(1, min(500, (int)$_GET['limite'])) : 100;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina'])           : 1;
    $offset = ($pagina - 1) * $limite;

    // ── Filtro ───────────────────────────────────────────────────────────────
    $filtro = isset($_GET['filtro']) ? trim($_GET['filtro']) : '';

    $where  = '';
    $params = [];

    if ($filtro !== '') {
        $where    = "WHERE material::TEXT                  ILIKE :filtro
                  OR desc_articulo                        ILIKE :filtro
                  OR LPAD(almacen::TEXT, 4, '0')          ILIKE :filtro
                  OR denom_almacen                        ILIKE :filtro
                  OR grupo_art::TEXT                      ILIKE :filtro";
        $params[':filtro'] = '%' . $filtro . '%';
    }

    // ── Total de registros ───────────────────────────────────────────────────
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM mb52 $where");
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetchColumn();

    // ── Datos de la página solicitada ────────────────────────────────────────
    $stmtDatos = $pdo->prepare("
        SELECT
            centro,
            LPAD(almacen::TEXT, 4, '0') AS almacen,
            denom_almacen,
            grupo_art,
            material,
            desc_articulo,
            libre_utilizacion,
            umb,
            valor_lu,
            bloqueado
        FROM mb52
        $where
        ORDER BY material
        LIMIT :limite OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmtDatos->bindValue($k, $v);
    }
    $stmtDatos->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtDatos->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtDatos->execute();

    echo json_encode([
        'total'  => $total,
        'pagina' => $pagina,
        'limite' => $limite,
        'datos'  => $stmtDatos->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}
?>
