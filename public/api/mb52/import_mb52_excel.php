<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/auth_guard.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_FILES['excel'])) {
        http_response_code(400);
        echo json_encode(["error" => "No se recibió archivo Excel."]);
        exit;
    }

    // Validar extensión ANTES de intentar cargar el archivo
    $ext = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xls', 'xlsx'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo debe ser XLS o XLSX.']);
        exit;
    }

    $archivoTmp = $_FILES['excel']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($archivoTmp);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["error" => "No se pudo leer el archivo Excel."]);
        exit;
    }

    $hoja = $spreadsheet->getActiveSheet();

    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM mb52");

    $importados         = 0;
    $erroresExcel       = 0;
    $erroresNomenclador = 0;
    $lineasError        = [];

    $checkArticulo = $pdo->prepare("SELECT 1 FROM articulo WHERE codigo_articulo = :id");
    $checkAlmacen  = $pdo->prepare("SELECT 1 FROM cod_almacen WHERE codigo_almacen = :cod");

    $insertMB52 = $pdo->prepare("
        INSERT INTO mb52 (
            centro, almacen, denom_almacen, grupo_art,
            material, desc_articulo, libre_utilizacion,
            umb, valor_lu, bloqueado
        ) VALUES (
            :centro, :almacen, :denom_almacen, :grupo_art,
            :material, :desc_articulo, :libre_utilizacion,
            :umb, :valor_lu, :bloqueado
        )
    ");

    foreach ($hoja->getRowIterator(2) as $row) {

        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[] = trim((string)$cell->getValue());
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
        ] = array_pad($cells, 10, null);

        $resumen = implode(" | ", array_map(fn($v) => $v ?? '', [
            $centro, $almacen, $denom_almacen, $grupo_art,
            $material, $desc_articulo, $libre_utilizacion,
            $umb, $valor_lu, $bloqueado
        ]));

        // Validaciones de formato
        if (
            !$centro || !$almacen || !$denom_almacen || !$grupo_art ||
            !$material || !$desc_articulo || $libre_utilizacion === null ||
            !$umb || $valor_lu === null || $bloqueado === null
        ) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Campos incompletos]\n  $resumen";
            continue;
        }

        if (!ctype_digit($almacen)) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Almacen no numerico]\n  $resumen";
            continue;
        }
        $almacenPad = str_pad($almacen, 4, "0", STR_PAD_LEFT);

        if (!ctype_digit($material) || strlen($material) !== 10) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Material invalido (debe ser 10 digitos)]\n  $resumen";
            continue;
        }

        if (!ctype_digit($grupo_art) || strlen($grupo_art) !== 4) {
            $erroresExcel++;
            $lineasError[] = "[ERROR EXCEL - Grupo de articulo invalido (debe ser 4 digitos)]\n  $resumen";
            continue;
        }

        // Validaciones de nomenclador
        $checkArticulo->execute([':id' => $material]);
        if (!$checkArticulo->fetch()) {
            $erroresNomenclador++;
            $lineasError[] = "[ERROR NOMENCLADOR - Articulo $material no existe en la BD]\n  $resumen";
            continue;
        }

        $checkAlmacen->execute([':cod' => $almacenPad]);
        if (!$checkAlmacen->fetch()) {
            $erroresNomenclador++;
            $lineasError[] = "[ERROR NOMENCLADOR - Almacen $almacenPad no existe en la BD]\n  $resumen";
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
            ':bloqueado'         => (int)$bloqueado
        ]);

        $importados++;
    }

    $pdo->commit();

    // Generar TXT de errores
    $txtBase64 = null;
    if (!empty($lineasError)) {
        $fecha    = date('d/m/Y H:i:s');
        $cabecera = "REPORTE DE ERRORES - IMPORTACION MB52\n"
                  . "Fecha: $fecha\n"
                  . "Importados correctamente: $importados\n"
                  . "Errores de formato Excel: $erroresExcel\n"
                  . "Errores de nomenclador:   $erroresNomenclador\n"
                  . str_repeat("-", 60) . "\n\n"
                  . "COLUMNAS: Centro | Almacen | Denom. Almacen | Grupo Art | Material | Descripcion | Libre Utilizacion | UMB | Valor LU | Bloqueado\n\n";

        $contenido = $cabecera . implode("\n\n", $lineasError);
        $txtBase64 = base64_encode($contenido);
    }

    echo json_encode([
        "success"             => true,
        "importados"          => $importados,
        "errores_excel"       => $erroresExcel,
        "errores_nomenclador" => $erroresNomenclador,
        "reporte_txt"         => $txtBase64
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Error procesando MB52: " . $e->getMessage()]);
}
?>
