<?php
require_once __DIR__ . '/../config/database.php';

class Almacen {

    private $conn;
    private $table_name = "almacen";

    // === CAMPOS DE LA TABLA ===
    public $nombre;
    public $almacen_sap;
    public $almacen_sigc;
    public $almacen_tfa;
    public $almacen_consig;
    public $almacen_devolucion;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // =========================
    // OBTENER TODOS
    // =========================
    public function obtenerTodos() {
        $stmt = $this->conn->prepare("
            SELECT
                nombre,
                almacen_sap,
                almacen_sigc,
                almacen_tfa,
                almacen_consig,
                almacen_devolucion
            FROM {$this->table_name}
            ORDER BY nombre ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================
    // EXISTE (POR PK)
    // =========================
    public function existe($nombre) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$this->table_name}
            WHERE nombre = :nombre
        ");
        $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
        $stmt->execute();
        return intval($stmt->fetchColumn()) > 0;
    }

    // =========================
    // INSERTAR
    // =========================
    public function insertar() {
        $query = "
            INSERT INTO {$this->table_name} (
                nombre,
                almacen_sap,
                almacen_sigc,
                almacen_tfa,
                almacen_consig,
                almacen_devolucion
            ) VALUES (
                :nombre,
                :almacen_sap,
                :almacen_sigc,
                :almacen_tfa,
                :almacen_consig,
                :almacen_devolucion
            )
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":almacen_sap", $this->almacen_sap);
        $stmt->bindParam(":almacen_sigc", $this->almacen_sigc);
        $stmt->bindParam(":almacen_tfa", $this->almacen_tfa);
        $stmt->bindParam(":almacen_consig", $this->almacen_consig);
        $stmt->bindParam(":almacen_devolucion", $this->almacen_devolucion);

        return $stmt->execute();
    }

    // =========================
    // ACTUALIZAR
    // =========================
    public function actualizar() {
        $query = "
            UPDATE {$this->table_name}
            SET
                almacen_sap = :almacen_sap,
                almacen_sigc = :almacen_sigc,
                almacen_tfa = :almacen_tfa,
                almacen_consig = :almacen_consig,
                almacen_devolucion = :almacen_devolucion
            WHERE nombre = :nombre
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":almacen_sap", $this->almacen_sap);
        $stmt->bindParam(":almacen_sigc", $this->almacen_sigc);
        $stmt->bindParam(":almacen_tfa", $this->almacen_tfa);
        $stmt->bindParam(":almacen_consig", $this->almacen_consig);
        $stmt->bindParam(":almacen_devolucion", $this->almacen_devolucion);

        return $stmt->execute();
    }

    // =========================
    // ELIMINAR
    // =========================
    public function eliminar() {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table_name}
            WHERE nombre = :nombre
        ");
        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        return $stmt->execute();
    }
}
?>
