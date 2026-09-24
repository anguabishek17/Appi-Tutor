<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Services\ResponseService;
use App\Services\CsrfService;

/**
 * Core Request Guard & Authorization Middleware
 */
class AuthMiddleware
{
    /**
     * Require the user to be authenticated
     */
    public static function requireAuth(bool $isApi = false): array
    {
        $user = AuthService::user();

        if (!$user) {
            if ($isApi) {
                ResponseService::error('Unauthorized. Authentication required.', 401);
            }
            ResponseService::redirect('/login.php', 'Please log in to continue.', 'warning');
        }

        return $user;
    }

    /**
     * Require the user to have at least one of the specified roles
     */
    public static function requireRole(array $roles, bool $isApi = false): array
    {
        $user = self::requireAuth($isApi);

        if (!in_array($user['role'], $roles, true)) {
            if ($isApi) {
                ResponseService::error('Forbidden. Insufficient permissions.', 403);
            }
            ResponseService::redirect('/unauthorized.php', 'You do not have access to this area.', 'error');
        }

        return $user;
    }

    /**
     * Require the tutor to have an APPROVED status.
     * If PENDING or REJECTED, redirect or return error.
     */
    public static function requireApprovedTutor(bool $isApi = false): array
    {
        $user = self::requireRole(['TUTOR'], $isApi);

        // Refresh to check newest approval status
        $refreshedUser = AuthService::refreshSession() ?? $user;

        if (($refreshedUser['tutor_approval_status'] ?? '') !== 'APPROVED') {
            if ($isApi) {
                ResponseService::error('Tutor account is pending manager approval.', 403, [
                    'approval_status' => $refreshedUser['tutor_approval_status'] ?? 'PENDING'
                ]);
            }
            ResponseService::redirect('/tutor/pending.php');
        }

        return $refreshedUser;
    }

    /**
     * Require CSRF validation for state-changing requests (POST, PUT, DELETE, PATCH)
     */
    public static function requireCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!CsrfService::validate()) {
                ResponseService::error('Invalid or expired CSRF token. Please refresh the page and try again.', 419);
            }
        }
    }
}
