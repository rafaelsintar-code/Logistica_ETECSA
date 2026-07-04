<?php
/**
 * session_check.php — Endpoint liviano para verificar sesión activa.
 *
 * Lo llama session-guard.js al cargar cada página protegida.
 * Devuelve 200 si hay sesión válida, 401 si no.
 * No devuelve datos sensibles.
 */

require_once __DIR__ . '/../config/session_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// soloJson=true: siempre responde JSON, nunca redirige (es un endpoint puro)
verificar_sesion(true);

http_response_code(200);
echo json_encode(['ok' => true]);
?>
