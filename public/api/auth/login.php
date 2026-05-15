<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
session_start();

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (
        !isset($data['username'], $data['password']) ||
        trim($data['username']) === '' ||
        trim($data['password']) === ''
    ) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Complete todos los campos."]);
        exit;
    }

    $username = trim($data['username']);
    $password = $data['password'];

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id_usuario, username, nombre, password_hash, rol, activo
        FROM usuario
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Usuario no existe
    if (!$usuario) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    // Usuario inactivo
    if (!$usuario['activo']) {
        echo json_encode(["success" => false, "message" => "Usuario desactivado. Contacte al administrador."]);
        exit;
    }

    // Contraseña incorrecta
    if (!password_verify($password, $usuario['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    // Login correcto — guardar sesión
    session_regenerate_id(true);

    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['username']   = $usuario['username'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['rol']        = $usuario['rol'];

    // Redirigir según rol
    $roles    = ['administrador' => 'admin.html'];
    $redirect = $roles[strtolower($usuario['rol'])] ?? 'admin.html';
    
    echo json_encode([
        "success"  => true,
        "message"  => "Bienvenido, " . $usuario['nombre'] . ".",
        "redirect" => $redirect
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>