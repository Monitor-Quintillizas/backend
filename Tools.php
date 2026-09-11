<?php

declare(strict_types=1);

namespace App\Utils;

class Tools
{
    /**
     * Ejecuta el script de Python y devuelve un array con las métricas.
     */
    public static function getPythonMetrics(): ?array
    {
        // shell_exec ejecuta un comando en la terminal del contenedor
        $output = shell_exec('python3 python/monitor.py');
        
        if (!$output) {
            return null;
        }

        // Convertir el JSON de Python a un array asociativo de PHP
        $metrics = json_decode(trim($output), true);
        
        return $metrics ?: null;
    }
}