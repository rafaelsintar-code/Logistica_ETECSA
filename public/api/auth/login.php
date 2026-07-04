<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/csrf.php';

const MAX_INTENTOS    = 3;
const BLOQUEO_MINUTOS = 15;

const IP_MAX_INTENTOS = 20;
const IP_VENTANA_SEG  = 900;

verificar_rate_limit_ip();

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

    $stmt = $pdo->prepare("
        SELECT id_usuario, username, nombre, password_hash, rol,
               activo, intentos_fallidos, bloqueado_hasta, auth_source
        FROM usuario
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    // Usuarios LDAP no pueden autenticarse por esta ruta
    if (($usuario['auth_source'] ?? 'local') === 'ldap') {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    if (!$usuario['activo']) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    if (!empty($usuario['bloqueado_hasta'])) {
        $bloqueadoHasta = new DateTime($usuario['bloqueado_hasta']);
        $ahora          = new DateTime();
        if ($ahora < $bloqueadoHasta) {
            $restantes = (int) ceil(($bloqueadoHasta->getTimestamp() - $ahora->getTimestamp()) / 60);
            echo json_encode([
                "success" => false,
                "message" => "Cuenta bloqueada por demasiados intentos fallidos. Intente en {$restantes} minuto(s)."
            ]);
            exit;
        }
        $pdo->prepare("
            UPDATE usuario SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE username = :u
        ")->execute([':u' => $username]);
        $usuario['intentos_fallidos'] = 0;
    }

    if (!password_verify($password, $usuario['password_hash'])) {
        $intentos = (int)$usuario['intentos_fallidos'] + 1;

        if ($intentos >= MAX_INTENTOS) {
            $pdo->prepare("
                UPDATE usuario
                SET intentos_fallidos = :i,
                    bloqueado_hasta   = NOW() + (:minutos || ' minutes')::INTERVAL
                WHERE username = :u
            ")->execute([':i' => $intentos, ':minutos' => BLOQUEO_MINUTOS, ':u' => $username]);

            echo json_encode([
                "success" => false,
                "message" => "Cuenta bloqueada por " . BLOQUEO_MINUTOS . " minutos tras " . MAX_INTENTOS . " intentos fallidos."
            ]);
        } else {
            $restantes = MAX_INTENTOS - $intentos;
            $pdo->prepare("
                UPDATE usuario SET intentos_fallidos = :i WHERE username = :u
            ")->execute([':i' => $intentos, ':u' => $username]);

            echo json_encode([
                "success" => false,
                "message" => "Credenciales incorrectas. {$restantes} intento(s) restante(s) antes del bloqueo."
            ]);
        }
        exit;
    }

    $pdo->prepare("
        UPDATE usuario
        SET intentos_fallidos = 0,
            bloqueado_hasta   = NULL,
            ultimo_acceso     = NOW()
        WHERE username = :u
    ")->execute([':u' => $username]);

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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno del servidor."]);
}
?>
