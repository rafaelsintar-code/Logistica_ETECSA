<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_guard_admin.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

try {
    $db = new Database();
    $pdo = $db->getConnection();


    // Leer JSON recibido
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar campos obligatorios
    if (
        !isset($data['codigo_articulo']) ||
        !isset($data['descripcion']) ||
        !isset($data['familia']) ||
        !isset($data['acta_precio']) ||
        !isset($data['garantia'])
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Faltan campos obligatorios."
        ]);
        exit;
    }

    // Normalizar datos
    $codigo_articulo = trim($data['codigo_articulo']);
    $codigo_sigc     = isset($data['codigo_sigc']) && $data['codigo_sigc'] !== ''
                        ? trim($data['codigo_sigc'])
                        : null;

    $descripcion  = trim($data['descripcion']);
    $familia      = trim($data['familia']);
    $precio_usd   = isset($data['precio_usd']) && $data['precio_usd'] !== ''
                        ? trim($data['precio_usd'])
                        : null;

    $precio_cup   = isset($data['precio_cup']) && $data['precio_cup'] !== ''
                        ? trim($data['precio_cup'])
                        : null;

    $acta_precio = trim($data['acta_precio']);
    $garantia    = trim($data['garantia']);

    // Validar que los campos obligatorios no sean cadena vacía tras el trim
    if ($codigo_articulo === '' || $descripcion === '' || $familia === '' ||
        $acta_precio === '' || $garantia === '') {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Los campos obligatorios no pueden estar vacíos."
        ]);
        exit;
    }

    // Verificar duplicado por PK
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM articulo 
        WHERE codigo_articulo = :codigo
    ");
    $check->execute([':codigo' => $codigo_articulo]);

    if ($check->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "El código del artículo ya existe."
        ]);
        exit;
    }

    // Insertar artículo
    $stmt = $pdo->prepare("
        INSERT INTO articulo (
            codigo_articulo,
            codigo_sigc,
            descripcion,
            familia,
            precio_usd,
            precio_cup,
            acta_precio,
            garantia
        ) VALUES (
            :codigo_articulo,
            :codigo_sigc,
            :descripcion,
            :familia,
            :precio_usd,
            :precio_cup,
            :acta_precio,
            :garantia
        )
    ");

    $stmt->execute([
        ':codigo_articulo' => $codigo_articulo,
        ':codigo_sigc'     => $codigo_sigc,
        ':descripcion'     => $descripcion,
        ':familia'         => $familia,
        ':precio_usd'      => $precio_usd,
        ':precio_cup'      => $precio_cup,
        ':acta_precio'     => $acta_precio,
        ':garantia'        => $garantia
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Artículo agregado correctamente."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de base de datos."
    ]);
}
?>
