<?php
/**
 * session_config.php — Configuración centralizada de sesión.
 *
 * Incluir ANTES de session_start() en cualquier archivo que inicie sesión.
 * Define la cookie con duración de 8 horas y deja que el guard controle
 * la inactividad por software (SESSION_TIMEOUT).
 */

const SESSION_LIFETIME = 28800; // 8 horas — duración de la cookie
const SESSION_TIMEOUT  = 300;   // 5 minutos — cierre por inactividad

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);

/**
 * Verifica que haya una sesión activa y que no haya expirado por inactividad.
 * Debe llamarse después de session_start().
 *
 * En endpoints AJAX (detectados por es_ajax_request(), definida en csrf.php)
 * responde 401 JSON. En páginas normales redirige al login.
 *
 * @param bool $soloJson  Si true, siempre responde JSON (usado por session_check.php).
 */
function verificar_sesion(bool $soloJson = false): void {
    if (empty($_SESSION['id_usuario'])) {
        if ($soloJson || es_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Sesión no iniciada."]);
            exit;
        }
        header("Location: " . app_base_url() . "/pages/login.html");
        exit;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        if ($soloJson || es_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Sesión expirada por inactividad."]);
            exit;
        }
        header("Location: " . app_base_url() . "/pages/login.html");
        exit;
    }

    $_SESSION['last_activity'] = time();
}
