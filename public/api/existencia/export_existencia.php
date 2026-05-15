<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) throw new Exception("Error de conexión a la base de datos");

    // ==========================
    // FILTROS
    // ==========================
    $where  = [];
    $params = [];

    if (!empty($_GET['almacen'])) {
        if (!ctype_digit(trim($_GET['almacen']))) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro almacen inválido.']);
            exit;
        }
        $where[]              = 'm52.almacen = :almacen';
        $params[':almacen']   = $_GET['almacen'];
    }

    if (!empty($_GET['articulo'])) {
        $where[]              = 'm52.material = :articulo';
        $params[':articulo']  = $_GET['articulo'];
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ==========================
    // CONSULTA
    // ==========================
    $sql = "
        SELECT
            m52.material,
            m52.desc_articulo,
            m52.almacen,
            (m52.libre_utilizacion + m52.bloqueado)        AS cantidad,
            COALESCE(v.promedio_ventas, 0)                 AS promedio_ventas,
            CASE
                WHEN COALESCE(v.promedio_ventas, 0) = 0 THEN 0
                ELSE (m52.libre_utilizacion + m52.bloqueado) / v.promedio_ventas
            END AS disponibilidad

        FROM mb52 m52

        LEFT JOIN (
            SELECT
                material,
                almacen,
                SUM(ABS(cantidad)) / 6.0 AS promedio_ventas
            FROM mb51
            WHERE cantidad < 0
            GROUP BY material, almacen
        ) v
            ON v.material = m52.material
           AND v.almacen  = m52.almacen

        $whereSql

        ORDER BY m52.desc_articulo, m52.almacen
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================
    // FILTRO INDICACIÓN (en PHP,
    // igual que la lógica JS)
    // ==========================
    function obtenerIndicacion(array $row): string {
        $promedio = (float) $row['promedio_ventas'];
        $disp     = (float) $row['disponibilidad'];

        if ($promedio === 0.0)          return 'azul';
        if ($disp === 0.0)              return 'rojo';
        if ($disp > 0 && $disp < 1)    return 'amarillo';
        if ($disp > 12)                 return 'verde';
        return 'gris';
    }

    // Mapa color indicación → hex de fondo
    $coloresFondo = [
        'rojo'     => 'FFCCCC',  // Crítico
        'amarillo' => 'FFF3CC',  // Bajo
        'azul'     => 'CCE5FF',  // Sin ventas
        'verde'    => 'CCFFCC',  // Óptima
        'gris'     => 'F2F2F2',  // Normal
    ];

    if (!empty($_GET['indicacion'])) {
        $indFiltro = $_GET['indicacion'];
        $rows = array_values(array_filter($rows, fn($r) => obtenerIndicacion($r) === $indFiltro));
    }

    // ==========================
    // SPREADSHEET
    // ==========================
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Existencia de Recursos');

    // ---- Título principal ----
    $sheet->mergeCells('A1:F1');
    $sheet->setCellValue('A1', 'Existencia de Recursos');
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2C3E50']],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    // ---- Fila de fecha de exportación ----
    $sheet->mergeCells('A2:F2');
    $sheet->setCellValue('A2', 'Generado: ' . date('d/m/Y H:i'));
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF666666']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    // ---- Cabeceras ----
    $headers = ['Código Artículo', 'Descripción', 'Código Almacén', 'Cantidad', 'Promedio Ventas', 'Disponibilidad'];
    $col     = 'A';

    foreach ($headers as $h) {
        $sheet->setCellValue("{$col}3", $h);
        $sheet->getStyle("{$col}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A5276']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
        ]);
        $col++;
    }
    $sheet->getRowDimension(3)->setRowHeight(20);

    // ---- Datos ----
    $fila = 4;
    foreach ($rows as $row) {
        $promedio    = (float) $row['promedio_ventas'];
        $disp        = (float) $row['disponibilidad'];
        $indicacion  = obtenerIndicacion($row);
        $fondoArgb   = 'FF' . $coloresFondo[$indicacion];

        $sheet->setCellValue("A{$fila}", $row['material']);
        $sheet->setCellValue("B{$fila}", $row['desc_articulo']);
        $sheet->setCellValue("C{$fila}", $row['almacen']);
        $sheet->setCellValue("D{$fila}", number_format((float)$row['cantidad'], 2, '.', ''));
        $sheet->setCellValue("E{$fila}", number_format($promedio, 2, '.', ''));
        $sheet->setCellValue("F{$fila}", $promedio === 0.0 ? '—' : number_format($disp, 2, '.', ''));

        // Color de fila según indicación
        $sheet->getStyle("A{$fila}:F{$fila}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fondoArgb]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $fila++;
    }

    // ---- Anchos de columna ----
    $sheet->getColumnDimension('A')->setWidth(16);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(14);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(18);

    // ---- Congelar cabecera ----
    $sheet->freezePane('A4');

    // ==========================
    // SALIDA
    // ==========================
    $filename = 'existencia_recursos_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}