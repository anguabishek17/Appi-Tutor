<?php declare(strict_types=1);

/**
 * Parent Lesson Summary API
 * GET /api/parent/lesson.php?booking_id={id}
 * 
 * Provides parent-safe lesson summary, attendance, and topics/homework.
 * Strips private tutor notes and internal audit data.
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT'], true);
$db = Connection::getInstance();

$bookingId = filter_var($_GET['booking_id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking_id is required', 422);
}

// 2. Fetch booking & parent-safe lesson notes
$stmt = $db->prepare('
    SELECT 
        b.id,
        b.booking_reference,
        b.parent_user_id,
        b.tutor_profile_id,
        b.student_child_id,
        b.scheduled_start,
        b.scheduled_end,
        b.status,
        b.attendance_status,
        b.hourly_rate,
        b.total_amount,
        b.meeting_link,
        u_tutor.first_name AS tutor_first_name,
        u_tutor.last_name AS tutor_last_name,
        sc.first_name AS child_first_name,
        sc.last_name AS child_last_name,
        s.name AS subject_name,
        ln.lesson_summary,
        ln.topics_covered,
        ln.student_progress,
        ln.student_progress_rating,
        ln.homework_assigned,
        ln.parent_feedback_notes,
        ln.next_lesson_focus,
        ln.updated_at AS notes_updated_at
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    JOIN subjects s ON b.subject_id = s.id
    LEFT JOIN lesson_notes ln ON b.id = ln.booking_id
    WHERE b.id = :bid
    LIMIT 1
');
$stmt->execute([':bid' => $bookingId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    ResponseService::error('Lesson details not found', 404);
}

// 3. Strict IDOR Check: Booking must belong to authenticated parent
if ((int)$lesson['parent_user_id'] !== (int)$user['id']) {
    ResponseService::error('Forbidden. You do not have permission to view this lesson summary.', 403);
}

// Return strictly parent-safe data (NO private_tutor_notes, NO internal audit data)
ResponseService::json([
    'booking_id' => (int)$lesson['id'],
    'booking_reference' => $lesson['booking_reference'],
    'status' => $lesson['status'],
    'attendance_status' => $lesson['attendance_status'] ?? 'NOT_RECORDED',
    'scheduled_start' => $lesson['scheduled_start'],
    'scheduled_end' => $lesson['scheduled_end'],
    'subject_name' => $lesson['subject_name'],
    'tutor_name' => trim(($lesson['tutor_first_name'] ?? '') . ' ' . ($lesson['tutor_last_name'] ?? '')),
    'child_name' => !empty($lesson['child_first_name']) ? trim($lesson['child_first_name'] . ' ' . ($lesson['child_last_name'] ?? '')) : 'Self / General',
    'lesson_summary' => $lesson['lesson_summary'] ?? '',
    'topics_covered' => $lesson['topics_covered'] ?? '',
    'student_progress' => $lesson['student_progress'] ?? '',
    'student_progress_rating' => $lesson['student_progress_rating'] ? (int)$lesson['student_progress_rating'] : null,
    'homework_assigned' => $lesson['homework_assigned'] ?? '',
    'parent_feedback_notes' => $lesson['parent_feedback_notes'] ?? '',
    'next_lesson_focus' => $lesson['next_lesson_focus'] ?? '',
    'notes_updated_at' => $lesson['notes_updated_at'] ?? null,
]);
