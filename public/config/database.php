<?php
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $configFile = __DIR__ . '/../../db_config.ini';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Archivo de configuración no encontrado: {$configFile}");
        }

        $cfg = parse_ini_file($configFile, true);
        if ($cfg === false || empty($cfg['database'])) {
            throw new RuntimeException("Formato inválido en db_config.ini");
        }

        $db = $cfg['database'];
        $this->host     = $db['host']     ?? 'localhost';
        $this->port     = $db['port']     ?? '5433';
        $this->db_name  = $db['db_name']  ?? 'postgres';
        $this->username = $db['username'] ?? 'postgres';
        $this->password = $db['password'] ?? '';
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
