<?php declare(strict_types=1);

/**
 * Tutor API: Manage Subjects
 * GET: List all available system subjects & curricula alongside tutor's selected subject IDs
 * POST: Update tutor's selected subjects
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

// Resolve tutor_profile_id securely from DB
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $userId]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // 1. Fetch all curricula
    $cStmt = $db->query('SELECT id, name, code, description FROM curricula ORDER BY id ASC');
    $curricula = $cStmt->fetchAll();

    // 2. Fetch all subjects grouped by curriculum
    $sStmt = $db->query('
        SELECT s.id, s.curriculum_id, s.name, s.slug, c.name AS curriculum_name, c.code AS curriculum_code
        FROM subjects s
        JOIN curricula c ON s.curriculum_id = c.id
        ORDER BY s.curriculum_id ASC, s.name ASC
    ');
    $allSubjects = $sStmt->fetchAll();

    // 3. Fetch currently selected subject IDs for this tutor
    $tsStmt = $db->prepare('
        SELECT ts.subject_id, ts.custom_rate, s.name, s.curriculum_id
        FROM tutor_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.tutor_profile_id = :tpid
    ');
    $tsStmt->execute([':tpid' => $tutorProfileId]);
    $selectedSubjects = $tsStmt->fetchAll();
    $selectedSubjectIds = array_map(fn($row) => (int)$row['subject_id'], $selectedSubjects);

    ResponseService::json([
        'curricula' => $curricula,
        'all_subjects' => $allSubjects,
        'selected_subjects' => $selectedSubjects,
        'selected_subject_ids' => $selectedSubjectIds
    ], 'Subjects retrieved successfully');
}

if ($method === 'POST') {
    AuthMiddleware::requireCsrf();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $subjectIds = $input['subject_ids'] ?? [];

    if (!is_array($subjectIds)) {
        ResponseService::error('Invalid subject_ids format. Expected array of integers.', 422);
    }

    // Clean and validate subject IDs
    $validIds = [];
    foreach ($subjectIds as $id) {
        $idInt = filter_var($id, FILTER_VALIDATE_INT);
        if ($idInt !== false && $idInt > 0) {
            $validIds[] = $idInt;
        }
    }
    $validIds = array_values(array_unique($validIds));

    // Verify all submitted subject IDs actually exist in subjects table (Prevent arbitrary IDs)
    if (!empty($validIds)) {
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $checkStmt = $db->prepare("SELECT id FROM subjects WHERE id IN ($placeholders)");
        $checkStmt->execute($validIds);
        $existingDbIds = $checkStmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // Filter strictly to existing IDs
        $validIds = array_map('intval', $existingDbIds);
    }

    // Perform atomic update inside transaction
    $db->beginTransaction();
    try {
        // Remove previous mappings for this tutor
        $delStmt = $db->prepare('DELETE FROM tutor_subjects WHERE tutor_profile_id = :tpid');
        $delStmt->execute([':tpid' => $tutorProfileId]);

        // Insert new selected subjects
        if (!empty($validIds)) {
            $insertStmt = $db->prepare('INSERT INTO tutor_subjects (tutor_profile_id, subject_id) VALUES (:tpid, :sid)');
            foreach ($validIds as $sid) {
                $insertStmt->execute([
                    ':tpid' => $tutorProfileId,
                    ':sid' => $sid
                ]);
            }
        }

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        ResponseService::error('Failed to update subjects: ' . $e->getMessage(), 500);
    }

    ResponseService::json([
        'tutor_profile_id' => $tutorProfileId,
        'selected_subject_ids' => $validIds,
        'count' => count($validIds)
    ], 'Tutor subjects updated successfully');
}

ResponseService::error('Method Not Allowed', 405);
