<?php
declare(strict_types=1);

require_once 'connection.php';

use App\Database\Connection;

try {
    $db = (new Connection())->getConnection();
    
    $hashReal = password_hash("1234", PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE USERS SET password_hash = :hash WHERE username = 'admin'");
    $stmt->bindParam(':hash', $hashReal);
    $stmt->execute();
    
    echo "¡Listo! La contraseña se actualizó correctamente en la Base de Datos.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}