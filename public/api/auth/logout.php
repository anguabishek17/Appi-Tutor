<?php
declare(strict_types=1);

/**
 * Public Endpoint: User Logout
 * Method: POST / GET
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\ResponseService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthService::logout();
    ResponseService::json(null, 'Logged out successfully');
} else {
    AuthService::logout();
    ResponseService::redirect('/login.php', 'You have been logged out.', 'info');
}
