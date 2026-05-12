<?php
class Database {
    private $host = "localhost";
    private $port = "5433";
    private $db_name = "postgres";
    private $username = "postgres";
    private $password = "10101868";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
              "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}",
              $this->username,
              $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES 'utf8'");
            $this->initializeTables();
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode());
        }
        return $this->conn;
    }

    private function initializeTables() {
        $statements = [

            // SEQUENCES
            "CREATE SEQUENCE IF NOT EXISTS mb51_id_seq",
            "CREATE SEQUENCE IF NOT EXISTS solicitud_id_seq",
            "CREATE SEQUENCE IF NOT EXISTS usuario_id_usuario_seq",
            "CREATE SEQUENCE IF NOT EXISTS transferencia_id_seq",

            // TABLAS
            "CREATE TABLE IF NOT EXISTS articulo (
                codigo_articulo  NUMERIC(10, 0) NOT NULL PRIMARY KEY,
                codigo_sigc      NUMERIC(4, 0),
                descripcion      VARCHAR(255)   NOT NULL,
                familia          VARCHAR(100)   NOT NULL,
                precio_usd       VARCHAR(50),
                precio_cup       VARCHAR(50),
                acta_precio      VARCHAR(100)   NOT NULL,
                garantia         VARCHAR(100)   NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS cod_almacen (
                codigo_almacen  NUMERIC(4, 0)  NOT NULL PRIMARY KEY,
                tipo_almacen    VARCHAR(50)    NOT NULL,
                nombre_pv       VARCHAR(150)
            )",

            "CREATE TABLE IF NOT EXISTS mb51 (
                id              INTEGER        NOT NULL DEFAULT nextval('mb51_id_seq'::regclass) PRIMARY KEY,
                material        NUMERIC(10, 0) NOT NULL,
                desc_articulo   TEXT           NOT NULL,
                cantidad        NUMERIC        NOT NULL,
                fecha_doc       DATE           NOT NULL,
                almacen         NUMERIC(4, 0)  NOT NULL,
                clase_mov       TEXT           NOT NULL,
                desc_clase_mov  TEXT           NOT NULL,
                fecha_cont      DATE
            )",

            "CREATE TABLE IF NOT EXISTS mb52 (
                centro            VARCHAR(10)    NOT NULL,
                almacen           NUMERIC(4, 0)  NOT NULL,
                denom_almacen     VARCHAR(100)   NOT NULL,
                grupo_art         NUMERIC(4, 0)  NOT NULL,
                material          NUMERIC(10, 0) NOT NULL,
                desc_articulo     TEXT           NOT NULL,
                libre_utilizacion INTEGER        NOT NULL,
                umb               VARCHAR(10)    NOT NULL,
                valor_lu          NUMERIC(14, 2) NOT NULL,
                bloqueado         INTEGER        NOT NULL,
                PRIMARY KEY (centro, almacen, material)
            )",

            "CREATE TABLE IF NOT EXISTS solicitud (
                id                INTEGER        NOT NULL DEFAULT nextval('solicitud_id_seq'::regclass) PRIMARY KEY,
                codigo_almacen    NUMERIC(4, 0)  NOT NULL,
                tipo_almacen      VARCHAR(100)   NOT NULL,
                nombre_pv         VARCHAR(150)   NOT NULL,
                usuario           VARCHAR(100)   NOT NULL,
                hora_creacion     TIMESTAMP      NOT NULL DEFAULT now(),
                hora_confirmacion TIMESTAMP,
                vale              INTEGER
            )",

            "CREATE TABLE IF NOT EXISTS usuario (
                id_usuario      INTEGER        NOT NULL DEFAULT nextval('usuario_id_usuario_seq'::regclass) PRIMARY KEY,
                username        VARCHAR(50)    NOT NULL,
                nombre          VARCHAR(120)   NOT NULL,
                correo          VARCHAR(150)   NOT NULL,
                password_hash   TEXT           NOT NULL,
                rol             VARCHAR(50)    NOT NULL DEFAULT 'Administrador',
                activo          BOOLEAN        NOT NULL DEFAULT true,
                creado_en       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en  TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS transferencia (
                id                 INTEGER        NOT NULL DEFAULT nextval('transferencia_id_seq'::regclass) PRIMARY KEY,
                almacen_origen     NUMERIC(4, 0)  NOT NULL,
                denom_origen       VARCHAR(100)   NOT NULL,
                almacen_destino    NUMERIC(4, 0)  NOT NULL,
                denom_destino      VARCHAR(100)   NOT NULL,
                usuario            VARCHAR(100)   NOT NULL,
                hora_creacion      TIMESTAMP      NOT NULL DEFAULT NOW(),
                hora_confirmacion  TIMESTAMP,
                vale               INTEGER
            )",

            "ALTER SEQUENCE mb51_id_seq            OWNED BY mb51.id",
            "ALTER SEQUENCE solicitud_id_seq        OWNED BY solicitud.id",
            "ALTER SEQUENCE usuario_id_usuario_seq  OWNED BY usuario.id_usuario",
            "ALTER SEQUENCE transferencia_id_seq    OWNED BY transferencia.id",
        ];

        foreach ($statements as $sql) {
            $this->conn->exec($sql);
        }
    }
}
?>