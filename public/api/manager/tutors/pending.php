<?php
declare(strict_types=1);

/**
 * Manager API: List Pending Tutors
 * Method: GET
 */

require_once __DIR__ . '/../../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// Require MANAGER role
$manager = AuthMiddleware::requireRole(['MANAGER'], true);

$db = Connection::getInstance();
$stmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.user_id,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.approval_status,
        tp.created_at AS application_date,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.avatar_url
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = "PENDING"
    ORDER BY tp.created_at ASC
');
$stmt->execute();
$pendingTutors = $stmt->fetchAll();

ResponseService::json([
    'tutors' => $pendingTutors,
    'count' => count($pendingTutors)
], 'Pending tutors retrieved successfully');
