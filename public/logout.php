<?php
declare(strict_types=1);

/**
 * Public Page: Logout Action
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\ResponseService;

AuthService::logout();
ResponseService::redirect('/login.php', 'You have been safely signed out.', 'info');
