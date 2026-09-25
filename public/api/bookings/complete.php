<?php declare(strict_types=1);

/**
 * Bookings API: Complete Lesson
 * Method: POST /api/bookings/complete.php
 * Role: TUTOR
 * 
 * Rules:
 * - Authentication & APPROVED TUTOR role
 * - Valid CSRF
 * - Booking belongs to authenticated tutor
 * - Booking status must be ACCEPTED (no PENDING, REJECTED, CANCELLED, SYSTEM_CANCELLED)
 * - Timing rule: Scheduled end time must have passed (Europe/London timezone)
 * - Attendance must be recorded (ATTENDED, PARTIAL, ABSENT)
 * - Saves lesson notes if provided in payload
 * - Sets status to COMPLETED
 * - Does NOT modify availability_slots capacity
 * - Idempotency: Reject already COMPLETED booking safely
 * - Logs BOOKING_COMPLETED audit event
 * - Triggers BOOKING_COMPLETED notification to Parent
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\BookingNotificationService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED TUTOR role & CSRF
$user = AuthMiddleware::requireApprovedTutor(true);
AuthMiddleware::requireCsrf();

$db = Connection::getInstance();

// 2. Resolve tutor_profile_id securely
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $user['id']]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$bookingId = filter_var($input['booking_id'] ?? $input['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking_id is required', 422);
}

// Extract optional / required completion fields
$attendanceStatus = trim((string)($input['attendance_status'] ?? ''));
$lessonSummary = isset($input['lesson_summary']) ? trim((string)$input['lesson_summary']) : null;
$topicsCovered = isset($input['topics_covered']) ? trim((string)$input['topics_covered']) : null;
$studentProgress = isset($input['student_progress']) ? trim((string)$input['student_progress']) : null;
$studentProgressRating = isset($input['student_progress_rating']) && $input['student_progress_rating'] !== ''
    ? filter_var($input['student_progress_rating'], FILTER_VALIDATE_INT)
    : null;
$homeworkAssigned = isset($input['homework_assigned']) ? trim((string)$input['homework_assigned']) : null;
$parentFeedback = isset($input['parent_feedback_notes']) ? trim((string)$input['parent_feedback_notes']) : null;
$nextLessonFocus = isset($input['next_lesson_focus']) ? trim((string)$input['next_lesson_focus']) : null;
$privateNotes = isset($input['private_tutor_notes']) ? trim((string)$input['private_tutor_notes']) : null;

// Validate rating if passed
if ($studentProgressRating !== null && ($studentProgressRating < 1 || $studentProgressRating > 5)) {
    ResponseService::error('Progress rating must be between 1 and 5.', 422);
}

$validAttendance = ['ATTENDED', 'PARTIAL', 'ABSENT'];

// 3. Begin Transaction & Lock Booking with SELECT ... FOR UPDATE
$db->beginTransaction();

try {
    $stmt = $db->prepare('
        SELECT 
            b.id,
            b.booking_reference,
            b.parent_user_id,
            b.tutor_profile_id,
            b.student_child_id,
            b.subject_id,
            b.scheduled_start,
            b.scheduled_end,
            b.status,
            b.attendance_status,
            u_tutor.first_name AS tutor_first_name,
            u_tutor.last_name AS tutor_last_name,
            u_parent.email AS parent_email,
            u_parent.first_name AS parent_first_name,
            u_parent.last_name AS parent_last_name,
            sc.first_name AS child_first_name,
            sc.last_name AS child_last_name,
            s.name AS subject_name
        FROM bookings b
        JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
        JOIN users u_tutor ON tp.user_id = u_tutor.id
        JOIN users u_parent ON b.parent_user_id = u_parent.id
        LEFT JOIN students_children sc ON b.student_child_id = sc.id
        JOIN subjects s ON b.subject_id = s.id
        WHERE b.id = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        ResponseService::error('Booking not found', 404);
    }

    // Ownership check (IDOR Protection)
    if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot complete another tutor\'s booking.', 403);
    }

    // Idempotency: Reject already completed booking safely
    if ($booking['status'] === 'COMPLETED') {
        $db->rollBack();
        ResponseService::error('Booking is already marked as COMPLETED.', 422);
    }

    // Allowed transition: Strictly ACCEPTED -> COMPLETED
    if ($booking['status'] !== 'ACCEPTED') {
        $db->rollBack();
        ResponseService::error("Cannot complete booking with status '{$booking['status']}'. Only ACCEPTED bookings can be completed.", 422);
    }

    // Timing check: Scheduled lesson end must have passed
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $scheduledEndLondon = new DateTimeImmutable($booking['scheduled_end'], $tz);

    if ($scheduledEndLondon > $nowLondon) {
        $db->rollBack();
        ResponseService::error('Cannot mark a lesson as completed before its scheduled end time has passed.', 422);
    }

    // Attendance resolution & requirement
    $finalAttendance = $attendanceStatus !== '' ? $attendanceStatus : ($booking['attendance_status'] ?? 'NOT_RECORDED');
    if (!in_array($finalAttendance, $validAttendance, true)) {
        $db->rollBack();
        ResponseService::error('Valid attendance (ATTENDED, PARTIAL, or ABSENT) must be recorded before completing the lesson.', 422);
    }

    // 4. Update Booking to COMPLETED
    $updateStmt = $db->prepare('
        UPDATE bookings
        SET status = "COMPLETED",
            attendance_status = :attendance,
            updated_at = NOW()
        WHERE id = :id AND tutor_profile_id = :tpid
    ');
    $updateStmt->execute([
        ':attendance' => $finalAttendance,
        ':id' => $bookingId,
        ':tpid' => $tutorProfileId
    ]);

    // 5. Save or Update Lesson Notes
    $noteStmt = $db->prepare('SELECT id FROM lesson_notes WHERE booking_id = :bid FOR UPDATE');
    $noteStmt->execute([':bid' => $bookingId]);
    $existingNote = $noteStmt->fetch();

    if ($existingNote) {
        $upNoteStmt = $db->prepare('
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
        ');
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
        $inNoteStmt = $db->prepare('
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
        ');
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

    // 6. Create Audit Log BOOKING_COMPLETED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_COMPLETED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $user['id'],
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => 'ACCEPTED',
            'new_status' => 'COMPLETED',
            'attendance_status' => $finalAttendance,
            'tutor_profile_id' => $tutorProfileId,
            'parent_user_id' => $booking['parent_user_id'],
            'scheduled_start' => $booking['scheduled_start'],
            'scheduled_end' => $booking['scheduled_end']
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    // 7. Post-commit Email Notification (Parent notification)
    try {
        $parentName = trim(($booking['parent_first_name'] ?? '') . ' ' . ($booking['parent_last_name'] ?? ''));
        $tutorName = trim(($booking['tutor_first_name'] ?? '') . ' ' . ($booking['tutor_last_name'] ?? ''));
        $childName = !empty($booking['child_first_name']) ? trim($booking['child_first_name'] . ' ' . ($booking['child_last_name'] ?? '')) : 'Self / General';
        $subjectName = (string)($booking['subject_name'] ?? 'Lesson');

        BookingNotificationService::notifyBookingCompleted(
            [
                'id' => $bookingId,
                'booking_reference' => $booking['booking_reference'],
                'scheduled_start' => $booking['scheduled_start'],
                'scheduled_end' => $booking['scheduled_end']
            ],
            $booking['parent_email'],
            $parentName,
            $tutorName,
            $childName,
            $subjectName,
            $finalAttendance
        );
    } catch (\Throwable $mailErr) {
        error_log('[Complete Booking Mail Error] ' . $mailErr->getMessage());
    }

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'COMPLETED',
        'attendance_status' => $finalAttendance,
        'message' => 'Lesson successfully marked as COMPLETED.'
    ]);
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Complete Booking Error] ' . $e->getMessage());
    ResponseService::error('Failed to complete lesson: ' . $e->getMessage(), 500);
}
