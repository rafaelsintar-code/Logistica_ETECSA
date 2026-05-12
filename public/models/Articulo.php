<?php
require_once __DIR__ . '/../config/database.php';

class Articulo {

    private $conn;
    private $table_name = "articulo";
    public $codigo_articulo;
    public $codigo_sigc;
    public $descripcion;
    public $familia;
    public $precio_usd;
    public $precio_cup;
    public $acta_precio;
    public $garantia;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /* =========================
       OBTENER TODOS
    ========================= */
    public function obtenerTodos() {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM {$this->table_name}
            ORDER BY familia ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
       EXISTE POR PK
    ========================= */
    public function existe($codigo) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$this->table_name}
            WHERE codigo_articulo = :codigo
        ");
        $stmt->bindParam(":codigo", $codigo);
        $stmt->execute();
        return intval($stmt->fetchColumn()) > 0;
    }

    /* =========================
       INSERTAR
    ========================= */
    public function insertar() {
        $query = "
            INSERT INTO {$this->table_name} (
                codigo_articulo,
                codigo_sigc,
                descripcion,
                familia,
                precio_usd,
                precio_cup,
                acta_precio,
                garantia
            ) VALUES (
                :codigo_articulo,
                :codigo_sigc,
                :descripcion,
                :familia,
                :precio_usd,
                :precio_cup,
                :acta_precio,
                :garantia
            )
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":codigo_articulo", $this->codigo_articulo);
        $stmt->bindParam(":codigo_sigc", $this->codigo_sigc);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":familia", $this->familia);
        $stmt->bindParam(":precio_usd", $this->precio_usd);
        $stmt->bindParam(":precio_cup", $this->precio_cup);
        $stmt->bindParam(":acta_precio", $this->acta_precio);
        $stmt->bindParam(":garantia", $this->garantia);

        return $stmt->execute();
    }

    /* =========================
       ACTUALIZAR
    ========================= */
    public function actualizar() {
        $query = "
            UPDATE {$this->table_name}
            SET
                codigo_sigc = :codigo_sigc,
                descripcion = :descripcion,
                familia = :familia,
                precio_usd = :precio_usd,
                precio_cup = :precio_cup,
                acta_precio = :acta_precio,
                garantia = :garantia
            WHERE codigo_articulo = :codigo_articulo
        ";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":codigo_articulo", $this->codigo_articulo);
        $stmt->bindParam(":codigo_sigc", $this->codigo_sigc);
        $stmt->bindParam(":descripcion", $this->descripcion);
        $stmt->bindParam(":familia", $this->familia);
        $stmt->bindParam(":precio_usd", $this->precio_usd);
        $stmt->bindParam(":precio_cup", $this->precio_cup);
        $stmt->bindParam(":acta_precio", $this->acta_precio);
        $stmt->bindParam(":garantia", $this->garantia);

        return $stmt->execute();
    }

    /* =========================
       ELIMINAR
    ========================= */
    public function eliminar() {
        $stmt = $this->conn->prepare("
            DELETE FROM {$this->table_name}
            WHERE codigo_articulo = :codigo
        ");
        $stmt->bindParam(":codigo", $this->codigo_articulo);
        return $stmt->execute();
    }
}
?>
