<?php declare(strict_types=1);

/**
 * Public API: Get Tutor Detail & Available Slots
 * Method: GET
 * Query Param: id (tutor_profile_id)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

$tutorProfileId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$tutorProfileId || $tutorProfileId <= 0) {
    ResponseService::error('Valid tutor ID is required', 400);
}

$db = Connection::getInstance();

// 1. Fetch public profile of APPROVED tutor only
$stmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.teaching_mode,
        tp.is_featured
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.id = :id 
      AND tp.approval_status = "APPROVED" 
      AND u.status = "ACTIVE"
    LIMIT 1
');
$stmt->execute([':id' => $tutorProfileId]);
$tutor = $stmt->fetch();

if (!$tutor) {
    ResponseService::error('Tutor not found or is not publicly available', 404);
}

// 2. Fetch tutor approved subjects
$subjStmt = $db->prepare('
    SELECT 
        s.id AS subject_id,
        s.name AS subject_name,
        s.slug AS subject_slug,
        c.name AS curriculum_name,
        c.code AS curriculum_code
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    WHERE ts.tutor_profile_id = :tpid
    ORDER BY c.id ASC, s.name ASC
');
$subjStmt->execute([':tpid' => $tutorProfileId]);
$subjects = $subjStmt->fetchAll();

// 3. Fetch upcoming available slots (Strictly NOT blocked, NOT fully booked, and in the future)
$slotStmt = $db->prepare('
    SELECT 
        id AS slot_id,
        start_time,
        end_time,
        session_type,
        delivery_mode,
        max_capacity,
        booked_count,
        (max_capacity - booked_count) AS spaces_left
    FROM availability_slots
    WHERE tutor_profile_id = :tpid
      AND is_blocked = 0
      AND start_time > NOW()
      AND booked_count < max_capacity
    ORDER BY start_time ASC
');
$slotStmt->execute([':tpid' => $tutorProfileId]);
$slots = $slotStmt->fetchAll();

ResponseService::json([
    'tutor' => [
        'id' => (int)$tutor['tutor_profile_id'],
        'first_name' => $tutor['first_name'],
        'last_name' => $tutor['last_name'],
        'avatar_url' => $tutor['avatar_url'],
        'headline' => $tutor['headline'],
        'bio' => $tutor['bio'],
        'qualifications' => $tutor['qualifications'],
        'hourly_rate' => (float)$tutor['hourly_rate'],
        'experience_years' => (int)$tutor['experience_years'],
        'teaching_mode' => $tutor['teaching_mode'],
        'is_featured' => (bool)$tutor['is_featured'],
    ],
    'subjects' => $subjects,
    'available_slots' => $slots,
    'total_slots' => count($slots)
], 'Tutor details retrieved successfully');
