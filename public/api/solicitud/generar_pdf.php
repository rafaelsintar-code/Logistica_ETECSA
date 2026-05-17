<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['solicitud_id'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
        exit;
    }

    $id        = (int) $data['solicitud_id'];
    $articulos = $data['articulos'] ?? [];
    $vale      = $data['vale'];

    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de conexión."]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT nombre_pv, LPAD(codigo_almacen::TEXT, 4, '0') AS codigo_almacen, hora_creacion
        FROM solicitud WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Solicitud no encontrada."]);
        exit;
    }

    $fecha   = new DateTime($sol['hora_creacion']);
    $dia     = $fecha->format('d');
    $mes     = $fecha->format('m');
    $anio    = $fecha->format('Y');
    $fechaFmt = "$dia/$mes/$anio";

    // Si el código empieza en '0', se representa como C + 3 dígitos (ej: 0001 → C001)
    $codAlm  = str_pad((string)$sol['codigo_almacen'], 4, '0', STR_PAD_LEFT);
    $etiquetaAlm = $codAlm[0] === '0' ? 'C001' : $codAlm;

    $almacen = htmlspecialchars($etiquetaAlm, ENT_QUOTES, 'UTF-8');

    // Filas artículos — siempre 8
    $filasHtml = '';
    $totalFilas = max(8, count($articulos));
    for ($i = 0; $i < $totalFilas; $i++) {
        $num  = $i + 1;
        $cod  = htmlspecialchars($articulos[$i]['codigo']      ?? '', ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($articulos[$i]['descripcion'] ?? '', ENT_QUOTES, 'UTF-8');
        $cant = $articulos[$i]['cantidad'] ?? '';
        $um   = $cod ? 'U' : '';

        $filasHtml .= "
        <tr>
            <td class='center'>$cod</td>
            <td colspan='3'>$desc</td>
            <td class='center'>$um</td>
            <td class='center'>$cant</td>
            <td colspan='2'></td>
            <td colspan='2'></td>
        </tr>";
    }

    // Cargar plantilla y reemplazar placeholders
    $plantilla = file_get_contents(
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'plantillas' . DIRECTORY_SEPARATOR . 'plantilla_solicitud.html'
    );

    $html = str_replace(
        ['{{dia}}', '{{mes}}', '{{anio}}', '{{fecha}}', '{{almacen}}', '{{vale}}', '{{filas_articulos}}'],
        [$dia,      $mes,      $anio,      $fechaFmt,   $almacen,      $vale,      $filasHtml],
        $plantilla
    );

    // Generar PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $nombrePV  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sol['nombre_pv']);
    echo json_encode([
        "success"  => true,
        "pdf"      => base64_encode($dompdf->output()),
        "filename" => "{$nombrePV}_vale{$vale}.pdf"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>