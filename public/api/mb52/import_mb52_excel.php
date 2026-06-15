<?php
// ── Límites de ejecución ─────────────────────────────────────────────────────
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../config/auth_guard_admin.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
csrf_verify();

// ── Filtro de lectura por bloque de filas ────────────────────────────────────
class ChunkReadFilter implements IReadFilter
{
    private $startRow = 1;
    private $endRow   = 1;

    public function setRows($start, $size)
    {
        $this->startRow = $start;
        $this->endRow   = $start + $size - 1;
    }

    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        return $row === 1 || ($row >= $this->startRow && $row <= $this->endRow);
    }
}

// ── Helper: leer celda sin explotar ─────────────────────────────────────────
function leerCeldaSegura(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): ?string
{
    try {
        $val = $cell->getValue();
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$val);
        return trim($val);
    } catch (Throwable $e) {
        return null;
    }
}

// ── Constantes ───────────────────────────────────────────────────────────────
const CHUNK_SIZE     = 500;
const MAX_ERR_LINEAS = 500;

try {
    // ── Validar archivo subido ───────────────────────────────────────────────
    if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se recibió un archivo Excel válido.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo debe ser XLS o XLSX.']);
        exit;
    }

    $mimeReal     = mime_content_type($_FILES['excel']['tmp_name']);
    $mimesValidos = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
        'application/msexcel',
        'application/x-msexcel',
        'application/x-ms-excel',
        'application/x-excel',
        'application/x-dos_ms_excel',
        'application/xls',
        'application/x-xls',
        'application/xlsb',
        'application/vnd.ms-office',
        'application/CDFV2',
        'application/CDFV2-encrypted',
    ];
    if (!in_array($mimeReal, $mimesValidos, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo no es un Excel válido (tipo MIME no permitido).']);
        exit;
    }

    // Copiar el archivo temporal con la extensión correcta para que IOFactory
    // pueda identificar el tipo de archivo (crítico para .xls binario)
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('mb52_') . '.' . $ext;
    if (!copy($_FILES['excel']['tmp_name'], $tmpPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error procesando el archivo subido.']);
        exit;
    }
    register_shutdown_function(function() use ($tmpPath) {
        if (file_exists($tmpPath)) @unlink($tmpPath);
    });

    // ── Contar filas totales sin cargar datos ────────────────────────────────
    try {
        $readerInfo      = IOFactory::createReaderForFile($tmpPath);
        $readerInfo->setReadDataOnly(true);
        $readerInfo->setReadEmptyCells(false);
        $spreadsheetInfo = $readerInfo->listWorksheetInfo($tmpPath);
        $totalFilas      = (int)($spreadsheetInfo[0]['totalRows'] ?? 0);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se pudo leer el archivo Excel.']);
        exit;
    }

    if ($totalFilas < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo Excel no contiene filas de datos.']);
        exit;
    }

    // ── Conectar a BD e iniciar transacción ──────────────────────────────────
    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    $pdo->exec('DELETE FROM mb52');

    $importados         = 0;
    $saltadas           = 0;
    $erroresExcel       = 0;
    $erroresNomenclador = 0;
    $lineasError        = [];

    $checkArticulo = $pdo->prepare('SELECT 1 FROM articulo    WHERE codigo_articulo = :id');
    $checkAlmacen  = $pdo->prepare('SELECT 1 FROM cod_almacen WHERE codigo_almacen  = :cod');

    $insertMB52 = $pdo->prepare('
        INSERT INTO mb52 (
            centro, almacen, denom_almacen, grupo_art,
            material, desc_articulo, libre_utilizacion,
            umb, valor_lu, bloqueado
        ) VALUES (
            :centro, :almacen, :denom_almacen, :grupo_art,
            :material, :desc_articulo, :libre_utilizacion,
            :umb, :valor_lu, :bloqueado
        )
    ');

    // ── Leer el archivo en bloques ───────────────────────────────────────────
    $filter = new ChunkReadFilter();
    $reader = IOFactory::createReaderForFile($tmpPath);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $reader->setReadFilter($filter);

    for ($startRow = 2; $startRow <= $totalFilas; $startRow += CHUNK_SIZE) {

        $filter->setRows($startRow, CHUNK_SIZE);

        try {
            $spreadsheet = $reader->load($tmpPath);
        } catch (Throwable $e) {
            $filasFin   = min($startRow + CHUNK_SIZE - 1, $totalFilas);
            $saltadas   += $filasFin - $startRow + 1;
            if (count($lineasError) < MAX_ERR_LINEAS) {
                $lineasError[] = "[BLOQUE SALTADO - Error de lectura] Filas $startRow–$filasFin";
            }
            continue;
        }

        $hoja = $spreadsheet->getActiveSheet();

        foreach ($hoja->getRowIterator($startRow, $startRow + CHUNK_SIZE - 1) as $row) {

            $numFila      = $row->getRowIndex();
            $celdaFallida = false;
            $cells        = [];

            try {
                foreach ($row->getCellIterator() as $cell) {
                    $valor = leerCeldaSegura($cell);
                    if ($valor === null) $celdaFallida = true;
                    $cells[] = $valor ?? '';
                }
            } catch (Throwable $e) {
                $saltadas++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[FILA SALTADA - Error de lectura] Fila $numFila";
                }
                continue;
            }

            if ($celdaFallida) {
                $saltadas++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[FILA SALTADA - Carácter especial] Fila $numFila";
                }
                continue;
            }

            if (count(array_filter($cells)) === 0) continue;

            [
                $centro,
                $almacen,
                $denom_almacen,
                $grupo_art,
                $material,
                $desc_articulo,
                $libre_utilizacion,
                $umb,
                $valor_lu,
                $bloqueado
            ] = array_pad($cells, 10, '');

            $resumen = implode(' | ', [
                $centro, $almacen, $denom_almacen, $grupo_art,
                $material, $desc_articulo, $libre_utilizacion,
                $umb, $valor_lu, $bloqueado
            ]);

            // Validaciones de formato
            if (
                !$centro || !$almacen || !$denom_almacen || !$grupo_art ||
                !$material || !$desc_articulo || $libre_utilizacion === '' ||
                !$umb || $valor_lu === '' || $bloqueado === ''
            ) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Campos incompletos] Fila $numFila\n  $resumen";
                }
                continue;
            }

            if (!ctype_digit($almacen)) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Almacén no numérico] Fila $numFila\n  $resumen";
                }
                continue;
            }
            $almacenPad = str_pad($almacen, 4, '0', STR_PAD_LEFT);

            if (!ctype_digit($material) || strlen($material) !== 10) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Material inválido (10 dígitos)] Fila $numFila\n  $resumen";
                }
                continue;
            }

            if (!ctype_digit($grupo_art) || strlen($grupo_art) !== 4) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Grupo de artículo inválido (4 dígitos)] Fila $numFila\n  $resumen";
                }
                continue;
            }

            // Validaciones de nomenclador
            $checkArticulo->execute([':id' => $material]);
            if (!$checkArticulo->fetch()) {
                $erroresNomenclador++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR NOMENCLADOR - Artículo $material no existe] Fila $numFila\n  $resumen";
                }
                continue;
            }

            $checkAlmacen->execute([':cod' => $almacenPad]);
            if (!$checkAlmacen->fetch()) {
                $erroresNomenclador++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR NOMENCLADOR - Almacén $almacenPad no existe] Fila $numFila\n  $resumen";
                }
                continue;
            }

            // INSERT
            $insertMB52->execute([
                ':centro'            => $centro,
                ':almacen'           => $almacenPad,
                ':denom_almacen'     => $denom_almacen,
                ':grupo_art'         => $grupo_art,
                ':material'          => $material,
                ':desc_articulo'     => $desc_articulo,
                ':libre_utilizacion' => (int)$libre_utilizacion,
                ':umb'               => $umb,
                ':valor_lu'          => (float)$valor_lu,
                ':bloqueado'         => (int)$bloqueado,
            ]);

            $importados++;
        }

        // Liberar memoria del bloque antes de cargar el siguiente
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();
    }

    $pdo->commit();

    // ── Reporte TXT de errores ───────────────────────────────────────────────
    $txtBase64    = null;
    $reporteTrunc = count($lineasError) >= MAX_ERR_LINEAS;

    if (!empty($lineasError)) {
        $fecha    = date('d/m/Y H:i:s');
        $cabecera = "REPORTE DE ERRORES - IMPORTACIÓN MB52\n"
                  . "Fecha: $fecha\n"
                  . "Importados correctamente:              $importados\n"
                  . "Filas saltadas (carácter especial):    $saltadas\n"
                  . "Errores de formato Excel:              $erroresExcel\n"
                  . "Errores de nomenclador:                $erroresNomenclador\n"
                  . ($reporteTrunc ? "AVISO: reporte truncado a " . MAX_ERR_LINEAS . " líneas.\n" : '')
                  . str_repeat('-', 60) . "\n\n"
                  . "COLUMNAS: Centro | Almacén | Denom. Almacén | Grupo Art | Material | Descripción | Libre Utilización | UMB | Valor LU | Bloqueado\n\n";

        $txtBase64 = base64_encode($cabecera . implode("\n\n", $lineasError));
    }

    echo json_encode([
        'success'             => true,
        'importados'          => $importados,
        'saltadas'            => $saltadas,
        'errores_excel'       => $erroresExcel,
        'errores_nomenclador' => $erroresNomenclador,
        'reporte_truncado'    => $reporteTrunc,
        'reporte_txt'         => $txtBase64,
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error procesando MB52: ' . $e->getMessage()]);
}
?>
