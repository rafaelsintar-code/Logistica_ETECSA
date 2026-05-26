<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
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

const MAX_INTENTOS    = 3;
const BLOQUEO_MINUTOS = 15;


// ── Rate limiting por IP ─────────────────────────────────────────────────
// Usa archivos temporales para no requerir Redis ni tabla extra.
// Máximo 20 intentos por IP en una ventana de 15 minutos.
const IP_MAX_INTENTOS  = 20;
const IP_VENTANA_SEG   = 900; // 15 minutos

$ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipHash    = hash('sha256', $ip); // no guardar IP en texto plano
$cacheFile = sys_get_temp_dir() . '/login_rl_' . $ipHash . '.json';

$ahora     = time();
$ipData    = ['intentos' => [], 'bloqueado_hasta' => 0];

if (file_exists($cacheFile)) {
    $raw = @json_decode(file_get_contents($cacheFile), true);
    if (is_array($raw)) $ipData = $raw;
}

// Limpiar intentos fuera de la ventana
$ipData['intentos'] = array_filter($ipData['intentos'], fn($t) => ($ahora - $t) < IP_VENTANA_SEG);

if (count($ipData['intentos']) >= IP_MAX_INTENTOS) {
    $espera = IP_VENTANA_SEG - ($ahora - min($ipData['intentos']));
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos desde esta dirección. Espere ' . ceil($espera / 60) . ' minuto(s).',
    ]);
    exit;
}

// Registrar este intento
$ipData['intentos'][] = $ahora;
@file_put_contents($cacheFile, json_encode($ipData), LOCK_EX);
// ────────────────────────────────────────────────────────────────────────

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
               activo, intentos_fallidos, bloqueado_hasta
        FROM usuario
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Usuario no existe — mismo mensaje que contraseña incorrecta (no revelar info)
    if (!$usuario) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    // Usuario inactivo — mismo mensaje genérico
    if (!$usuario['activo']) {
        echo json_encode(["success" => false, "message" => "Credenciales incorrectas."]);
        exit;
    }

    // Comprobar bloqueo temporal
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
        // Bloqueo expirado — resetear contadores
        $pdo->prepare("
            UPDATE usuario SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE username = :u
        ")->execute([':u' => $username]);
        $usuario['intentos_fallidos'] = 0;
    }

    // Contraseña incorrecta
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

    // Login correcto — resetear contadores y registrar acceso
    $pdo->prepare("
        UPDATE usuario
        SET intentos_fallidos = 0,
            bloqueado_hasta   = NULL,
            ultimo_acceso     = NOW()
        WHERE username = :u
    ")->execute([':u' => $username]);

    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['username']   = $usuario['username'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['rol']        = $usuario['rol'];

    // Generar token CSRF para esta sesión
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
