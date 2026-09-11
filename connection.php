<?php
// connection.php

class Connection {
    private $host = 'db'; 
    private $db_name = 'monitor_team_db';
    private $username = 'root'; 
    private $password = 'root'; 
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Configurar PDO para que lance excepciones en caso de errores SQL
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Configurar el retorno de datos como arrays asociativos por defecto
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            // Devolver error en formato JSON para que el Frontend lo interprete
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                "ok" => false, 
                "mensaje" => "Error de conexión a la base de datos: " . $exception->getMessage()
            ]);
            exit;
        }

        return $this->conn;
    }
}
?>