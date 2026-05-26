<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Acceso denegado. Este script solo puede ejecutarse desde la línea de comandos.\n");
}

/**
 * install.php — Script de inicialización de base de datos.
 *
 * Ejecutar UNA SOLA VEZ desde la línea de comandos, desde el directorio raíz del proyecto:
 *
 *   php install.php
 *
 * NO debe ser accesible desde el servidor web. Este archivo ya está en la raíz del proyecto, fuera de public/.
 * Una vez ejecutado correctamente puede borrarse o guardarse fuera del servidor.
 */

$configFile = __DIR__ . '/config/db_config.ini';
if (!file_exists($configFile)) {
    die("Error: no se encontró db_config.ini en " . __DIR__ . "\n");
}

$cfg = parse_ini_file($configFile, true);
if ($cfg === false || empty($cfg['database'])) {
    die("Error: formato inválido en db_config.ini\n");
}

$db = $cfg['database'];

try {
    $pdo = new PDO(
        "pgsql:host={$db['host']};port={$db['port']};dbname={$db['db_name']}",
        $db['username'],
        $db['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET client_encoding TO 'UTF8'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "\n");
}

$statements = [
    // --- SECUENCIAS ---
    "CREATE SEQUENCE IF NOT EXISTS mb51_id_seq",
    "CREATE SEQUENCE IF NOT EXISTS solicitud_id_seq",
    "CREATE SEQUENCE IF NOT EXISTS usuario_id_usuario_seq",
    "CREATE SEQUENCE IF NOT EXISTS transferencia_id_seq",

    // --- TABLAS ---
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
        desc_clase_mov  TEXT           NOT NULL
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
        username        VARCHAR(50)    NOT NULL UNIQUE,
        nombre          VARCHAR(120)   NOT NULL,
        correo          VARCHAR(150)   NOT NULL,
        password_hash   TEXT           NOT NULL,
        rol             VARCHAR(50)    NOT NULL DEFAULT 'Administrador',
        activo          BOOLEAN        NOT NULL DEFAULT true,
        creado_en       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en  TIMESTAMP,
        intentos_fallidos INTEGER      NOT NULL DEFAULT 0,
        bloqueado_hasta   TIMESTAMP,
        ultimo_acceso     TIMESTAMP
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

    "CREATE SEQUENCE IF NOT EXISTS solicitud_articulo_id_seq",
    "CREATE SEQUENCE IF NOT EXISTS transferencia_articulo_id_seq",

    "CREATE TABLE IF NOT EXISTS solicitud_articulo (
        id              INTEGER        NOT NULL DEFAULT nextval('solicitud_articulo_id_seq'::regclass) PRIMARY KEY,
        solicitud_id    INTEGER        NOT NULL REFERENCES solicitud(id) ON DELETE CASCADE,
        codigo_articulo VARCHAR(20)    NOT NULL,
        descripcion     TEXT           NOT NULL,
        cantidad        INTEGER        NOT NULL CHECK (cantidad > 0)
    )",

    "CREATE TABLE IF NOT EXISTS transferencia_articulo (
        id                INTEGER        NOT NULL DEFAULT nextval('transferencia_articulo_id_seq'::regclass) PRIMARY KEY,
        transferencia_id  INTEGER        NOT NULL REFERENCES transferencia(id) ON DELETE CASCADE,
        codigo_articulo   VARCHAR(20)    NOT NULL,
        descripcion       TEXT           NOT NULL,
        cantidad          INTEGER        NOT NULL CHECK (cantidad > 0)
    )",

    // --- ASIGNAR SECUENCIAS ---
    "ALTER SEQUENCE IF EXISTS mb51_id_seq           OWNED BY mb51.id",
    "ALTER SEQUENCE IF EXISTS solicitud_id_seq       OWNED BY solicitud.id",
    "ALTER SEQUENCE IF EXISTS usuario_id_usuario_seq OWNED BY usuario.id_usuario",
    "ALTER SEQUENCE IF EXISTS transferencia_id_seq              OWNED BY transferencia.id",
    "ALTER SEQUENCE IF EXISTS solicitud_articulo_id_seq     OWNED BY solicitud_articulo.id",
    "ALTER SEQUENCE IF EXISTS transferencia_articulo_id_seq OWNED BY transferencia_articulo.id",

    // --- COLUMNAS ADICIONALES (bases ya existentes) ---
    "ALTER TABLE usuario ADD COLUMN IF NOT EXISTS intentos_fallidos INTEGER   NOT NULL DEFAULT 0",
    "ALTER TABLE usuario ADD COLUMN IF NOT EXISTS bloqueado_hasta   TIMESTAMP",
    "ALTER TABLE usuario ADD COLUMN IF NOT EXISTS ultimo_acceso     TIMESTAMP",
];

echo "Inicializando base de datos...\n\n";

foreach ($statements as $sql) {
    // Extraer la primera línea como etiqueta para el log
    $label = trim(strtok($sql, "\n"));
    try {
        $pdo->exec($sql);
        echo "  OK  {$label}\n";
    } catch (PDOException $e) {
        $code = $e->getCode();
        // Ignorar "ya existe": 42P07 tabla, 42723 función/secuencia, 42701 columna
        if (in_array($code, ['42P07', '42723', '42701'])) {
            echo "  --  {$label} (ya existe, omitido)\n";
        } else {
            echo " ERR  {$label}\n     {$e->getMessage()}\n";
        }
    }
}

echo "\nListo. Puedes borrar este archivo del servidor.\n";
?>
