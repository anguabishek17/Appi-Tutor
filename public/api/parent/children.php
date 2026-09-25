<?php declare(strict_types=1);

/**
 * Parent API: Child Management
 * Methods:
 *  - GET: List all children belonging to the authenticated parent
 *  - POST: Create a new child profile
 *  - PUT / PATCH: Update an existing child profile owned by this parent
 *  - DELETE: Delete a child profile owned by this parent (if no active bookings)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require STUDENT_PARENT role
$parent = AuthMiddleware::requireRole(['STUDENT_PARENT'], true);
$parentUserId = (int)$parent['id'];
$db = Connection::getInstance();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// -------------------------------------------------------------
// GET: List Parent's Own Children
// -------------------------------------------------------------
if ($method === 'GET') {
    $stmt = $db->prepare('
        SELECT 
            id,
            parent_user_id,
            first_name,
            last_name,
            date_of_birth,
            year_group,
            school_name,
            learning_goals,
            special_needs_notes,
            created_at,
            updated_at
        FROM students_children
        WHERE parent_user_id = :parent_id
        ORDER BY created_at ASC
    ');
    $stmt->execute([':parent_id' => $parentUserId]);
    $children = $stmt->fetchAll();

    ResponseService::json([
        'children' => $children,
        'count' => count($children)
    ], 'Children retrieved successfully');
}

// All mutating actions require CSRF validation
AuthMiddleware::requireCsrf();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

// -------------------------------------------------------------
// POST: Create Child
// -------------------------------------------------------------
if ($method === 'POST') {
    $firstName = trim((string)($input['first_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $dob = trim((string)($input['date_of_birth'] ?? ''));
    $yearGroup = trim((string)($input['year_group'] ?? ''));
    $schoolName = trim((string)($input['school_name'] ?? ''));
    $learningGoals = trim((string)($input['learning_goals'] ?? ''));
    $specialNeedsNotes = trim((string)($input['special_needs_notes'] ?? ''));

    $errors = [];
    if (empty($firstName) || strlen($firstName) > 100) {
        $errors['first_name'] = 'Child first name is required (max 100 characters).';
    }
    if (empty($lastName) || strlen($lastName) > 100) {
        $errors['last_name'] = 'Child last name is required (max 100 characters).';
    }
    if (!empty($dob)) {
        $d = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$d || $d->format('Y-m-d') !== $dob) {
            $errors['date_of_birth'] = 'Invalid date of birth format (YYYY-MM-DD required).';
        }
    }
    if (strlen($yearGroup) > 50) {
        $errors['year_group'] = 'Year group cannot exceed 50 characters.';
    }
    if (strlen($schoolName) > 150) {
        $errors['school_name'] = 'School name cannot exceed 150 characters.';
    }

    if (!empty($errors)) {
        ResponseService::error('Validation failed', 422, $errors);
    }

    $insertStmt = $db->prepare('
        INSERT INTO students_children 
        (parent_user_id, first_name, last_name, date_of_birth, year_group, school_name, learning_goals, special_needs_notes)
        VALUES (:parent_id, :first_name, :last_name, :dob, :year_group, :school_name, :learning_goals, :special_needs)
    ');
    $insertStmt->execute([
        ':parent_id' => $parentUserId,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':dob' => !empty($dob) ? $dob : null,
        ':year_group' => !empty($yearGroup) ? $yearGroup : null,
        ':school_name' => !empty($schoolName) ? $schoolName : null,
        ':learning_goals' => !empty($learningGoals) ? $learningGoals : null,
        ':special_needs' => !empty($specialNeedsNotes) ? $specialNeedsNotes : null,
    ]);

    $newChildId = (int)$db->lastInsertId();

    ResponseService::json([
        'child_id' => $newChildId,
        'parent_user_id' => $parentUserId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'year_group' => $yearGroup,
    ], 'Child profile created successfully', 201);
}

// -------------------------------------------------------------
// PUT / PATCH: Update Child
// -------------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH' || ($method === 'POST' && ($input['_method'] ?? '') === 'PUT')) {
    $childId = filter_var($input['id'] ?? $input['child_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$childId || $childId <= 0) {
        ResponseService::error('Valid child ID is required', 422);
    }

    // Verify ownership
    $checkStmt = $db->prepare('SELECT id, parent_user_id FROM students_children WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $childId]);
    $child = $checkStmt->fetch();

    if (!$child || (int)$child['parent_user_id'] !== $parentUserId) {
        ResponseService::error('Child profile not found or unauthorized access', 403);
    }

    $firstName = trim((string)($input['first_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $dob = trim((string)($input['date_of_birth'] ?? ''));
    $yearGroup = trim((string)($input['year_group'] ?? ''));
    $schoolName = trim((string)($input['school_name'] ?? ''));
    $learningGoals = trim((string)($input['learning_goals'] ?? ''));
    $specialNeedsNotes = trim((string)($input['special_needs_notes'] ?? ''));

    $errors = [];
    if (empty($firstName) || strlen($firstName) > 100) {
        $errors['first_name'] = 'Child first name is required (max 100 characters).';
    }
    if (empty($lastName) || strlen($lastName) > 100) {
        $errors['last_name'] = 'Child last name is required (max 100 characters).';
    }
    if (!empty($dob)) {
        $d = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$d || $d->format('Y-m-d') !== $dob) {
            $errors['date_of_birth'] = 'Invalid date of birth format (YYYY-MM-DD required).';
        }
    }
    if (strlen($yearGroup) > 50) {
        $errors['year_group'] = 'Year group cannot exceed 50 characters.';
    }
    if (strlen($schoolName) > 150) {
        $errors['school_name'] = 'School name cannot exceed 150 characters.';
    }

    if (!empty($errors)) {
        ResponseService::error('Validation failed', 422, $errors);
    }

    $updateStmt = $db->prepare('
        UPDATE students_children 
        SET first_name = :first_name,
            last_name = :last_name,
            date_of_birth = :dob,
            year_group = :year_group,
            school_name = :school_name,
            learning_goals = :learning_goals,
            special_needs_notes = :special_needs,
            updated_at = NOW()
        WHERE id = :id AND parent_user_id = :parent_id
    ');
    $updateStmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':dob' => !empty($dob) ? $dob : null,
        ':year_group' => !empty($yearGroup) ? $yearGroup : null,
        ':school_name' => !empty($schoolName) ? $schoolName : null,
        ':learning_goals' => !empty($learningGoals) ? $learningGoals : null,
        ':special_needs' => !empty($specialNeedsNotes) ? $specialNeedsNotes : null,
        ':id' => $childId,
        ':parent_id' => $parentUserId,
    ]);

    ResponseService::json([
        'child_id' => $childId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'year_group' => $yearGroup,
    ], 'Child profile updated successfully');
}

// -------------------------------------------------------------
// DELETE: Delete Child
// -------------------------------------------------------------
if ($method === 'DELETE' || ($method === 'POST' && ($input['_method'] ?? '') === 'DELETE')) {
    $childId = filter_var($input['id'] ?? $input['child_id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$childId || $childId <= 0) {
        ResponseService::error('Valid child ID is required', 422);
    }

    // Verify ownership
    $checkStmt = $db->prepare('SELECT id, parent_user_id FROM students_children WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $childId]);
    $child = $checkStmt->fetch();

    if (!$child || (int)$child['parent_user_id'] !== $parentUserId) {
        ResponseService::error('Child profile not found or unauthorized access', 403);
    }

    // Check if child has active pending or accepted bookings
    $bookingCheck = $db->prepare('
        SELECT COUNT(*) FROM bookings 
        WHERE student_child_id = :cid AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
    ');
    $bookingCheck->execute([':cid' => $childId]);
    if ((int)$bookingCheck->fetchColumn() > 0) {
        ResponseService::error('Cannot delete child profile with active or pending bookings.', 400);
    }

    $delStmt = $db->prepare('DELETE FROM students_children WHERE id = :id AND parent_user_id = :parent_id');
    $delStmt->execute([':id' => $childId, ':parent_id' => $parentUserId]);

    ResponseService::json(['child_id' => $childId], 'Child profile deleted successfully');
}

ResponseService::error('Method Not Allowed', 405);
