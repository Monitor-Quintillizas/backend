<?php

declare(strict_types=1);

namespace App\Utils;

class ValueValidation
{
    /**
     * Valida que un ID sea un número entero positivo.
     */
    public static function validateId(mixed $val, bool $allowMinusOne = false): int
    {
        if (!is_numeric($val)) {
            return 0; // 0 representará un ID inválido
        }
        
        $id = (int) $val;
        
        if ($id <= 0 && !($allowMinusOne && $id === -1)) {
            return 0;
        }
        
        return $id;
    }

    /**
     * Valida que un texto cumpla con una longitud mínima y máxima.
     */
    public static function validateString(mixed $val, int $minLength = 1, int $maxLength = 255): ?string
    {
        if (!is_string($val)) {
            return null;
        }
        
        $cleanedVal = trim($val);
        $length = strlen($cleanedVal);
        
        if ($length < $minLength || $length > $maxLength) {
            return null;
        }
        
        return $cleanedVal;
    }

    /**
     * Valida correos electrónicos.
     */
    public static function validateEmail(mixed $val): ?string
    {
        if (!is_string($val)) {
            return null;
        }
        
        $email = trim($val);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        return $email;
    }
    /**
     * Valida que una fecha tenga el formato correcto (por defecto YYYY-MM-DD HH:MM:SS).
     */
    public static function validateDate(mixed $val, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!is_string($val)) {
            return null;
        }
        
        $d = \DateTime::createFromFormat($format, $val);
        // Comprueba si se pudo crear la fecha y si coincide exactamente con el formato ingresado
        if ($d && $d->format($format) === $val) {
            return $val;
        }
        
        return null;
    }
    /**
     * Valida que un valor exista dentro de una lista de opciones permitidas (ENUM).
     */
    public static function validateInList(mixed $val, array $allowedValues): ?string
    {
        if (!is_string($val) || !in_array($val, $allowedValues, true)) {
            return null;
        }
        
        return $val;
    }

    /**
     * Valida un porcentaje numérico en el rango [0, 100].
     */
    public static function validatePercentage(mixed $val): ?float
    {
        if (is_bool($val) || (!is_int($val) && !is_float($val) && !is_string($val))) {
            return null;
        }

        if (is_string($val) && trim($val) === '') {
            return null;
        }

        if (!is_numeric($val)) {
            return null;
        }

        $num = (float) $val;
        if (!is_finite($num) || $num < 0.0 || $num > 100.0) {
            return null;
        }

        return round($num, 2);
    }

    /**
     * Valida un número decimal mayor o igual a 0.
     */
    public static function validateNonNegativeFloat(mixed $val): ?float
    {
        if (is_bool($val) || (!is_int($val) && !is_float($val) && !is_string($val))) {
            return null;
        }

        if (is_string($val) && trim($val) === '') {
            return null;
        }

        if (!is_numeric($val)) {
            return null;
        }

        $num = (float) $val;
        if (!is_finite($num) || $num < 0.0) {
            return null;
        }

        return round($num, 2);
    }

    /**
     * Valida un entero mayor o igual a 0.
     */
    public static function validateNonNegativeInt(mixed $val): ?int
    {
        if (is_bool($val) || (!is_int($val) && !is_float($val) && !is_string($val))) {
            return null;
        }

        if (is_string($val) && trim($val) === '') {
            return null;
        }

        if (!is_numeric($val)) {
            return null;
        }

        $num = (float) $val;
        if (!is_finite($num) || $num < 0 || floor($num) !== $num) {
            return null;
        }

        return (int) $num;
    }
}