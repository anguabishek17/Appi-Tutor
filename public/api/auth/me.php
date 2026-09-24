<?php
declare(strict_types=1);

/**
 * Public Endpoint: User Identity & Active Session Inspector
 * Method: GET
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

$user = AuthService::user();

if (!$user) {
    ResponseService::error('Not authenticated', 401);
}

// Refresh latest profile status from database
$refreshedUser = AuthService::refreshSession() ?? $user;

ResponseService::json([
    'user' => $refreshedUser,
    'csrf_token' => CsrfService::getToken(),
], 'Session active');
