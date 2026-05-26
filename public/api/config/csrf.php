<?php
// Genera el token CSRF si no existe en sesión y lo devuelve.
// Debe llamarse después de session_start().
function csrf_get_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Valida el token CSRF.
// Lo busca primero en la cabecera X-CSRF-Token (fetch/JSON),
// luego en el campo POST csrf_token (multipart/form-data).
// Termina con 403 JSON si falla.
function csrf_verify(): void {
    $enviado  = $_SERVER['HTTP_X_CSRF_TOKEN']
             ?? $_POST['csrf_token']
             ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    if (!$esperado || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
        exit;
    }
}

/**
 * Devuelve la URL base de la aplicación (hasta public/) detectada dinámicamente.
 * Funciona tanto en XAMPP (localhost/branch2/public) como en producción (/).
 */
function app_base_url(): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Subimos desde api/<modulo>/ hasta public/
    $base = rtrim(dirname(dirname($scriptDir)), '/');
    return $base === '' ? '' : $base;
}

/**
 * Detecta si la petición actual es AJAX/JSON.
 * Comprueba Accept: application/json, X-Requested-With y ausencia de text/html.
 */
function es_ajax_request(): bool {
    $accept     = $_SERVER['HTTP_ACCEPT']           ?? '';
    $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return strpos($accept, 'application/json') !== false
        || strtolower($xRequested) === 'xmlhttprequest'
        || strpos($accept, 'text/html') === false;
}
?>
