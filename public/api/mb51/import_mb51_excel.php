<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/auth_guard.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}


function parseFecha($valor) {
    $valor = trim($valor);
    if ($valor === '') return null;

    $dt = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');

    return null;
}

try {
    if (!isset($_FILES['excel'])) {
        http_response_code(400);
        echo json_encode(["error" => "No se recibió archivo Excel."]);
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo debe ser XLS o XLSX.']);
        exit;
    }

    $spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
    $hoja        = $spreadsheet->getActiveSheet();

    // Pre-validar que el archivo tiene al menos una fila de datos antes de borrar
    $preScan = 0;
    foreach ($spreadsheet->getActiveSheet()->getRowIterator(2) as $row) {
        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[] = trim((string)$cell->getFormattedValue());
        }
        if (count(array_filter($cells)) > 0) {
            $preScan++;
            break; // basta con encontrar una fila no vacía
        }
    }
    if ($preScan === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo Excel no contiene filas de datos.']);
        exit;
    }

    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM mb51");

    $importados          = 0;
    $erroresExcel        = 0;
    $erroresNomenclador  = 0;
    $lineasError         = [];

    $checkArticulo = $pdo->prepare("SELECT 1 FROM articulo WHERE codigo_articulo = :id");
    $checkAlmacen  = $pdo->prepare("SELECT 1 FROM cod_almacen WHERE codigo_almacen = :cod");

    $insert = $pdo->prepare("
        INSERT INTO mb51 (
            material, desc_articulo, cantidad,
            fecha_doc, almacen, clase_mov,
            desc_clase_mov, fecha_cont
        ) VALUES (
            :material, :desc_articulo, :cantidad,
            :fecha_doc, :almacen, :clase_mov,
            :desc_clase_mov, :fecha_cont
        )
    ");

    foreach ($hoja->getRowIterator(2) as $row) {

        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[] = trim((string)$cell->getFormattedValue());
        }

        if (count(array_filter($cells)) === 0) continue;

        [
            $material,
            $desc_articulo,
            $cantidad,
            $fecha_doc_raw,
            $almacen,
            $clase_mov,
            $desc_clase_mov,
            $fecha_cont_raw
        ] = array_pad($cells, 8, null);

        $resumen = implode(" | ", array_map(fn($v) => $v ?? '', [
            $material, $desc_articulo, $cantidad,
            $fecha_doc_raw, $almacen, $clase_mov,
            $desc_clase_mov, $fecha_cont_raw
        ]));

        // ── Validaciones de formato ──────────────────────────────
        if (
            !$material || !$desc_articulo || $cantidad === null ||
            !$fecha_doc_raw || !$almacen || !$clase_mov ||
            !$desc_clase_mov
        ) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Campos incompletos]\n  $resumen";
            continue;
        }

        if (!ctype_digit($material) || strlen($material) !== 10) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Material inválido (debe ser 10 dígitos)]\n  $resumen";
            continue;
        }

        if (!ctype_digit($almacen)) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Almacén no numérico]\n  $resumen";
            continue;
        }
        $almacenPad = str_pad($almacen, 4, "0", STR_PAD_LEFT);

        if (!is_numeric($cantidad)) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Cantidad no numérica]\n  $resumen";
            continue;
        }

        $fecha_doc  = parseFecha($fecha_doc_raw);
        $fecha_cont = $fecha_cont_raw ? parseFecha($fecha_cont_raw) : null;

        if (!$fecha_doc) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Formato de fecha_doc inválido]\n  $resumen";
            continue;
        }

        // ── Validaciones de nomenclador ──────────────────────────
        $checkArticulo->execute([':id' => $material]);
        if (!$checkArticulo->fetch()) {
            $erroresNomenclador++;
            $lineasError[] = "[ERROR NOMENCLADOR - Artículo $material no existe en la BD]\n  $resumen";
            continue;
        }

        $checkAlmacen->execute([':cod' => $almacenPad]);
        if (!$checkAlmacen->fetch()) {
            $erroresNomenclador++;
            $lineasError[] = "[ERROR NOMENCLADOR - Almacén $almacenPad no existe en la BD]\n  $resumen";
            continue;
        }

        // ── INSERT ───────────────────────────────────────────────
        $insert->execute([
            ':material'       => $material,
            ':desc_articulo'  => $desc_articulo,
            ':cantidad'       => (float)$cantidad,
            ':fecha_doc'      => $fecha_doc,
            ':almacen'        => $almacenPad,
            ':clase_mov'      => $clase_mov,
            ':desc_clase_mov' => $desc_clase_mov,
            ':fecha_cont'     => $fecha_cont
        ]);

        $importados++;
    }

    $pdo->commit();

    // ── Generar TXT de errores ───────────────────────────────────
    $txtBase64 = null;
    if (!empty($lineasError)) {
        $fecha   = date('d/m/Y H:i:s');
        $cabecera = "REPORTE DE ERRORES - IMPORTACIÓN MB51\n"
                  . "Fecha: $fecha\n"
                  . "Importados correctamente: $importados\n"
                  . "Errores de formato Excel: $erroresExcel\n"
                  . "Errores de nomenclador:   $erroresNomenclador\n"
                  . str_repeat("-", 60) . "\n\n"
                  . "COLUMNAS: Material | Descripción | Cantidad | Fecha Doc | Almacén | Clase Mov | Desc Clase Mov | Fecha Cont\n\n";

        $contenido = $cabecera . implode("\n\n", $lineasError);
        $txtBase64 = base64_encode($contenido);
    }

    echo json_encode([
        "success"              => true,
        "importados"           => $importados,
        "errores_excel"        => $erroresExcel,
        "errores_nomenclador"  => $erroresNomenclador,
        "reporte_txt"          => $txtBase64
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Error procesando MB51: " . $e->getMessage()]);
}
?>