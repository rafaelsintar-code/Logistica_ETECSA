<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    [
        "value" => "",
        "label" => "Todas las indicaciones"
    ],
    [
        "value" => "rojo",
        "label" => "Crítico (Disponibilidad = 0)"
    ],
    [
        "value" => "amarillo",
        "label" => "Bajo (0 < Disponibilidad < 1)"
    ],
    [
        "value" => "azul",
        "label" => "Sin ventas"
    ],
    [
        "value" => "verde",
        "label" => "Alta (Disponibilidad > 12)"
    ],
    [
        "value" => "gris",
        "label" => "Normal"
    ]
], JSON_UNESCAPED_UNICODE);
