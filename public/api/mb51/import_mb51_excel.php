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
// PhpSpreadsheet carga sólo las filas del bloque actual en memoria,
// liberando las anteriores antes de continuar con el siguiente bloque.
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
        // Eliminar caracteres de control no imprimibles
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$val);
        return trim($val);
    } catch (Throwable $e) {
        return null;
    }
}

function parseFecha(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') return null;
    // Excel puede entregar fechas como número serial
    if (is_numeric($valor)) {
        try {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$valor);
            return $date->format('Y-m-d');
        } catch (Throwable $e) {}
    }
    $dt = DateTime::createFromFormat('d/m/Y', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');
    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    if ($dt !== false) return $dt->format('Y-m-d');
    return null;
}

// ── Constantes ───────────────────────────────────────────────────────────────
const CHUNK_SIZE     = 500;   // filas por bloque de lectura
const MAX_ERR_LINEAS = 500;   // máximo de líneas de error a guardar (evita reporte enorme)

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
    ];
    if (!in_array($mimeReal, $mimesValidos, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo no es un Excel válido (tipo MIME no permitido).']);
        exit;
    }

    $tmpPath = $_FILES['excel']['tmp_name'];

    // ── Contar filas totales sin cargar datos ────────────────────────────────
    // Se usa un reader vacío sólo para obtener el número de filas más alto.
    try {
        $readerInfo  = IOFactory::createReaderForFile($tmpPath);
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

    $pdo->exec('DELETE FROM mb51');

    $importados         = 0;
    $saltadas           = 0;
    $erroresExcel       = 0;
    $erroresNomenclador = 0;
    $lineasError        = [];

    $checkArticulo = $pdo->prepare('SELECT 1 FROM articulo   WHERE codigo_articulo = :id');
    $checkAlmacen  = $pdo->prepare('SELECT 1 FROM cod_almacen WHERE codigo_almacen  = :cod');

    $insert = $pdo->prepare('
        INSERT INTO mb51 (
            material, desc_articulo, cantidad,
            fecha_doc, almacen, clase_mov,
            desc_clase_mov
        ) VALUES (
            :material, :desc_articulo, :cantidad,
            :fecha_doc, :almacen, :clase_mov,
            :desc_clase_mov
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
            // Bloque corrupto → saltar todas sus filas
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
                $material,
                $desc_articulo,
                $cantidad,
                $fecha_doc_raw,
                $almacen,
                $clase_mov,
                $desc_clase_mov
            ] = array_pad($cells, 7, '');

            $resumen = implode(' | ', [
                $material, $desc_articulo, $cantidad,
                $fecha_doc_raw, $almacen, $clase_mov,
                $desc_clase_mov
            ]);

            // Validaciones de formato
            if (
                !$material || !$desc_articulo || $cantidad === '' ||
                !$fecha_doc_raw || !$almacen || !$clase_mov || !$desc_clase_mov
            ) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Campos incompletos] Fila $numFila\n  $resumen";
                }
                continue;
            }

            if (!ctype_digit($material) || strlen($material) !== 10) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Material inválido (10 dígitos)] Fila $numFila\n  $resumen";
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

            if (!is_numeric($cantidad)) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Cantidad no numérica] Fila $numFila\n  $resumen";
                }
                continue;
            }

            $fecha_doc = parseFecha($fecha_doc_raw);

            if (!$fecha_doc) {
                $erroresExcel++;
                if (count($lineasError) < MAX_ERR_LINEAS) {
                    $lineasError[] = "[ERROR EXCEL - Fecha_doc inválida] Fila $numFila\n  $resumen";
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
            $insert->execute([
                ':material'       => $material,
                ':desc_articulo'  => $desc_articulo,
                ':cantidad'       => (float)$cantidad,
                ':fecha_doc'      => $fecha_doc,
                ':almacen'        => $almacenPad,
                ':clase_mov'      => $clase_mov,
                ':desc_clase_mov' => $desc_clase_mov,
            ]);

            $importados++;
        }

        // Liberar memoria del bloque actual antes de cargar el siguiente
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
        $cabecera = "REPORTE DE ERRORES - IMPORTACIÓN MB51\n"
                  . "Fecha: $fecha\n"
                  . "Importados correctamente:              $importados\n"
                  . "Filas saltadas (carácter especial):    $saltadas\n"
                  . "Errores de formato Excel:              $erroresExcel\n"
                  . "Errores de nomenclador:                $erroresNomenclador\n"
                  . ($reporteTrunc ? "AVISO: reporte truncado a " . MAX_ERR_LINEAS . " líneas.\n" : '')
                  . str_repeat('-', 60) . "\n\n"
                  . "COLUMNAS: Material | Descripción | Cantidad | Fecha Doc | Almacén | Clase Mov | Desc Clase Mov\n\n";

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
    echo json_encode(['success' => false, 'message' => 'Error procesando MB51: ' . $e->getMessage()]);
}
?>
