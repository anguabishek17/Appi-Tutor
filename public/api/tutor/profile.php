<?php declare(strict_types=1);

/**
 * Tutor API: Get or Update Tutor Profile
 * GET: Retrieve own profile details
 * POST: Update own profile details
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor(true);
$db = Connection::getInstance();
$userId = (int)$user['id'];
$tutorProfileId = (int)($user['tutor_profile_id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $db->prepare('
        SELECT 
            tp.id AS tutor_profile_id,
            tp.user_id,
            tp.headline,
            tp.bio,
            tp.hourly_rate,
            tp.experience_years,
            tp.qualifications,
            tp.teaching_mode,
            tp.approval_status,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.avatar_url
        FROM tutor_profiles tp
        JOIN users u ON tp.user_id = u.id
        WHERE tp.user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        ResponseService::error('Tutor profile not found', 404);
    }

    ResponseService::json(['profile' => $profile], 'Profile fetched successfully');
}

if ($method === 'POST') {
    AuthMiddleware::requireCsrf();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $firstName = trim((string)($input['first_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $headline = trim((string)($input['headline'] ?? ''));
    $bio = trim((string)($input['bio'] ?? ''));
    $qualifications = trim((string)($input['qualifications'] ?? ''));
    $teachingMode = strtoupper(trim((string)($input['teaching_mode'] ?? 'BOTH')));
    $hourlyRate = filter_var($input['hourly_rate'] ?? 35.00, FILTER_VALIDATE_FLOAT);
    $experienceYears = filter_var($input['experience_years'] ?? 1, FILTER_VALIDATE_INT);
    $avatarUrl = trim((string)($input['avatar_url'] ?? ''));

    $errors = [];
    if (empty($firstName) || strlen($firstName) > 100) {
        $errors['first_name'] = 'First name is required (max 100 characters).';
    }
    if (empty($lastName) || strlen($lastName) > 100) {
        $errors['last_name'] = 'Last name is required (max 100 characters).';
    }
    if (strlen($headline) > 255) {
        $errors['headline'] = 'Headline cannot exceed 255 characters.';
    }
    if ($hourlyRate === false || $hourlyRate < 10.00 || $hourlyRate > 500.00) {
        $errors['hourly_rate'] = 'Hourly rate must be between £10.00 and £500.00.';
    }
    if ($experienceYears === false || $experienceYears < 0 || $experienceYears > 60) {
        $errors['experience_years'] = 'Years of experience must be between 0 and 60.';
    }
    if (!in_array($teachingMode, ['ONLINE', 'IN_PERSON', 'BOTH'], true)) {
        $errors['teaching_mode'] = 'Invalid teaching mode. Choose ONLINE, IN_PERSON, or BOTH.';
    }

    if (!empty($errors)) {
        ResponseService::error('Validation failed', 422, $errors);
    }

    // 1. Update users table (first_name, last_name, phone, avatar_url)
    $userStmt = $db->prepare('
        UPDATE users 
        SET first_name = :first_name,
            last_name = :last_name,
            phone = :phone,
            avatar_url = :avatar_url,
            updated_at = NOW()
        WHERE id = :id
    ');
    $userStmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':phone' => $phone !== '' ? $phone : null,
        ':avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        ':id' => $userId,
    ]);

    // 2. Update tutor_profiles table
    $tutorStmt = $db->prepare('
        UPDATE tutor_profiles 
        SET headline = :headline,
            bio = :bio,
            qualifications = :qualifications,
            hourly_rate = :hourly_rate,
            experience_years = :experience_years,
            teaching_mode = :teaching_mode,
            updated_at = NOW()
        WHERE user_id = :user_id
    ');
    $tutorStmt->execute([
        ':headline' => $headline !== '' ? $headline : null,
        ':bio' => $bio !== '' ? $bio : null,
        ':qualifications' => $qualifications !== '' ? $qualifications : null,
        ':hourly_rate' => $hourlyRate,
        ':experience_years' => $experienceYears,
        ':teaching_mode' => $teachingMode,
        ':user_id' => $userId,
    ]);

    // Refresh session to sync name/avatar changes
    \App\Auth\AuthService::refreshSession();

    ResponseService::json([
        'user_id' => $userId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'headline' => $headline,
        'hourly_rate' => $hourlyRate,
        'teaching_mode' => $teachingMode
    ], 'Profile updated successfully');
}

ResponseService::error('Method Not Allowed', 405);
