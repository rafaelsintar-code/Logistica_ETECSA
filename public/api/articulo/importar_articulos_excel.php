<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../../../vendor/autoload.php";
require_once __DIR__ . '/../config/auth_guard_admin.php';
require_once __DIR__ . '/../config/excel_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

try {
    // Validar y copiar el archivo usando el helper compartido (lista MIME completa)
    $tmpPath = excel_validar_y_copiar('articulos_');
    $archivo = $tmpPath;

    $tipo   = IOFactory::identify($archivo);
    $reader = IOFactory::createReader($tipo);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($archivo);
    $sheet       = $spreadsheet->getActiveSheet();

    $db  = new Database();
    $pdo = $db->getConnection();

    /*
     * Columnas del Excel de artículos (fila de datos desde la 12):
     * A = Familia  B = Código SIGC  C = Código Artículo  D = Descripción
     * E = Precio USD  F = Precio CUP  G = Acta Precio  H = Garantía
     */
    $filaInicio    = 12;
    $familiaActual = null;
    $datos         = [];
    $ultimaFila    = $sheet->getHighestRow();

    for ($fila = $filaInicio; $fila <= $ultimaFila; $fila++) {
        $familia  = trim((string)$sheet->getCell("A$fila")->getValue());
        $sigc     = trim((string)$sheet->getCell("B$fila")->getValue());
        $codigo   = trim((string)$sheet->getCell("C$fila")->getValue());
        $desc     = trim((string)$sheet->getCell("D$fila")->getValue());
        $usd      = trim((string)$sheet->getCell("E$fila")->getValue());
        $cup      = trim((string)$sheet->getCell("F$fila")->getValue());
        $acta     = trim((string)$sheet->getCell("G$fila")->getValue());
        $garantia = trim((string)$sheet->getCell("H$fila")->getValue());

        if ($codigo === '' && $desc === '' && $familia === '') continue;

        if ($familia !== '') $familiaActual = $familia;
        if ($familiaActual === null) continue;
        if (!preg_match('/^\d{10}$/', $codigo)) continue;
        if ($desc === '' || $acta === '' || $garantia === '') continue;

        $datos[] = [
            'codigo_articulo' => $codigo,
            'codigo_sigc'     => $sigc !== '' ? $sigc : null,
            'descripcion'     => $desc,
            'familia'         => $familiaActual,
            'precio_usd'      => $usd  !== '' ? $usd  : null,
            'precio_cup'      => $cup  !== '' ? $cup  : null,
            'acta_precio'     => $acta,
            'garantia'        => $garantia,
        ];
    }

    if (count($datos) === 0) {
        throw new Exception("No se detectaron filas válidas en el Excel.");
    }

    $stmtExiste = $pdo->prepare("SELECT 1 FROM articulo WHERE codigo_articulo = :codigo LIMIT 1");
    $stmtInsert = $pdo->prepare("
        INSERT INTO articulo (codigo_articulo, codigo_sigc, descripcion, familia,
                              precio_usd, precio_cup, acta_precio, garantia)
        VALUES (:codigo_articulo, :codigo_sigc, :descripcion, :familia,
                :precio_usd, :precio_cup, :acta_precio, :garantia)
    ");
    $stmtUpdate = $pdo->prepare("
        UPDATE articulo SET
            codigo_sigc = :codigo_sigc, descripcion = :descripcion, familia = :familia,
            precio_usd  = :precio_usd,  precio_cup  = :precio_cup,
            acta_precio = :acta_precio, garantia    = :garantia
        WHERE codigo_articulo = :codigo_articulo
    ");

    $insertados = $actualizados = 0;
    $pdo->beginTransaction();

    foreach ($datos as $row) {
        $stmtExiste->execute([':codigo' => $row['codigo_articulo']]);
        if ($stmtExiste->fetchColumn() > 0) {
            $stmtUpdate->execute($row);
            $actualizados++;
        } else {
            $stmtInsert->execute($row);
            $insertados++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'resumen' => ['procesados' => count($datos), 'insertados' => $insertados, 'actualizados' => $actualizados],
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
