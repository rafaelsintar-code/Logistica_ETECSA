<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
session_start();

const MAX_INTENTOS    = 3;
const BLOQUEO_MINUTOS = 15;

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
            // Bloquear cuenta
            $pdo->prepare("
                UPDATE usuario
                SET intentos_fallidos = :i,
                    bloqueado_hasta   = NOW() + INTERVAL '" . BLOQUEO_MINUTOS . " minutes'
                WHERE username = :u
            ")->execute([':i' => $intentos, ':u' => $username]);

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

    $roles    = ['administrador' => 'admin.html'];
    $redirect = $roles[strtolower($usuario['rol'])] ?? 'admin.html';

    echo json_encode([
        "success"  => true,
        "message"  => "Bienvenido, " . $usuario['nombre'] . ".",
        "redirect" => $redirect
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
