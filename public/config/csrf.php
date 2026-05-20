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
?>
