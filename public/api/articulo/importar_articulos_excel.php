<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../../vendor/autoload.php";
require_once __DIR__. '/../../config/auth_guard.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=utf-8");

try {

    /* =========================
       VALIDAR ARCHIVO
    ========================= */
    if (!isset($_FILES["excel"]) || $_FILES["excel"]["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("No se recibió un archivo Excel válido.");
    }
    $ext = strtolower(pathinfo($_FILES["excel"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["xls", "xlsx"])) {
        throw new Exception("El archivo debe ser XLS o XLSX.");
    }

    $archivo = $_FILES["excel"]["tmp_name"];

    /* =========================
       LECTOR COMPATIBLE XLS/XLSX
    ========================= */
    $tipo = IOFactory::identify($archivo);
    $reader = IOFactory::createReader($tipo);
    $reader->setReadDataOnly(true);

    $spreadsheet = $reader->load($archivo);
    $sheet = $spreadsheet->getActiveSheet();

    /* =========================
       CONEXIÓN BD
    ========================= */
    $db = new Database();
    $pdo = $db->getConnection();

    if (!$pdo) {
        throw new Exception("No se pudo conectar a la base de datos.");
    }

    /* =========================
       CONFIGURACIÓN EXCEL
       Columnas:
       A = Familia
       B = Código SIGC
       C = Código Artículo
       D = Descripción
       E = Precio USD
       F = Precio CUP
       G = Acta Precio
       H = Garantía
    ========================= */
    $filaInicio = 12;
    $familiaActual = null;
    $datos = [];

    $ultimaFila = $sheet->getHighestRow();

    for ($fila = $filaInicio; $fila <= $ultimaFila; $fila++) {

        $familia  = trim((string)$sheet->getCell("A$fila")->getValue());
        $sigc     = trim((string)$sheet->getCell("B$fila")->getValue());
        $codigo   = trim((string)$sheet->getCell("C$fila")->getValue());
        $desc     = trim((string)$sheet->getCell("D$fila")->getValue());
        $usd      = trim((string)$sheet->getCell("E$fila")->getValue());
        $cup      = trim((string)$sheet->getCell("F$fila")->getValue());
        $acta     = trim((string)$sheet->getCell("G$fila")->getValue());
        $garantia = trim((string)$sheet->getCell("H$fila")->getValue());

        /* =========================
           DETECCIÓN DE FIN REAL
           (cuando ya no hay códigos)
        ========================= */
        if ($codigo === "" && $desc === "" && $familia === "") {
            continue;
        }

        /* =========================
           ACTUALIZAR FAMILIA
        ========================= */
        if ($familia !== "") {
            $familiaActual = $familia;
        }

        if ($familiaActual === null) {
            continue; // no se importa sin familia
        }

        /* =========================
           VALIDAR CÓDIGO ARTÍCULO
           numeric(10)
        ========================= */
        if (!preg_match('/^\d{10}$/', $codigo)) {
            continue;
        }

        if ($desc === "" || $acta === "" || $garantia === "") {
            continue;
        }

        $datos[] = [
            "codigo_articulo" => $codigo,
            "codigo_sigc"     => $sigc !== "" ? $sigc : null,
            "descripcion"     => $desc,
            "familia"         => $familiaActual,
            "precio_usd"      => $usd !== "" ? $usd : null,
            "precio_cup"      => $cup !== "" ? $cup : null,
            "acta_precio"     => $acta,
            "garantia"        => $garantia
        ];
    }

    if (count($datos) === 0) {
        throw new Exception("No se detectaron filas válidas en el Excel.");
    }

    /* =========================
       CONSULTAS
    ========================= */
    $stmtExiste = $pdo->prepare("
        SELECT COUNT(*) FROM articulo WHERE codigo_articulo = :codigo
    ");

    $stmtInsert = $pdo->prepare("
        INSERT INTO articulo (
            codigo_articulo, codigo_sigc, descripcion, familia,
            precio_usd, precio_cup, acta_precio, garantia
        ) VALUES (
            :codigo_articulo, :codigo_sigc, :descripcion, :familia,
            :precio_usd, :precio_cup, :acta_precio, :garantia
        )
    ");

    $stmtUpdate = $pdo->prepare("
        UPDATE articulo SET
            codigo_sigc = :codigo_sigc,
            descripcion = :descripcion,
            familia = :familia,
            precio_usd = :precio_usd,
            precio_cup = :precio_cup,
            acta_precio = :acta_precio,
            garantia = :garantia
        WHERE codigo_articulo = :codigo_articulo
    ");

    /* =========================
       INSERTAR / ACTUALIZAR
    ========================= */
    $insertados = 0;
    $actualizados = 0;

    foreach ($datos as $row) {

        $stmtExiste->execute([
            ":codigo" => $row["codigo_articulo"]
        ]);

        if ($stmtExiste->fetchColumn() > 0) {
            $stmtUpdate->execute($row);
            $actualizados++;
        } else {
            $stmtInsert->execute($row);
            $insertados++;
        }
    }

    echo json_encode([
        "success" => true,
        "resumen" => [
            "procesados"   => count($datos),
            "insertados"   => $insertados,
            "actualizados" => $actualizados
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ]);
}
