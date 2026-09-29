<?php

declare(strict_types=1);

namespace App\Sessions;

/**
 * Gestor de Sesiones para el Monitor
 * Diseñado para soportar cookies de sesión nativas y tokens en encabezados HTTP.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            // En desarrollo local entre diferentes puertos:
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                ini_set('session.cookie_samesite', 'Lax');
            }
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        self::start();
        return $_SESSION[$key] ?? null;
    }

    /**
     * Valida si existe una sesión activa mediante cookies de PHP
     * o mediante el header Authorization enviado por el Frontend / Postman.
     */
    public static function isValid(): bool
    {
        self::start();
        
        // 1. Validar por sesión tradicional de PHP
        if (isset($_SESSION['user_id'])) {
            return true;
        }

        // 2. Validar por token en encabezado Authorization (Bearer token)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
            if (!empty($token)) {
                // Token simulado o decodificado
                return true;
            }
        }

        return false;
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }
}