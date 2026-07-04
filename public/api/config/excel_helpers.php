<?php
/**
 * excel_helpers.php — Utilidades compartidas para todos los imports de Excel.
 *
 * Incluir con require_once ANTES de usar PhpSpreadsheet en cualquier import.
 * Centraliza: lista de MIMEs válidos, constantes de chunk, clase ChunkReadFilter,
 * y funciones de limpieza/parseo de celdas.
 */

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

// ── Constantes de importación ────────────────────────────────────────────────
if (!defined('EXCEL_CHUNK_SIZE'))     define('EXCEL_CHUNK_SIZE',     500);
if (!defined('EXCEL_MAX_ERR_LINEAS')) define('EXCEL_MAX_ERR_LINEAS', 500);

// ── Tipos MIME aceptados para archivos Excel ─────────────────────────────────
// Incluye variantes generadas por SAP, Excel antiguo (.xls binario),
// LibreOffice, y sistemas que detectan xlsx como ZIP.
if (!defined('EXCEL_MIMES_VALIDOS')) {
    define('EXCEL_MIMES_VALIDOS', [
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
    ]);
}

// ── Validación y copia del archivo subido ────────────────────────────────────
/**
 * Valida el archivo Excel subido y lo copia a un temporal con extensión correcta.
 * IOFactory necesita la extensión para identificar el tipo (.xls vs .xlsx).
 *
 * @param  string $prefix  Prefijo para el nombre del temporal (ej: 'mb51_').
 * @return string          Ruta al archivo temporal copiado.
 * @throws Exception       Si el archivo no es válido o no se puede copiar.
 */
function excel_validar_y_copiar(string $prefix = 'excel_'): string
{
    if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se recibió un archivo Excel válido.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo debe ser XLS o XLSX.']);
        exit;
    }

    $mimeReal = mime_content_type($_FILES['excel']['tmp_name']);
    if (!in_array($mimeReal, EXCEL_MIMES_VALIDOS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo no es un Excel válido (tipo MIME no permitido).']);
        exit;
    }

    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid($prefix) . '.' . $ext;
    if (!copy($_FILES['excel']['tmp_name'], $tmpPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error procesando el archivo subido.']);
        exit;
    }

    register_shutdown_function(function () use ($tmpPath) {
        if (file_exists($tmpPath)) @unlink($tmpPath);
    });

    return $tmpPath;
}

// ── Contar filas sin cargar datos ────────────────────────────────────────────
/**
 * Devuelve el total de filas del primer worksheet sin cargar los datos en memoria.
 *
 * @throws Exception Si el archivo no puede leerse.
 */
function excel_contar_filas(string $tmpPath): int
{
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $info = $reader->listWorksheetInfo($tmpPath);
    return (int)($info[0]['totalRows'] ?? 0);
}

// ── Filtro de lectura por bloque ─────────────────────────────────────────────
/**
 * PhpSpreadsheet carga solo las filas del bloque actual, liberando las anteriores.
 * Siempre incluye la fila 1 (cabecera) para mantener el contexto de columnas.
 */
class ExcelChunkReadFilter implements IReadFilter
{
    private int $startRow = 1;
    private int $endRow   = 1;

    public function setRows(int $start, int $size): void
    {
        $this->startRow = $start;
        $this->endRow   = $start + $size - 1;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row === 1 || ($row >= $this->startRow && $row <= $this->endRow);
    }
}

// ── Limpieza de celda ────────────────────────────────────────────────────────
/**
 * Lee el valor de una celda eliminando caracteres de control no imprimibles.
 * Devuelve null si hay una excepción al leer la celda.
 */
function excel_leer_celda(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): ?string
{
    try {
        $val = $cell->getValue();
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$val);
        return trim($val);
    } catch (Throwable $e) {
        return null;
    }
}

// ── Parseo de fecha ──────────────────────────────────────────────────────────
/**
 * Convierte una celda de fecha (número serial Excel, d/m/Y o Y-m-d) a Y-m-d.
 * Devuelve null si el valor no puede interpretarse como fecha.
 */
function excel_parse_fecha(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') return null;

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
