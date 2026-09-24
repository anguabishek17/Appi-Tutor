<?php
declare(strict_types=1);

/**
 * Public Endpoint: Session Sync / Backend Login
 * Method: POST
 * Accepts: JSON payload { idToken, email, firstName, lastName, requestedRole }
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Auth\AuthService;
use App\Auth\FirebaseVerifier;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseService::error('Method not allowed', 405);
}

// 1. Verify CSRF Token
\App\Middleware\AuthMiddleware::requireCsrf();

// 2. Parse JSON Input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$idToken = trim($input['idToken'] ?? '');
$requestedRole = trim($input['requestedRole'] ?? 'STUDENT_PARENT');
$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');

if (empty($idToken)) {
    ResponseService::error('Firebase ID token is required', 400);
}

// 2. Verify Firebase ID Token
$config = require CONFIG_PATH . '/app.php';
$verifier = new FirebaseVerifier($config['firebase']['project_id']);
$verifiedToken = $verifier->verifyIdToken($idToken);

if (!$verifiedToken || empty($verifiedToken['uid'])) {
    ResponseService::error('Invalid or expired Firebase ID token. Please authenticate again.', 401);
}

$firebaseUid = $verifiedToken['uid'];
$email = $verifiedToken['email'] ?? '';
$avatarUrl = $verifiedToken['picture'] ?? null;

// Use name from token claims if first/last name not supplied directly
if (empty($firstName) && !empty($verifiedToken['name'])) {
    $parts = explode(' ', trim($verifiedToken['name']), 2);
    $firstName = $parts[0] ?? 'User';
    $lastName = $parts[1] ?? '';
}

if (empty($firstName)) {
    $firstName = 'User';
}

// 3. Authenticate / Sync Database User and establish secure PHP session
try {
    $sessionUser = AuthService::loginWithFirebase(
        $firebaseUid,
        $email,
        $firstName,
        $lastName,
        $requestedRole,
        $avatarUrl
    );

    // Determine target redirect based on verified server role and tutor approval state
    $redirectUrl = '/dashboard.php';
    if ($sessionUser['role'] === 'MANAGER') {
        $redirectUrl = '/manager/dashboard.php';
    } elseif ($sessionUser['role'] === 'TUTOR') {
        if ($sessionUser['tutor_approval_status'] === 'APPROVED') {
            $redirectUrl = '/tutor/dashboard.php';
        } else {
            $redirectUrl = '/tutor/pending.php';
        }
    } elseif ($sessionUser['role'] === 'STUDENT_PARENT') {
        $redirectUrl = '/parent/dashboard.php';
    }

    ResponseService::json([
        'user' => $sessionUser,
        'redirect' => $redirectUrl,
        'csrf_token' => CsrfService::getToken(),
    ], 'Authentication successful');

} catch (Exception $e) {
    error_log('[Auth Error]: ' . $e->getMessage());
    ResponseService::error('Authentication error: ' . $e->getMessage(), 500);
}
