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

$data = json_decode(file_get_contents("php://input"), true);

try {
    if (empty($data['username']) || empty($data['nombre']) || empty($data['correo']) || empty($data['password'])) {
        throw new Exception("Datos incompletos.");
    }

    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo electrónico no es válido.");
    }

    if (strlen($data['password']) < 8) {
        throw new Exception("La contraseña debe tener al menos 8 caracteres.");
    }

    $db  = new Database();
    $rolesValidos = ['Administrador', 'Visitante'];
    $rol = $data['rol'] ?? 'Visitante';
    if (!in_array($rol, $rolesValidos, true)) {
        throw new Exception("Rol no válido.");
    }

    $pdo = $db->getConnection();

    $chkUser = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE username = :username");
    $chkUser->execute([':username' => $data['username']]);
    if ($chkUser->fetchColumn() > 0) {
        throw new Exception("El nombre de usuario ya está en uso.");
    }

    $chkMail = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE correo = :correo");
    $chkMail->execute([':correo' => $data['correo']]);
    if ($chkMail->fetchColumn() > 0) {
        throw new Exception("El correo ya está registrado.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO usuario (username, nombre, correo, password_hash, rol, activo, creado_en)
        VALUES (:username, :nombre, :correo, :password, :rol, true, NOW())
    ");
    $stmt->execute([
        ':username' => $data['username'],
        ':nombre'   => $data['nombre'],
        ':correo'   => $data['correo'],
        ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':rol'      => $rol
    ]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
