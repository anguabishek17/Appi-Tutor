<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Cross-Site Request Forgery Protection Service
 */
class CsrfService
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate or return existing CSRF token for the session
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Render hidden HTML input containing CSRF token
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Verify CSRF token from POST or X-CSRF-Token header
     */
    public static function validate(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (empty($sessionToken)) {
            return false;
        }

        // If no token passed, look in POST data, JSON input, or HTTP header
        if ($token === null) {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true);
            $token = $_POST['csrf_token'] ?? $jsonData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        return hash_equals($sessionToken, (string)$token);
    }
}
