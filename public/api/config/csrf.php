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
 *
 * Estrategia (en orden de prioridad):
 *   1. Variable de entorno APP_BASE_URL — definida en el pool de PHP-FPM o
 *      en nginx.conf con fastcgi_param APP_BASE_URL ""; Es el método más
 *      fiable y explícito para producción.
 *   2. Detección automática por SCRIPT_NAME — funciona en XAMPP y en Nginx
 *      cuando fastcgi_param SCRIPT_FILENAME está configurado correctamente.
 *
 * En producción con DocumentRoot = public/ y dominio en raíz (/),
 * la función devolverá "" (cadena vacía), lo cual es correcto:
 *   Location: /pages/login.html   ← URL absoluta desde la raíz del dominio.
 *
 * Para definir APP_BASE_URL en el pool PHP-FPM (/etc/php/x.x/fpm/pool.d/www.conf):
 *   env[APP_BASE_URL] =
 * (vacío = aplicación en la raíz del dominio)
 */
function app_base_url(): string {
    // Prioridad 1: variable de entorno explícita (producción Nginx)
    $envUrl = getenv('APP_BASE_URL');
    if ($envUrl !== false) {
        return rtrim($envUrl, '/');
    }

    // Prioridad 2: detección automática por SCRIPT_NAME (XAMPP / fallback)
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
