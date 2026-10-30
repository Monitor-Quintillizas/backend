<?php

declare(strict_types=1);

namespace App\Utils;

class Errors
{
    /**
     * Formato base para devolver errores estandarizados
     */
    private static function send(int $httpCode, int $type, int $category, string $description): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        
        echo json_encode([
            "Error" => [
                "Type" => $type,
                "Category" => $category,
                "Description" => $description
            ]
        ]);
        
        exit;
    }

    public static function badRequest(string $message = "Parámetros faltantes o inválidos."): void
    {
        self::send(400, 1, 1, $message);
    }

    public static function unauthorized(string $message = "Sesión inválida o usuario no autenticado."): void
    {
        self::send(401, 2, 1, $message);
    }

    public static function databaseError(string $message = "Error en la base de datos."): void
    {
        self::send(500, 3, 2, $message);
    }
    
    public static function notFound(string $message = "Recurso no encontrado."): void
    {
        self::send(404, 4, 3, $message);
    }

    public static function forbidden(string $message = "Acceso no permitido."): void
    {
        self::send(403, 5, 1, $message);
    }
}