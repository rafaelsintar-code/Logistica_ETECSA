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

    $esAdmin  = strtolower($_SESSION['rol'] ?? '') === 'administrador';
    $username = $_SESSION['username'];

    // Paginación — parámetros opcionales, por defecto 100 registros
    $limite = isset($_GET['limite']) ? max(1, min(500, (int)$_GET['limite'])) : 100;
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina'])           : 1;
    $offset = ($pagina - 1) * $limite;

    // El administrador ve todas; el visitante solo las suyas
    if ($esAdmin) {
        $stmt = $pdo->prepare("
            SELECT
                id,
                LPAD(almacen_origen::TEXT, 4, '0')  AS almacen_origen,
                denom_origen,
                LPAD(almacen_destino::TEXT, 4, '0') AS almacen_destino,
                denom_destino,
                usuario,
                hora_creacion,
                hora_confirmacion,
                vale
            FROM transferencia
            ORDER BY hora_creacion DESC
            LIMIT :limite OFFSET :offset
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT
                id,
                LPAD(almacen_origen::TEXT, 4, '0')  AS almacen_origen,
                denom_origen,
                LPAD(almacen_destino::TEXT, 4, '0') AS almacen_destino,
                denom_destino,
                usuario,
                hora_creacion,
                hora_confirmacion,
                vale
            FROM transferencia
            WHERE usuario = :usuario
            ORDER BY hora_creacion DESC
            LIMIT :limite OFFSET :offset
        ");
        $stmt->bindValue(':usuario', $username, PDO::PARAM_STR);
        $stmt->bindValue(':limite',  $limite,   PDO::PARAM_INT);
        $stmt->bindValue(':offset',  $offset,   PDO::PARAM_INT);
        $stmt->execute();
    }

    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cabeceras de paginación para uso futuro del frontend
    header("X-Pagina: $pagina");
    header("X-Limite: $limite");

    // Meta de sesión para que el frontend adapte la UI sin lógica en el cliente
    header("X-Usuario-Rol: "      . ($esAdmin ? 'administrador' : 'visitante'));
    header("X-Usuario-Username: " . $username);

    echo json_encode($datos);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
}
?>
