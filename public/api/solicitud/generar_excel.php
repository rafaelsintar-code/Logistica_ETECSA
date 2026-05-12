<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth_guard.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

header('Content-Type: application/json; charset=utf-8');

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
        SELECT nombre_pv, codigo_almacen, hora_creacion
        FROM solicitud WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Solicitud no encontrada."]);
        exit;
    }

    $fecha    = new DateTime($sol['hora_creacion']);
    $dia      = $fecha->format('d');
    $mes      = $fecha->format('m');
    $anio     = $fecha->format('Y');
    $fechaFmt = "$dia/$mes/$anio";
    $almacen  = $sol['nombre_pv'] . ' — ' . $sol['codigo_almacen'];

    $sp    = new Spreadsheet();
    $sheet = $sp->getActiveSheet();

    // ── Anchos de columna A-J ───────────────────────────────────
    $sheet->getColumnDimension('A')->setWidth(16);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(10);
    $sheet->getColumnDimension('I')->setWidth(10);
    $sheet->getColumnDimension('J')->setWidth(10);

    // ── Estilos reutilizables ───────────────────────────────────
    $borde   = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]];
    $negrita = ['font'    => ['bold' => true]];
    $centro  = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
    $wrap    = ['alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER]];
    $cabArt  = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD0D0D0']]];

    // ── Filas 1-2: merge vertical en H, I, J ────────────────────
    // H1:H2 → "Día\n$dia", I1:I2 → "Mes\n$mes", J1:J2 → "Año\n$anio"
    $sheet->mergeCells('H1:H2');
    $sheet->setCellValue('H1', "Día\n$dia");
    $sheet->getStyle('H1')->applyFromArray(array_merge($borde, $negrita, $centro, $wrap));

    $sheet->mergeCells('I1:I2');
    $sheet->setCellValue('I1', "Mes\n$mes");
    $sheet->getStyle('I1')->applyFromArray(array_merge($borde, $negrita, $centro, $wrap));

    $sheet->mergeCells('J1:J2');
    $sheet->setCellValue('J1', "Año\n$anio");
    $sheet->getStyle('J1')->applyFromArray(array_merge($borde, $negrita, $centro, $wrap));

    // ── Fila 1: Título ──────────────────────────────────────────
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'Anexo 1 Solicitud o Devolución de Recursos Materiales en ETECSA');
    $sheet->getStyle('A1')->applyFromArray(array_merge($borde, $negrita, $centro));
    $sheet->getRowDimension(1)->setRowHeight(20);

    // ── Fila 2: Unidad Organizativa + Código + 161.0.40095 ──────
    $sheet->setCellValue('A2', 'Unidad Organizativa');
    $sheet->getStyle('A2')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('B2:D2');
    $sheet->setCellValue('B2', 'DT Pinar del Río C001');
    $sheet->getStyle('B2')->applyFromArray(array_merge($borde, $centro));

    $sheet->setCellValue('E2', 'Código');
    $sheet->getStyle('E2')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('F2:G2');
    $sheet->setCellValue('F2', '161.0.40095');
    $sheet->getStyle('F2')->applyFromArray(array_merge($borde, $centro));
    $sheet->getRowDimension(2)->setRowHeight(20);

    // ── Fila 3: Área Organizativa + Código + Fecha ──────────────
    $sheet->setCellValue('A3', 'Área Organizativa');
    $sheet->getStyle('A3')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('B3:D3');
    $sheet->setCellValue('B3', 'Dpto Comercial');
    $sheet->getStyle('B3')->applyFromArray(array_merge($borde, $centro));

    $sheet->setCellValue('E3', 'Código');
    $sheet->getStyle('E3')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('F3:G3');
    $sheet->getStyle('F3')->applyFromArray($borde);

    $sheet->mergeCells('H3:J3');
    $sheet->setCellValue('H3', $fechaFmt);
    $sheet->getStyle('H3')->applyFromArray(array_merge($borde, $centro));
    $sheet->getRowDimension(3)->setRowHeight(18);

    // ── Fila 4: Solicitud / Devolución ──────────────────────────
    $sheet->mergeCells('A4:D4');
    $sheet->setCellValue('A4', 'Solicitud:    X');
    $sheet->getStyle('A4')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('E4:J4');
    $sheet->setCellValue('E4', 'Devolución:');
    $sheet->getStyle('E4')->applyFromArray(array_merge($borde, $negrita));
    $sheet->getRowDimension(4)->setRowHeight(18);

    // ── Fila 5: Destino + Tarea de Inversión + No. Reserva ──────
    $sheet->setCellValue('A5', 'Destino');
    $sheet->getStyle('A5')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('B5:C5');
    $sheet->setCellValue('B5', 'Tarea de Inversión');
    $sheet->getStyle('B5')->applyFromArray(array_merge($borde, $negrita));

    $sheet->getStyle('D5')->applyFromArray($borde);

    $sheet->mergeCells('E5:F5');
    $sheet->setCellValue('E5', 'No. Reserva Inversión');
    $sheet->getStyle('E5')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('G5:J5');
    $sheet->getStyle('G5')->applyFromArray($borde);
    $sheet->getRowDimension(5)->setRowHeight(18);

    // ── Fila 6: Centro de Costo + No. Cuenta ────────────────────
    $sheet->getStyle('A6')->applyFromArray($borde);

    $sheet->mergeCells('B6:C6');
    $sheet->setCellValue('B6', 'Centro de Costo');
    $sheet->getStyle('B6')->applyFromArray(array_merge($borde, $negrita));

    $sheet->getStyle('D6')->applyFromArray($borde);

    $sheet->mergeCells('E6:F6');
    $sheet->setCellValue('E6', 'No. Cuenta');
    $sheet->getStyle('E6')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('G6:J6');
    $sheet->getStyle('G6')->applyFromArray($borde);
    $sheet->getRowDimension(6)->setRowHeight(18);

    // ── Fila 7: Orden de Trabajo + Almacén ──────────────────────
    $sheet->getStyle('A7')->applyFromArray($borde);

    $sheet->mergeCells('B7:C7');
    $sheet->setCellValue('B7', 'Orden de Trabajo');
    $sheet->getStyle('B7')->applyFromArray(array_merge($borde, $negrita));

    $sheet->getStyle('D7')->applyFromArray($borde);

    $sheet->mergeCells('E7:F7');
    $sheet->setCellValue('E7', 'Almacén');
    $sheet->getStyle('E7')->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells('G7:J7');
    $sheet->setCellValue('G7', $almacen);
    $sheet->getStyle('G7')->applyFromArray(array_merge($borde, $centro));
    $sheet->getRowDimension(7)->setRowHeight(18);

    // ── Fila 8: Cabecera tabla artículos ────────────────────────
    $sheet->setCellValue('A8', 'Código');
    $sheet->mergeCells('B8:D8');
    $sheet->setCellValue('B8', 'Descripción del Recurso');
    $sheet->setCellValue('E8', 'UM');
    $sheet->setCellValue('F8', 'Cantidad');
    $sheet->mergeCells('G8:H8');
    $sheet->setCellValue('G8', 'Centro de Costo');
    $sheet->mergeCells('I8:J8');
    $sheet->setCellValue('I8', 'Cantidad a Entregar');
    $sheet->getStyle('A8:J8')->applyFromArray(array_merge($borde, $negrita, $centro, $cabArt));
    $sheet->getRowDimension(8)->setRowHeight(20);

    // ── Filas artículos — mínimo 8, crece si hay más ────────────
    $totalFilas = max(8, count($articulos));
    for ($i = 0; $i < $totalFilas; $i++) {
        $row  = 9 + $i;
        $cod  = $articulos[$i]['codigo']      ?? '';
        $desc = $articulos[$i]['descripcion'] ?? '';
        $cant = $articulos[$i]['cantidad']    ?? '';
        $um   = $cod ? 'U' : '';

        $sheet->setCellValue("A$row", $cod);
        $sheet->mergeCells("B$row:D$row");
        $sheet->setCellValue("B$row", $desc);
        $sheet->setCellValue("E$row", $um);
        $sheet->setCellValue("F$row", $cant);
        $sheet->mergeCells("G$row:H$row");
        $sheet->setCellValue("G$row", '');
        $sheet->mergeCells("I$row:J$row");
        $sheet->setCellValue("I$row", '');

        $sheet->getStyle("A$row:J$row")->applyFromArray($borde);
        $sheet->getStyle("A$row")->applyFromArray($centro);
        $sheet->getStyle("E$row")->applyFromArray($centro);
        $sheet->getStyle("F$row")->applyFromArray($centro);
        $sheet->getRowDimension($row)->setRowHeight(16);
    }

    // ── Filas Observaciones (dinámicas según totalFilas) ────────
    $obs1 = 9 + $totalFilas;
    $obs2 = $obs1 + 1;

    $sheet->setCellValue("A$obs1", 'Observaciones:');
    $sheet->getStyle("A$obs1")->applyFromArray(array_merge($borde, $negrita));
    $sheet->mergeCells("B$obs1:J$obs1");
    $sheet->getStyle("B$obs1")->applyFromArray($borde);
    $sheet->getRowDimension($obs1)->setRowHeight(18);

    $sheet->getStyle("A$obs2")->applyFromArray($borde);
    $sheet->mergeCells("B$obs2:J$obs2");
    $sheet->getStyle("B$obs2")->applyFromArray($borde);
    $sheet->getRowDimension($obs2)->setRowHeight(18);

    // ── Pie: encabezados ────────────────────────────────────────
    $pie1 = $obs2 + 1;
    $sheet->mergeCells("A$pie1:C$pie1");
    $sheet->setCellValue("A$pie1", 'Solicitado por:');
    $sheet->getStyle("A$pie1")->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells("D$pie1:F$pie1");
    $sheet->setCellValue("D$pie1", 'Autorizado por:');
    $sheet->getStyle("D$pie1")->applyFromArray(array_merge($borde, $negrita));

    $sheet->mergeCells("G$pie1:I$pie1");
    $sheet->setCellValue("G$pie1", 'Recibido por:');
    $sheet->getStyle("G$pie1")->applyFromArray(array_merge($borde, $negrita));

    $sheet->setCellValue("J$pie1", 'No.');
    $sheet->getStyle("J$pie1")->applyFromArray(array_merge($borde, $negrita, $centro));
    $sheet->getRowDimension($pie1)->setRowHeight(18);

    // ── Pie: nombres + vale ─────────────────────────────────────
    $pie2 = $pie1 + 1;
    $sheet->mergeCells("A$pie2:C$pie2");
    $sheet->setCellValue("A$pie2", 'Nombre:');
    $sheet->getStyle("A$pie2")->applyFromArray($borde);

    $sheet->mergeCells("D$pie2:F$pie2");
    $sheet->setCellValue("D$pie2", 'Nombre:');
    $sheet->getStyle("D$pie2")->applyFromArray($borde);

    $sheet->mergeCells("G$pie2:I$pie2");
    $sheet->setCellValue("G$pie2", 'Nombre');
    $sheet->getStyle("G$pie2")->applyFromArray($borde);

    $sheet->setCellValue("J$pie2", $vale);
    $sheet->getStyle("J$pie2")->applyFromArray(array_merge($borde, $negrita, $centro));
    $sheet->getStyle("J$pie2")->getFont()->setSize(14);
    $sheet->getRowDimension($pie2)->setRowHeight(20);

    // ── Pie: firmas ─────────────────────────────────────────────
    $pie3 = $pie2 + 1;
    $sheet->setCellValue("A$pie3", 'Firma');
    $sheet->setCellValue("B$pie3", 'Fecha');
    $sheet->setCellValue("C$pie3", '');
    $sheet->setCellValue("D$pie3", 'Firma');
    $sheet->setCellValue("E$pie3", 'Fecha');
    $sheet->setCellValue("F$pie3", '');
    $sheet->setCellValue("G$pie3", 'Firma');
    $sheet->setCellValue("H$pie3", 'Fecha');
    $sheet->mergeCells("I$pie3:J$pie3");
    $sheet->setCellValue("I$pie3", '');
    $sheet->getStyle("A$pie3:J$pie3")->applyFromArray($borde);
    $sheet->getRowDimension($pie3)->setRowHeight(24);

    // ── Generar y devolver en base64 ────────────────────────────
    $writer = new Xlsx($sp);
    ob_start();
    $writer->save('php://output');
    $xlsx = ob_get_clean();

    $nombrePV  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sol['nombre_pv']);
    echo json_encode([
        "success"  => true,
        "xlsx"     => base64_encode($xlsx),
        "filename" => "{$nombrePV}_vale{$vale}.xlsx"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>