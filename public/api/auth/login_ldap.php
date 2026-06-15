<?php
/**
 * login_ldap.php — Autenticación híbrida LDAP/AD + autorización local (PostgreSQL)
 *
 * Flujo:
 *   1. Valida la identidad del usuario contra Active Directory (LDAP).
 *   2. Busca el usuario en la tabla `usuario` de PostgreSQL por su username.
 *      - Si no existe, se crea automáticamente:
 *          - username 'Administrador' → rol 'administrador'
 *          - resto → rol 'visitante'
 *      - Si existe pero está inactivo, se rechaza el acceso.
 *   3. El rol y el estado activo se leen SIEMPRE desde PostgreSQL, nunca desde AD.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ldap_auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params([
    'lifetime' => 28800,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once __DIR__ . '/../config/csrf.php';

// ── Rate limiting por IP ──────────────────────────────────────────────────
const IP_MAX_INTENTOS = 20;
const IP_VENTANA_SEG  = 900;

$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipHash = hash('sha256', $ip);

try {
    $dbRl  = new Database();
    $pdoRl = $dbRl->getConnection();

    $pdoRl->prepare("
        DELETE FROM login_rate_limit
        WHERE ip_hash = :h AND ventana_inicio < NOW() - INTERVAL '15 minutes'
    ")->execute([':h' => $ipHash]);

    $stmtRl = $pdoRl->prepare("
        INSERT INTO login_rate_limit (ip_hash, intentos, ventana_inicio)
        VALUES (:h, 1, NOW())
        ON CONFLICT (ip_hash) DO UPDATE
            SET intentos = login_rate_limit.intentos + 1
        RETURNING intentos,
                  EXTRACT(EPOCH FROM (NOW() - ventana_inicio))::INTEGER AS segundos_transcurridos
    ");
    $stmtRl->execute([':h' => $ipHash]);
    $rl = $stmtRl->fetch(PDO::FETCH_ASSOC);

    if ($rl && (int)$rl['intentos'] > IP_MAX_INTENTOS) {
        $espera = IP_VENTANA_SEG - (int)$rl['segundos_transcurridos'];
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Demasiados intentos desde esta dirección. Espere ' . ceil(max($espera, 60) / 60) . ' minuto(s).',
        ]);
        exit;
    }
} catch (PDOException $e) {
    // Rate limiting omitido si la tabla no existe
}
// ─────────────────────────────────────────────────────────────────────────

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

    // ── Paso 1: Autenticación contra Active Directory ─────────────────────
    $ldap   = new LdapAuth();
    $adUser = $ldap->authenticate($username, $password);

    // ── Paso 2: Buscar o crear usuario en PostgreSQL ──────────────────────
    $db  = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        SELECT id_usuario, username, nombre, correo, rol, activo
        FROM usuario
        WHERE username = :username
    ");
    $stmt->execute([':username' => $adUser['username']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Auto-provisioning: primera vez que este usuario de AD inicia sesión.
        // 'Administrador' de AD obtiene rol administrador; el resto, visitante.
        $nombre     = $adUser['nombre'] ?: $adUser['username'];
        $correo     = $adUser['correo'] ?: ($adUser['username'] . '@empresa.local');
        $fakeHash   = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $rolInicial = (strtolower($adUser['username']) === 'administrador') ? 'administrador' : 'visitante';

        $ins = $pdo->prepare("
            INSERT INTO usuario (username, nombre, correo, password_hash, rol, activo, auth_source)
            VALUES (:u, :n, :c, :ph, :rol, true, 'ldap')
            RETURNING id_usuario, username, nombre, correo, rol, activo
        ");
        $ins->execute([
            ':u'   => $adUser['username'],
            ':n'   => $nombre,
            ':c'   => $correo,
            ':ph'  => $fakeHash,
            ':rol' => $rolInicial,
        ]);
        $usuario = $ins->fetch(PDO::FETCH_ASSOC);
    }

    // ── Paso 3: Verificar estado activo ──────────────────────────────────
    if (!$usuario['activo']) {
        echo json_encode([
            "success" => false,
            "message" => "Tu cuenta ha sido desactivada. Contacta al administrador."
        ]);
        exit;
    }

    // ── Paso 4: Actualizar datos desde AD y crear sesión ─────────────────
    $pdo->prepare("
        UPDATE usuario
        SET ultimo_acceso = NOW(),
            nombre        = :nombre,
            correo        = :correo
        WHERE username = :u
    ")->execute([
        ':nombre' => $adUser['nombre'] ?: $usuario['nombre'],
        ':correo' => $adUser['correo'] ?: $usuario['correo'],
        ':u'      => $usuario['username'],
    ]);

    session_regenerate_id(true);
    $_SESSION['id_usuario']    = $usuario['id_usuario'];
    $_SESSION['username']      = $usuario['username'];
    $_SESSION['nombre']        = $usuario['nombre'];
    $_SESSION['rol']           = $usuario['rol'];
    $_SESSION['last_activity'] = time();

    $csrfToken = csrf_get_token();

    $roles    = [
        'administrador' => 'admin/index.html',
        'visitante'     => 'visitante/index.html',
    ];
    $redirect = $roles[strtolower($usuario['rol'])] ?? 'visitante/index.html';

    echo json_encode([
        "success"    => true,
        "message"    => "Bienvenido, " . $usuario['nombre'] . ".",
        "redirect"   => $redirect,
        "csrf_token" => $csrfToken,
    ]);

} catch (InvalidArgumentException $e) {
    echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);

} catch (RuntimeException $e) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "message" => "No se pudo contactar el servidor de autenticación. Intente más tarde."
    ]);
    error_log("[LDAP ERROR] " . $e->getMessage());

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
    error_log("[DB ERROR] " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno del servidor."]);
    error_log("[ERROR] " . $e->getMessage());
}
?>
