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

/**
 * Verifica el rate limit por IP usando la tabla login_rate_limit.
 * Si se supera el límite, responde 429 y termina el script.
 * Si la tabla no existe todavía, el error se silencia.
 *
 * Constantes requeridas (deben definirse antes de llamar a esta función):
 *   IP_MAX_INTENTOS  — número máximo de intentos permitidos
 *   IP_VENTANA_SEG   — duración de la ventana en segundos
 */
function verificar_rate_limit_ip(): void {
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash = hash('sha256', $ip);

    try {
        $db  = new Database();
        $pdo = $db->getConnection();

        $pdo->prepare("
            DELETE FROM login_rate_limit
            WHERE ip_hash = :h AND ventana_inicio < NOW() - INTERVAL '15 minutes'
        ")->execute([':h' => $ipHash]);

        $stmt = $pdo->prepare("
            INSERT INTO login_rate_limit (ip_hash, intentos, ventana_inicio)
            VALUES (:h, 1, NOW())
            ON CONFLICT (ip_hash) DO UPDATE
                SET intentos = login_rate_limit.intentos + 1
            RETURNING intentos,
                      EXTRACT(EPOCH FROM (NOW() - ventana_inicio))::INTEGER AS segundos_transcurridos
        ");
        $stmt->execute([':h' => $ipHash]);
        $rl = $stmt->fetch(PDO::FETCH_ASSOC);

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
        // Rate limiting omitido si la tabla aún no existe
    }
}
?>
