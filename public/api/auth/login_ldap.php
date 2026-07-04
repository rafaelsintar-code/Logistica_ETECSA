<?php
/**
 * login_ldap.php — Autenticación híbrida LDAP/AD + autorización local (PostgreSQL)
 *
 * Flujo:
 *   1. Valida la identidad del usuario contra Active Directory (LDAP).
 *   2. Busca el usuario en la tabla `usuario` de PostgreSQL por su username.
 *      - Si no existe, se crea automáticamente:
 *          - Miembro del grupo admin_group (ldap_config.ini) → rol 'administrador'
 *          - Resto → rol 'visitante'
 *      - Si existe pero está inactivo, se rechaza el acceso.
 *   3. El rol se re-evalúa en CADA login según la membresía actual al grupo
 *      admin_group en AD, y se sincroniza en PostgreSQL si cambió. El estado
 *      activo/inactivo, en cambio, se gestiona solo localmente en PostgreSQL.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ldap_auth.php';
require_once __DIR__ . '/../config/session_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/csrf.php';

// ── Rate limiting por IP ──────────────────────────────────────────────────
const IP_MAX_INTENTOS = 20;
const IP_VENTANA_SEG  = 900;

verificar_rate_limit_ip();
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

    // ── Leer configuración LDAP para grupo admin y dominio de correo ──────
    $ldapConfigFile = __DIR__ . '/../../../config/ldap_config.ini';
    $ldapCfg        = parse_ini_file($ldapConfigFile, true);
    $adminGroup     = $ldapCfg['ldap']['admin_group'] ?? 'grupo_administradores';
    $mailDomain     = $ldapCfg['ldap']['mail_domain']  ?? '@empresa.local';

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
        $nombre   = $adUser['nombre'] ?: $adUser['username'];
        $correo   = $adUser['correo'] ?: ($adUser['username'] . $mailDomain);
        $fakeHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        // Rol según membresía al grupo administrador en AD
        $esAdmin    = in_array($adminGroup, $adUser['memberOf'], true);
        $rolInicial = $esAdmin ? 'administrador' : 'visitante';

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

    // ── Paso 4: Re-sincronizar rol desde AD y actualizar datos ────────────
    // El rol puede haber cambiado en AD desde el último login; lo re-evaluamos.
    $rolActualizado = in_array($adminGroup, $adUser['memberOf'], true) ? 'administrador' : 'visitante';
    if ($rolActualizado !== $usuario['rol']) {
        $usuario['rol'] = $rolActualizado;
    }

    $pdo->prepare("
        UPDATE usuario
        SET ultimo_acceso = NOW(),
            nombre        = :nombre,
            correo        = :correo,
            rol           = :rol
        WHERE username = :u
    ")->execute([
        ':nombre' => $adUser['nombre'] ?: $usuario['nombre'],
        ':correo' => $adUser['correo'] ?: $usuario['correo'],
        ':rol'    => $rolActualizado,
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
