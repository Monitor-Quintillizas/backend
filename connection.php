<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private string $host = 'db';
    private string $dbName = 'monitor_team_db';
    private string $username = 'root';
    private string $password = 'root';
    private ?PDO $conn = null;

    public function getConnection(): ?PDO
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            // El manejo de errores se delegará posteriormente a Errors.php
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "Error" => [
                    "Type" => 500,
                    "Category" => "Database",
                    "Description" => "Connection failed."
                ]
            ]);
            exit;
        }

        return $this->conn;
    }
}