<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../config/auth_guard_admin.php';
require_once __DIR__ . '/../config/excel_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

try {
    $tmpPath    = excel_validar_y_copiar('mb52_');
    $totalFilas = excel_contar_filas($tmpPath);

    if ($totalFilas < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo Excel no contiene filas de datos.']);
        exit;
    }

    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM mb52');

    $importados = $saltadas = $erroresExcel = $erroresNomenclador = 0;
    $lineasError = [];

    $checkArticulo = $pdo->prepare('SELECT 1 FROM articulo    WHERE codigo_articulo = :id');
    $checkAlmacen  = $pdo->prepare('SELECT 1 FROM cod_almacen WHERE codigo_almacen  = :cod');
    $insert = $pdo->prepare('
        INSERT INTO mb52 (centro, almacen, denom_almacen, grupo_art, material, desc_articulo,
                          libre_utilizacion, umb, valor_lu, bloqueado)
        VALUES (:centro, :almacen, :denom_almacen, :grupo_art, :material, :desc_articulo,
                :libre_utilizacion, :umb, :valor_lu, :bloqueado)
    ');

    $filter = new ExcelChunkReadFilter();
    $reader = IOFactory::createReaderForFile($tmpPath);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $reader->setReadFilter($filter);

    for ($startRow = 2; $startRow <= $totalFilas; $startRow += EXCEL_CHUNK_SIZE) {
        $filter->setRows($startRow, EXCEL_CHUNK_SIZE);
        try {
            $spreadsheet = $reader->load($tmpPath);
        } catch (Throwable $e) {
            $filasFin  = min($startRow + EXCEL_CHUNK_SIZE - 1, $totalFilas);
            $saltadas += $filasFin - $startRow + 1;
            if (count($lineasError) < EXCEL_MAX_ERR_LINEAS)
                $lineasError[] = "[BLOQUE SALTADO] Filas $startRow-$filasFin";
            continue;
        }

        foreach ($spreadsheet->getActiveSheet()->getRowIterator($startRow, $startRow + EXCEL_CHUNK_SIZE - 1) as $row) {
            $numFila = $row->getRowIndex();
            $celdaFallida = false;
            $cells = [];
            try {
                foreach ($row->getCellIterator() as $cell) {
                    $v = excel_leer_celda($cell);
                    if ($v === null) $celdaFallida = true;
                    $cells[] = $v ?? '';
                }
            } catch (Throwable $e) {
                $saltadas++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS)
                    $lineasError[] = "[FILA SALTADA - Error lectura] Fila $numFila";
                continue;
            }

            if ($celdaFallida) { $saltadas++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS)
                    $lineasError[] = "[FILA SALTADA - Carácter especial] Fila $numFila";
                continue;
            }
            if (count(array_filter($cells)) === 0) continue;

            [$centro, $almacen, $denom_almacen, $grupo_art, $material, $desc_articulo,
             $libre_utilizacion, $umb, $valor_lu, $bloqueado] = array_pad($cells, 10, '');
            $resumen = implode(' | ', [$centro, $almacen, $denom_almacen, $grupo_art,
                $material, $desc_articulo, $libre_utilizacion, $umb, $valor_lu, $bloqueado]);

            if (!$centro || !$almacen || !$denom_almacen || !$grupo_art ||
                !$material || !$desc_articulo || $libre_utilizacion === '' ||
                !$umb || $valor_lu === '' || $bloqueado === '') {
                $erroresExcel++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR EXCEL - Campos incompletos] Fila $numFila\n  $resumen";
                continue;
            }
            if (!ctype_digit($almacen)) {
                $erroresExcel++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR EXCEL - Almacén no numérico] Fila $numFila\n  $resumen";
                continue;
            }
            $almacenPad = str_pad($almacen, 4, '0', STR_PAD_LEFT);
            if (!ctype_digit($material) || strlen($material) !== 10) {
                $erroresExcel++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR EXCEL - Material inválido (10 dígitos)] Fila $numFila\n  $resumen";
                continue;
            }
            if (!ctype_digit($grupo_art) || strlen($grupo_art) !== 4) {
                $erroresExcel++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR EXCEL - Grupo de artículo inválido (4 dígitos)] Fila $numFila\n  $resumen";
                continue;
            }
            $checkArticulo->execute([':id' => $material]);
            if (!$checkArticulo->fetch()) {
                $erroresNomenclador++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR NOMENCLADOR - Artículo $material no existe] Fila $numFila\n  $resumen";
                continue;
            }
            $checkAlmacen->execute([':cod' => $almacenPad]);
            if (!$checkAlmacen->fetch()) {
                $erroresNomenclador++;
                if (count($lineasError) < EXCEL_MAX_ERR_LINEAS) $lineasError[] = "[ERROR NOMENCLADOR - Almacén $almacenPad no existe] Fila $numFila\n  $resumen";
                continue;
            }
            $insert->execute([':centro' => $centro, ':almacen' => $almacenPad,
                ':denom_almacen' => $denom_almacen, ':grupo_art' => $grupo_art,
                ':material' => $material, ':desc_articulo' => $desc_articulo,
                ':libre_utilizacion' => (int)$libre_utilizacion, ':umb' => $umb,
                ':valor_lu' => (float)$valor_lu, ':bloqueado' => (int)$bloqueado]);
            $importados++;
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();
    }

    $pdo->commit();

    $reporteTrunc = count($lineasError) >= EXCEL_MAX_ERR_LINEAS;
    $txtBase64 = null;
    if (!empty($lineasError)) {
        $cab = "REPORTE DE ERRORES - IMPORTACIÓN MB52\nFecha: " . date('d/m/Y H:i:s') . "\n"
             . "Importados: $importados | Saltadas: $saltadas | Errores Excel: $erroresExcel | Errores Nomenclador: $erroresNomenclador\n"
             . ($reporteTrunc ? "AVISO: reporte truncado a " . EXCEL_MAX_ERR_LINEAS . " líneas.\n" : '')
             . str_repeat('-', 60) . "\nCOLUMNAS: Centro | Almacén | Denom. Almacén | Grupo Art | Material | Descripción | Libre Utilización | UMB | Valor LU | Bloqueado\n\n";
        $txtBase64 = base64_encode($cab . implode("\n\n", $lineasError));
    }

    echo json_encode(['success' => true, 'importados' => $importados, 'saltadas' => $saltadas,
        'errores_excel' => $erroresExcel, 'errores_nomenclador' => $erroresNomenclador,
        'reporte_truncado' => $reporteTrunc, 'reporte_txt' => $txtBase64]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error procesando MB52: ' . $e->getMessage()]);
}
?>
