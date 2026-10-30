<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * Clase de Conexión a la Base de Datos (PDO)
 * Diseñada para soportar tanto Docker Compose como ejecución local en XAMPP / SQLite.
 */
class Connection
{
    private string $host;
    private string $port;
    private string $dbName;
    private string $username;
    private string $password;
    private ?PDO $conn = null;

    public function __construct()
    {
        // Lectura de variables de entorno con valores por defecto
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->dbName = getenv('DB_NAME') ?: 'monitor_team_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    }

    public function getConnection(): ?PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // Intentar conexión a MySQL / MariaDB
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            // Fallback persistente local con SQLite
            return $this->initLocalFallback();
        }
    }

    /**
     * Fallback persistente con SQLite para desarrollo local sin interrupciones
     */
    private function initLocalFallback(): ?PDO
    {
        try {
            $sqlitePath = __DIR__ . '/local_monitor.sqlite';
            $this->conn = new PDO("sqlite:" . $sqlitePath);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Inicializar tablas
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS USERS (
                    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    role TEXT DEFAULT 'user',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login_at DATETIME NULL,
                    last_seen_at DATETIME NULL,
                    current_host TEXT DEFAULT 'Mi Computadora',
                    last_cpu REAL DEFAULT 0.0,
                    last_ram REAL DEFAULT 0.0
                );
                CREATE TABLE IF NOT EXISTS HOSTS (
                    host_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    hostname TEXT UNIQUE NOT NULL,
                    ip_address TEXT NOT NULL,
                    operating_system TEXT,
                    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS METRICS (
                    metric_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    host_id INTEGER NOT NULL,
                    cpu_usage_pct REAL NOT NULL,
                    ram_used_mb REAL NOT NULL,
                    ram_usage_pct REAL NOT NULL,
                    disk_usage_pct REAL NOT NULL,
                    measured_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS THRESHOLDS (
                    threshold_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    host_id INTEGER NOT NULL,
                    metric_name TEXT NOT NULL,
                    comparison_operator TEXT NOT NULL,
                    threshold_value REAL NOT NULL,
                    is_active INTEGER DEFAULT 1
                );
                CREATE TABLE IF NOT EXISTS ALERTS (
                    alert_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    threshold_id INTEGER NOT NULL,
                    metric_id INTEGER NOT NULL,
                    triggered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    message TEXT NOT NULL,
                    notification_status TEXT DEFAULT 'pending'
                );
            ");

            // Asegurar que las columnas nuevas existan si la BD ya estaba creada
            $columns = [
                "ALTER TABLE USERS ADD COLUMN role TEXT DEFAULT 'user'",
                "ALTER TABLE USERS ADD COLUMN last_seen_at DATETIME NULL",
                "ALTER TABLE USERS ADD COLUMN current_host TEXT DEFAULT 'Mi Computadora'",
                "ALTER TABLE USERS ADD COLUMN last_cpu REAL DEFAULT 0.0",
                "ALTER TABLE USERS ADD COLUMN last_ram REAL DEFAULT 0.0"
            ];
            foreach ($columns as $sql) {
                try { $this->conn->exec($sql); } catch (\Exception $ex) {}
            }

            // Asegurar usuario admin con contraseña '1234' y rol 'admin'
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM USERS WHERE username = 'admin'");
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $hash = password_hash('1234', PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("INSERT INTO USERS (username, email, password_hash, role) VALUES ('admin', 'admin@monitor.com', ?, 'admin')");
                $stmt->execute([$hash]);
            } else {
                $this->conn->exec("UPDATE USERS SET role = 'admin' WHERE username = 'admin'");
            }

            // Asegurar hosts por defecto
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM HOSTS");
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $this->conn->exec("
                    INSERT INTO HOSTS (host_id, hostname, ip_address, operating_system) VALUES 
                    (1, 'Evangelin (Mi PC)', '127.0.0.1', 'Windows 11 (AMD64)'),
                    (2, 'Servidor-BaseDatos', '192.168.1.101', 'Debian 12 Bookworm'),
                    (3, 'Servidor-Worker', '192.168.1.102', 'Fedora 40 Server');

                    INSERT INTO THRESHOLDS (host_id, metric_name, comparison_operator, threshold_value, is_active) VALUES
                    (1, 'cpu_usage_pct', '>', 80.00, 1),
                    (1, 'ram_usage_pct', '>', 85.00, 1),
                    (1, 'disk_usage_pct', '>', 90.00, 1);
                ");
            }

            return $this->conn;
        } catch (\Exception $ex) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            echo json_encode([
                "Error" => [
                    "Type" => 500,
                    "Category" => 2,
                    "Description" => "Error de base de datos: " . $ex->getMessage()
                ]
            ]);
            exit;
        }
    }
}