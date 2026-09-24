<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Standardized JSON API Response Helper
 */
class ResponseService
{
    /**
     * Send JSON success response
     */
    public static function json(mixed $data = null, string $message = 'Success', int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send JSON error response
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect with optional flash message
     */
    public static function redirect(string $url, ?string $flashMessage = null, string $flashType = 'info'): void
    {
        if ($flashMessage !== null && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash'] = [
                'type' => $flashType,
                'message' => $flashMessage,
            ];
        }

        header("Location: {$url}");
        exit;
    }
}
