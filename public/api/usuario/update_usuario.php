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
    if (!isset($data['username']) || $data['username'] === '') {
        throw new Exception("Username requerido.");
    }
    if (!isset($data['nombre']) || !isset($data['correo'])) {
        throw new Exception("Nombre y correo obligatorios.");
    }
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo electrónico no es válido.");
    }

    $db  = new Database();
    $pdo = $db->getConnection();

    $rolesValidos = ['administrador', 'visitante'];
    $rol = strtolower($data['rol'] ?? 'visitante');
    if (!in_array($rol, $rolesValidos, true)) {
        throw new Exception("Rol no válido.");
    }

    $activo = isset($data['activo'])
        ? filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN)
        : false;

    // Verificar si el usuario es local o LDAP para saber si acepta cambio de contraseña
    $stmtSrc = $pdo->prepare("SELECT auth_source FROM usuario WHERE username = :u");
    $stmtSrc->execute([':u' => $data['username']]);
    $row = $stmtSrc->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("Usuario no encontrado.");
    }
    $esLdap = ($row['auth_source'] === 'ldap');

    $sql = "
        UPDATE usuario
        SET nombre         = :nombre,
            correo         = :correo,
            rol            = :rol,
            activo         = :activo,
            actualizado_en = NOW()
    ";

    // Solo actualizar contraseña para usuarios locales
    if (!$esLdap && !empty($data['password'])) {
        if (strlen($data['password']) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres.");
        }
        $sql .= ", password_hash = :password";
    }

    $sql .= " WHERE username = :username";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':username', $data['username'], PDO::PARAM_STR);
    $stmt->bindValue(':nombre',   $data['nombre'],   PDO::PARAM_STR);
    $stmt->bindValue(':correo',   $data['correo'],   PDO::PARAM_STR);
    $stmt->bindValue(':rol',      $rol,              PDO::PARAM_STR);
    $stmt->bindValue(':activo',   $activo,           PDO::PARAM_BOOL);

    if (!$esLdap && !empty($data['password'])) {
        $stmt->bindValue(':password', password_hash($data['password'], PASSWORD_DEFAULT), PDO::PARAM_STR);
    }

    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception("Usuario no encontrado.");
    }

    echo json_encode(["success" => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error de base de datos."]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
