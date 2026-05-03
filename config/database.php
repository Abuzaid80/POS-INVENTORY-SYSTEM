<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
class Database {
    private $host = "localhost";
    private $db_name = "pos_inventory_system";
    private $username = "abahzaid";
    private $password = "@mbuhbogor2024";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }

        return $this->conn;
    }

    public function beginTransaction() {
        if ($this->conn) {
            return $this->conn->beginTransaction();
        }
        throw new Exception("No database connection available");
    }

    public function commit() {
        if ($this->conn) {
            return $this->conn->commit();
        }
        throw new Exception("No database connection available");
    }

    public function rollBack() {
        if ($this->conn) {
            return $this->conn->rollBack();
        }
        throw new Exception("No database connection available");
    }

    public function inTransaction() {
        if ($this->conn) {
            return $this->conn->inTransaction();
        }
        return false;
    }
}
?> 