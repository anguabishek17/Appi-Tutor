<?php declare(strict_types=1);

/**
 * Lesson Notes API: Save, Update, and Retrieve Lesson Notes & Attendance for Tutors
 * Methods:
 *  - GET /api/tutor/lesson-notes.php?booking_id={id}
 *  - POST /api/tutor/lesson-notes.php
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED TUTOR role
$user = AuthMiddleware::requireApprovedTutor(true);
$db = Connection::getInstance();

// 2. Resolve tutor_profile_id securely
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $user['id']]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $bookingId = filter_var($_GET['booking_id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$bookingId || $bookingId <= 0) {
        ResponseService::error('Valid booking_id is required', 422);
    }

    // Fetch booking details & verify ownership
    $bStmt = $db->prepare('
        SELECT 
            b.id,
            b.booking_reference,
            b.tutor_profile_id,
            b.parent_user_id,
            b.student_child_id,
            b.subject_id,
            b.scheduled_start,
            b.scheduled_end,
            b.status,
            b.attendance_status,
            u_parent.first_name AS parent_first_name,
            u_parent.last_name AS parent_last_name,
            sc.first_name AS child_first_name,
            sc.last_name AS child_last_name,
            s.name AS subject_name,
            ln.id AS lesson_note_id,
            ln.lesson_summary,
            ln.topics_covered,
            ln.student_progress,
            ln.student_progress_rating,
            ln.homework_assigned,
            ln.parent_feedback_notes,
            ln.next_lesson_focus,
            ln.private_tutor_notes,
            ln.created_at AS note_created_at,
            ln.updated_at AS note_updated_at
        FROM bookings b
        JOIN users u_parent ON b.parent_user_id = u_parent.id
        LEFT JOIN students_children sc ON b.student_child_id = sc.id
        JOIN subjects s ON b.subject_id = s.id
        LEFT JOIN lesson_notes ln ON b.id = ln.booking_id
        WHERE b.id = :bid
        LIMIT 1
    ');
    $bStmt->execute([':bid' => $bookingId]);
    $booking = $bStmt->fetch();

    if (!$booking) {
        ResponseService::error('Booking not found', 404);
    }

    // IDOR Protection: Must belong to authenticated tutor
    if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
        ResponseService::error('Forbidden. You do not have access to this booking.', 403);
    }

    ResponseService::json([
        'booking' => [
            'id' => (int)$booking['id'],
            'booking_reference' => $booking['booking_reference'],
            'status' => $booking['status'],
            'attendance_status' => $booking['attendance_status'] ?? 'NOT_RECORDED',
            'scheduled_start' => $booking['scheduled_start'],
            'scheduled_end' => $booking['scheduled_end'],
            'subject_name' => $booking['subject_name'],
            'parent_name' => trim(($booking['parent_first_name'] ?? '') . ' ' . ($booking['parent_last_name'] ?? '')),
            'child_name' => !empty($booking['child_first_name']) ? trim($booking['child_first_name'] . ' ' . ($booking['child_last_name'] ?? '')) : 'Self / General',
        ],
        'notes' => [
            'id' => $booking['lesson_note_id'] ? (int)$booking['lesson_note_id'] : null,
            'lesson_summary' => $booking['lesson_summary'] ?? '',
            'topics_covered' => $booking['topics_covered'] ?? '',
            'student_progress' => $booking['student_progress'] ?? '',
            'student_progress_rating' => $booking['student_progress_rating'] ? (int)$booking['student_progress_rating'] : null,
            'homework_assigned' => $booking['homework_assigned'] ?? '',
            'parent_feedback_notes' => $booking['parent_feedback_notes'] ?? '',
            'next_lesson_focus' => $booking['next_lesson_focus'] ?? '',
            'private_tutor_notes' => $booking['private_tutor_notes'] ?? '',
            'updated_at' => $booking['note_updated_at'] ?? null,
        ]
    ]);
}

if ($method === 'POST') {
    AuthMiddleware::requireCsrf();

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    $bookingId = filter_var($input['booking_id'] ?? $input['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$bookingId || $bookingId <= 0) {
        ResponseService::error('Valid booking_id is required', 422);
    }

    $attendanceStatus = trim((string)($input['attendance_status'] ?? ''));
    $validAttendance = ['NOT_RECORDED', 'ATTENDED', 'PARTIAL', 'ABSENT'];
    if ($attendanceStatus !== '' && !in_array($attendanceStatus, $validAttendance, true)) {
        ResponseService::error('Invalid attendance status. Must be ATTENDED, PARTIAL, ABSENT, or NOT_RECORDED.', 422);
    }

    $lessonSummary = isset($input['lesson_summary']) ? trim((string)$input['lesson_summary']) : null;
    $topicsCovered = isset($input['topics_covered']) ? trim((string)$input['topics_covered']) : null;
    $studentProgress = isset($input['student_progress']) ? trim((string)$input['student_progress']) : null;
    $studentProgressRating = isset($input['student_progress_rating']) && $input['student_progress_rating'] !== ''
        ? filter_var($input['student_progress_rating'], FILTER_VALIDATE_INT)
        : null;
    if ($studentProgressRating !== null && ($studentProgressRating < 1 || $studentProgressRating > 5)) {
        ResponseService::error('Progress rating must be an integer between 1 and 5.', 422);
    }

    $homeworkAssigned = isset($input['homework_assigned']) ? trim((string)$input['homework_assigned']) : null;
    $parentFeedback = isset($input['parent_feedback_notes']) ? trim((string)$input['parent_feedback_notes']) : null;
    $nextLessonFocus = isset($input['next_lesson_focus']) ? trim((string)$input['next_lesson_focus']) : null;
    $privateNotes = isset($input['private_tutor_notes']) ? trim((string)$input['private_tutor_notes']) : null;

    $db->beginTransaction();

    try {
        $stmt = $db->prepare('
            SELECT id, booking_reference, tutor_profile_id, status, attendance_status
            FROM bookings
            WHERE id = :bid
            FOR UPDATE
        ');
        $stmt->execute([':bid' => $bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $db->rollBack();
            ResponseService::error('Booking not found', 404);
        }

        // Ownership check
        if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
            $db->rollBack();
            ResponseService::error('Forbidden. You cannot edit notes for another tutor\'s booking.', 403);
        }

        // Booking status check: Must not be CANCELLED, REJECTED, or SYSTEM_CANCELLED
        $forbiddenStatuses = ['CANCELLED', 'REJECTED', 'SYSTEM_CANCELLED'];
        if (in_array($booking['status'], $forbiddenStatuses, true)) {
            $db->rollBack();
            ResponseService::error("Cannot record lesson notes for {$booking['status']} bookings.", 422);
        }

        // Update attendance on bookings table if provided
        if ($attendanceStatus !== '') {
            $attStmt = $db->prepare('UPDATE bookings SET attendance_status = :att, updated_at = NOW() WHERE id = :bid');
            $attStmt->execute([':att' => $attendanceStatus, ':bid' => $bookingId]);
        }

        // Check if lesson_notes record already exists
        $noteStmt = $db->prepare('SELECT id FROM lesson_notes WHERE booking_id = :bid FOR UPDATE');
        $noteStmt->execute([':bid' => $bookingId]);
        $existingNote = $noteStmt->fetch();

        if ($existingNote) {
            $updateSql = '
                UPDATE lesson_notes SET
                    lesson_summary = COALESCE(:summary, lesson_summary),
                    topics_covered = COALESCE(:topics, topics_covered),
                    student_progress = COALESCE(:progress, student_progress),
                    student_progress_rating = COALESCE(:rating, student_progress_rating),
                    homework_assigned = COALESCE(:homework, homework_assigned),
                    parent_feedback_notes = COALESCE(:feedback, parent_feedback_notes),
                    next_lesson_focus = COALESCE(:next_focus, next_lesson_focus),
                    private_tutor_notes = COALESCE(:private_notes, private_tutor_notes),
                    updated_at = NOW()
                WHERE id = :nid
            ';
            $upNoteStmt = $db->prepare($updateSql);
            $upNoteStmt->execute([
                ':summary' => $lessonSummary,
                ':topics' => $topicsCovered,
                ':progress' => $studentProgress,
                ':rating' => $studentProgressRating,
                ':homework' => $homeworkAssigned,
                ':feedback' => $parentFeedback,
                ':next_focus' => $nextLessonFocus,
                ':private_notes' => $privateNotes,
                ':nid' => $existingNote['id']
            ]);
        } else {
            $insertSql = '
                INSERT INTO lesson_notes (
                    booking_id,
                    tutor_user_id,
                    lesson_summary,
                    topics_covered,
                    student_progress,
                    student_progress_rating,
                    homework_assigned,
                    parent_feedback_notes,
                    next_lesson_focus,
                    private_tutor_notes
                ) VALUES (
                    :bid,
                    :tuid,
                    :summary,
                    :topics,
                    :progress,
                    :rating,
                    :homework,
                    :feedback,
                    :next_focus,
                    :private_notes
                )
            ';
            $inNoteStmt = $db->prepare($insertSql);
            $inNoteStmt->execute([
                ':bid' => $bookingId,
                ':tuid' => $user['id'],
                ':summary' => $lessonSummary,
                ':topics' => $topicsCovered,
                ':progress' => $studentProgress,
                ':rating' => $studentProgressRating,
                ':homework' => $homeworkAssigned,
                ':feedback' => $parentFeedback,
                ':next_focus' => $nextLessonFocus,
                ':private_notes' => $privateNotes
            ]);
        }

        $db->commit();

        ResponseService::json([
            'booking_id' => $bookingId,
            'attendance_status' => $attendanceStatus !== '' ? $attendanceStatus : $booking['attendance_status'],
            'message' => 'Lesson notes saved successfully.'
        ]);
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[Save Lesson Notes Error] ' . $e->getMessage());
        ResponseService::error('Failed to save lesson notes: ' . $e->getMessage(), 500);
    }
}

ResponseService::error('Method not allowed', 405);
