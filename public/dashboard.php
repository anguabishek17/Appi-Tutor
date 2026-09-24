<?php
declare(strict_types=1);

/**
 * Public Page: Central Dashboard Router
 * Directs authenticated users to their designated role portal
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\ResponseService;

$user = AuthService::user();

if (!$user) {
    ResponseService::redirect('/login.php', 'Please log in to access your dashboard.', 'warning');
}

// Refresh status from database
$refreshedUser = AuthService::refreshSession() ?? $user;

switch ($refreshedUser['role']) {
    case 'MANAGER':
        header('Location: /manager/dashboard.php');
        exit;

    case 'TUTOR':
        if (($refreshedUser['tutor_approval_status'] ?? '') === 'APPROVED') {
            header('Location: /tutor/dashboard.php');
        } else {
            header('Location: /tutor/pending.php');
        }
        exit;

    case 'STUDENT_PARENT':
        header('Location: /parent/dashboard.php');
        exit;

    default:
        ResponseService::redirect('/login.php', 'Invalid role configuration.', 'error');
}
