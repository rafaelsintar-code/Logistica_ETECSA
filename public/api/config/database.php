<?php
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
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
        $this->port     = getenv('DB_PORT')     ?: ($db['port']     ?? '5432');
        $this->db_name  = getenv('DB_NAME')     ?: ($db['db_name']  ?? 'postgres');
        $this->username = getenv('DB_USER')     ?: ($db['username'] ?? 'postgres');
        $this->password = getenv('DB_PASSWORD') ?: ($db['password'] ?? '');
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }
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
