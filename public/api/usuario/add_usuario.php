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
    // ── Validaciones comunes ──────────────────────────────────────────────
    if (empty($data['username']) || empty($data['nombre']) || empty($data['correo'])) {
        throw new Exception("Datos incompletos.");
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

    // ── Determinar origen de autenticación ────────────────────────────────
    // El formulario puede enviar auth_source='ldap' si el admin quiere
    // pre-registrar un usuario de AD; de lo contrario se asume 'local'.
    $authSource = ($data['auth_source'] ?? 'local') === 'ldap' ? 'ldap' : 'local';

    // Para usuarios locales la contraseña es obligatoria
    if ($authSource === 'local') {
        if (empty($data['password'])) {
            throw new Exception("La contraseña es obligatoria para usuarios locales.");
        }
        if (strlen($data['password']) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres.");
        }
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    } else {
        // Usuarios LDAP: hash aleatorio — la contraseña real está en AD
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    }

    // ── Unicidad ──────────────────────────────────────────────────────────
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
        INSERT INTO usuario (username, nombre, correo, password_hash, rol, activo, auth_source, creado_en)
        VALUES (:username, :nombre, :correo, :password, :rol, true, :auth_source, NOW())
    ");
    $stmt->execute([
        ':username'    => $data['username'],
        ':nombre'      => $data['nombre'],
        ':correo'      => $data['correo'],
        ':password'    => $passwordHash,
        ':rol'         => $rol,
        ':auth_source' => $authSource,
    ]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
