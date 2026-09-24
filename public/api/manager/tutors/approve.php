<?php
declare(strict_types=1);

/**
 * Manager API: Approve Tutor Profile
 * Method: POST
 * Query / Payload: { id: tutor_profile_id }
 */

require_once __DIR__ . '/../../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require Manager Role & CSRF
$manager = AuthMiddleware::requireRole(['MANAGER'], true);
AuthMiddleware::requireCsrf();

// 2. Resolve Tutor Profile ID
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$tutorProfileId = (int)($_GET['id'] ?? $input['id'] ?? 0);

if ($tutorProfileId <= 0) {
    ResponseService::error('Valid tutor profile ID is required', 400);
}

$db = Connection::getInstance();

// 3. Verify existence of tutor profile
$checkStmt = $db->prepare('SELECT id, user_id, approval_status FROM tutor_profiles WHERE id = :id LIMIT 1');
$checkStmt->execute([':id' => $tutorProfileId]);
$tutor = $checkStmt->fetch();

if (!$tutor) {
    ResponseService::error('Tutor profile not found', 404);
}

// 4. Update status to APPROVED
$updateStmt = $db->prepare('
    UPDATE tutor_profiles 
    SET approval_status = "APPROVED", approved_by = :manager_id, updated_at = NOW()
    WHERE id = :id
');
$updateStmt->execute([
    ':manager_id' => $manager['id'],
    ':id' => $tutorProfileId,
]);

// 5. Audit Log
$auditStmt = $db->prepare('
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
    VALUES (:user_id, "TUTOR_APPROVED", "tutor_profiles", :entity_id, :details, :ip, :ua)
');
$auditStmt->execute([
    ':user_id' => $manager['id'],
    ':entity_id' => $tutorProfileId,
    ':details' => json_encode(['previous_status' => $tutor['approval_status'], 'new_status' => 'APPROVED']),
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
]);

ResponseService::json([
    'tutor_profile_id' => $tutorProfileId,
    'approval_status' => 'APPROVED'
], 'Tutor approved successfully');
