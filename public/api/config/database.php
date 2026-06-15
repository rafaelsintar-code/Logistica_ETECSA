<?php
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // db_config.ini está en config/ (fuera de public/, en la raíz del proyecto)
        $configFile = __DIR__ . '/../../../config/db_config.ini';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Archivo de configuración no encontrado: {$configFile}");
        }

        $cfg = parse_ini_file($configFile, true);
        if ($cfg === false || empty($cfg['database'])) {
            throw new RuntimeException("Formato inválido en db_config.ini");
        }

        $db = $cfg['database'];
        $this->host     = getenv('DB_HOST')     ?: ($db['host']     ?? 'localhost');
        $this->port     = getenv('DB_PORT')     ?: ($db['port']     ?? '5434');
        $this->db_name  = getenv('DB_NAME')     ?: ($db['db_name']  ?? 'postgres');
        $this->username = getenv('DB_USER')     ?: ($db['username'] ?? 'postgres');
        // La contraseña SIEMPRE se lee desde variable de entorno en producción.
        // Solo cae al valor del .ini como último recurso (desarrollo local).
        $this->password = getenv('DB_PASSWORD') ?: ($db['password'] ?? '');
    }

    public function getConnection() {
        try {
            $this->conn = new PDO(
                "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
        return $this->conn;
    }
}
?>
