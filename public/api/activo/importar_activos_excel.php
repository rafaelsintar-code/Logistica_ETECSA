<?php
require_once "../../config/database.php";
require "../../../vendor/autoload.php";
require_once __DIR__. '/../../config/auth_guard.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=utf-8");

try {
    if (!isset($_FILES["excel"]) || $_FILES["excel"]["error"] !== UPLOAD_ERR_OK) {
        echo json_encode(["error" => "No se recibió archivo válido."]);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES["excel"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["xls", "xlsx"])) {
        echo json_encode(["error" => "El archivo debe ser XLS o XLSX."]);
        exit;
    }

    $archivoTmp = $_FILES["excel"]["tmp_name"];

    $excel = IOFactory::load($archivoTmp);
    $hoja  = $excel->getActiveSheet();

    $db   = new Database();
    $conn = $db->getConnection();

    // LEER EXCEL
    $datosExcel = [];
    foreach ($hoja->getRowIterator(2) as $fila) {
        $celdas = $fila->getCellIterator();
        $celdas->setIterateOnlyExistingCells(false);

        $valores = [];
        foreach ($celdas as $celda) {
            $valores[] = trim($celda->getValue());
        }

        if (empty($valores[0])) continue;

        $datosExcel[] = [
            "id"     => $valores[0],
            "nombre" => $valores[1] ?? "",
            "tipo"   => $valores[2] ?? ""
        ];
    }

    // LEER BD
    $stmt    = $conn->query("SELECT id_activo FROM activo");
    $datosBD = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $idsBD    = array_column($datosBD, "id_activo");
    $idsExcel = array_column($datosExcel, "id");

    $conn->beginTransaction();

    $stmtInsert = $conn->prepare("
        INSERT INTO activo (id_activo, nombre_activo, tipo_activo)
        VALUES (:id, :nombre, :tipo)
    ");

    $stmtUpdate = $conn->prepare("
        UPDATE activo
        SET nombre_activo = :nombre,
            tipo_activo   = :tipo
        WHERE id_activo = :id
    ");

    foreach ($datosExcel as $row) {
        if (in_array($row["id"], $idsBD)) {
            $stmtUpdate->execute($row);
        } else {
            $stmtInsert->execute($row);
        }
    }

    // ELIMINAR con prepared statement — evita SQL injection
    $aEliminar = array_diff($idsBD, $idsExcel);
    if (!empty($aEliminar)) {
        $placeholders = implode(",", array_fill(0, count($aEliminar), "?"));
        $stmtDel = $conn->prepare("DELETE FROM activo WHERE id_activo IN ($placeholders)");
        $stmtDel->execute(array_values($aEliminar));
    }

    $conn->commit();

    echo json_encode(["ok" => true]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(["error" => $e->getMessage()]);
}
