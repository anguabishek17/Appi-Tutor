<?php declare(strict_types=1);

/**
 * Bookings API: Create Booking Request Foundation
 * Method: POST
 * Payload:
 *  - availability_slot_id (int)
 *  - student_child_id (int)
 *  - subject_id (int)
 *  - student_notes (string, optional)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require STUDENT_PARENT role & CSRF
$parent = AuthMiddleware::requireRole(['STUDENT_PARENT'], true);
AuthMiddleware::requireCsrf();

$parentUserId = (int)$parent['id'];
$db = Connection::getInstance();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$slotId = filter_var($input['availability_slot_id'] ?? $input['slot_id'] ?? 0, FILTER_VALIDATE_INT);
$childId = filter_var($input['student_child_id'] ?? $input['child_id'] ?? 0, FILTER_VALIDATE_INT);
$subjectId = filter_var($input['subject_id'] ?? 0, FILTER_VALIDATE_INT);
$studentNotes = trim((string)($input['student_notes'] ?? ''));

if (!$slotId || $slotId <= 0) {
    ResponseService::error('Valid availability slot ID is required.', 422);
}
if (!$childId || $childId <= 0) {
    ResponseService::error('Valid child profile ID is required.', 422);
}
if (!$subjectId || $subjectId <= 0) {
    ResponseService::error('Valid subject ID is required.', 422);
}

// 2. Validate child belongs to the authenticated parent
$childStmt = $db->prepare('SELECT id, first_name, last_name FROM students_children WHERE id = :cid AND parent_user_id = :pid LIMIT 1');
$childStmt->execute([':cid' => $childId, ':pid' => $parentUserId]);
$child = $childStmt->fetch();

if (!$child) {
    ResponseService::error('Child profile not found or does not belong to your parent account.', 403);
}

// 3. Begin Atomic Transaction with SELECT ... FOR UPDATE
$db->beginTransaction();

try {
    // Lock availability slot row for update
    $slotStmt = $db->prepare('
        SELECT 
            av.id,
            av.tutor_profile_id,
            av.start_time,
            av.end_time,
            av.session_type,
            av.delivery_mode,
            av.max_capacity,
            av.booked_count,
            av.is_blocked,
            tp.hourly_rate,
            tp.approval_status,
            u.status AS user_status,
            u.first_name AS tutor_first_name,
            u.last_name AS tutor_last_name
        FROM availability_slots av
        JOIN tutor_profiles tp ON av.tutor_profile_id = tp.id
        JOIN users u ON tp.user_id = u.id
        WHERE av.id = :slot_id
        FOR UPDATE
    ');
    $slotStmt->execute([':slot_id' => $slotId]);
    $slot = $slotStmt->fetch();

    if (!$slot) {
        $db->rollBack();
        ResponseService::error('Availability slot not found.', 404);
    }

    $tutorProfileId = (int)$slot['tutor_profile_id'];
    $hourlyRate = (float)$slot['hourly_rate'];
    $maxCapacity = (int)$slot['max_capacity'];
    $bookedCount = (int)$slot['booked_count'];
    $isBlocked = (bool)$slot['is_blocked'];

    // Rule A: Tutor must be APPROVED and active
    if ($slot['approval_status'] !== 'APPROVED' || $slot['user_status'] !== 'ACTIVE') {
        $db->rollBack();
        ResponseService::error('Tutor is not currently approved or active for bookings.', 422);
    }

    // Rule B: Slot must not be blocked
    if ($isBlocked) {
        $db->rollBack();
        ResponseService::error('This availability slot has been blocked by the tutor.', 422);
    }

    // Rule C: Slot must be at least 24 hours in the future (Europe/London)
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $slotStartLondon = new DateTimeImmutable($slot['start_time'], $tz);
    $slotEndLondon = new DateTimeImmutable($slot['end_time'], $tz);

    $minAllowedStart = $nowLondon->modify('+24 hours');
    if ($slotStartLondon < $minAllowedStart) {
        $db->rollBack();
        ResponseService::error('Slots must be booked at least 24 hours in advance (London time).', 422);
    }

    // Rule D: Capacity check
    if ($bookedCount >= $maxCapacity) {
        $db->rollBack();
        ResponseService::error('This slot is fully booked. No capacity remaining.', 422);
    }

    // Rule E: Validate subject belongs to tutor (or exists in system)
    $subjStmt = $db->prepare('SELECT id, name FROM subjects WHERE id = :sid LIMIT 1');
    $subjStmt->execute([':sid' => $subjectId]);
    $subject = $subjStmt->fetch();

    if (!$subject) {
        $db->rollBack();
        ResponseService::error('Selected subject is invalid.', 422);
    }

    // Rule F: Prevent duplicate booking for the SAME child and slot
    $dupCheckStmt = $db->prepare('
        SELECT id FROM bookings 
        WHERE student_child_id = :cid 
          AND tutor_profile_id = :tpid 
          AND scheduled_start = :st 
          AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
        LIMIT 1
    ');
    $dupCheckStmt->execute([
        ':cid' => $childId,
        ':tpid' => $tutorProfileId,
        ':st' => $slot['start_time']
    ]);
    if ($dupCheckStmt->fetch()) {
        $db->rollBack();
        ResponseService::error('You have already booked this slot for this student.', 422);
    }

    // Rule G: Check child schedule conflict (overlapping booking at same time with any tutor)
    $childConflictStmt = $db->prepare('
        SELECT id FROM bookings 
        WHERE student_child_id = :cid 
          AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
          AND (scheduled_start < :et AND scheduled_end > :st)
        LIMIT 1
    ');
    $childConflictStmt->execute([
        ':cid' => $childId,
        ':st' => $slot['start_time'],
        ':et' => $slot['end_time']
    ]);
    if ($childConflictStmt->fetch()) {
        $db->rollBack();
        ResponseService::error('This student already has an active lesson scheduled during this time window.', 422);
    }

    // Rule H: For ONE_TO_ONE sessions, check tutor does not already have an accepted/pending 1-to-1 booking
    if ($slot['session_type'] === 'ONE_TO_ONE') {
        $tutorConflictStmt = $db->prepare('
            SELECT id FROM bookings 
            WHERE tutor_profile_id = :tpid 
              AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
              AND (scheduled_start < :et AND scheduled_end > :st)
            LIMIT 1
        ');
        $tutorConflictStmt->execute([
            ':tpid' => $tutorProfileId,
            ':st' => $slot['start_time'],
            ':et' => $slot['end_time']
        ]);
        if ($tutorConflictStmt->fetch()) {
            $db->rollBack();
            ResponseService::error('Tutor is already booked during this time window.', 422);
        }
    }

    // Calculate duration in hours & total price
    $durationSeconds = $slotEndLondon->getTimestamp() - $slotStartLondon->getTimestamp();
    $durationHours = max(0.5, round($durationSeconds / 3600, 2));
    $totalAmount = round($hourlyRate * $durationHours, 2);

    // Generate unique booking reference: e.g. APT-2026-XXXXXX
    $bookingReference = 'APT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));

    // 1. Insert booking with status PENDING
    $insertBookingStmt = $db->prepare('
        INSERT INTO bookings 
        (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount, student_notes)
        VALUES (:ref, :parent_id, :tpid, :cid, :sid, :st, :et, "PENDING", :rate, :total, :notes)
    ');
    $insertBookingStmt->execute([
        ':ref' => $bookingReference,
        ':parent_id' => $parentUserId,
        ':tpid' => $tutorProfileId,
        ':cid' => $childId,
        ':sid' => $subjectId,
        ':st' => $slot['start_time'],
        ':et' => $slot['end_time'],
        ':rate' => $hourlyRate,
        ':total' => $totalAmount,
        ':notes' => !empty($studentNotes) ? $studentNotes : null,
    ]);

    $newBookingId = (int)$db->lastInsertId();

    // 2. Increment availability_slots.booked_count atomically
    $incrementStmt = $db->prepare('
        UPDATE availability_slots 
        SET booked_count = booked_count + 1, updated_at = NOW() 
        WHERE id = :slot_id
    ');
    $incrementStmt->execute([':slot_id' => $slotId]);

    // 3. Create Audit Log BOOKING_CREATED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_CREATED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $parentUserId,
        ':eid' => $newBookingId,
        ':details' => json_encode([
            'booking_reference' => $bookingReference,
            'slot_id' => $slotId,
            'tutor_profile_id' => $tutorProfileId,
            'student_child_id' => $childId,
            'subject_id' => $subjectId,
            'scheduled_start' => $slot['start_time'],
            'scheduled_end' => $slot['end_time'],
            'session_type' => $slot['session_type'],
            'delivery_mode' => $slot['delivery_mode'],
            'total_amount' => $totalAmount,
            'status' => 'PENDING',
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    // 4. Commit Transaction
    $db->commit();

    // 5. Transactional Email Notification (After Commit)
    try {
        $tutorEmailStmt = $db->prepare('SELECT u.email, u.first_name, u.last_name FROM users u JOIN tutor_profiles tp ON tp.user_id = u.id WHERE tp.id = :tpid LIMIT 1');
        $tutorEmailStmt->execute([':tpid' => $tutorProfileId]);
        $tutorUser = $tutorEmailStmt->fetch();

        if ($tutorUser) {
            $tutorName = trim($tutorUser['first_name'] . ' ' . $tutorUser['last_name']);
            $parentName = trim(($parent['first_name'] ?? '') . ' ' . ($parent['last_name'] ?? ''));
            $childName = trim($child['first_name'] . ' ' . $child['last_name']);

            \App\Services\BookingNotificationService::notifyBookingCreated(
                [
                    'id' => $newBookingId,
                    'booking_reference' => $bookingReference,
                    'scheduled_start' => $slot['start_time'],
                    'scheduled_end' => $slot['end_time'],
                    'delivery_mode' => $slot['delivery_mode']
                ],
                $tutorUser['email'],
                $tutorName,
                $parentName,
                $childName,
                $subject['name']
            );
        }
    } catch (\Throwable $mailErr) {
        error_log("[Booking Create Mail Error] " . $mailErr->getMessage());
    }

    ResponseService::json([
        'booking_id' => $newBookingId,
        'booking_reference' => $bookingReference,
        'status' => 'PENDING',
        'scheduled_start' => $slot['start_time'],
        'scheduled_end' => $slot['end_time'],
        'delivery_mode' => $slot['delivery_mode'],
        'session_type' => $slot['session_type'],
        'total_amount' => $totalAmount,
        'child_name' => $child['first_name'] . ' ' . $child['last_name'],
        'tutor_name' => $slot['tutor_first_name'] . ' ' . $slot['tutor_last_name'],
    ], 'Booking requested successfully and is pending tutor confirmation', 201);

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Booking failed: ' . $e->getMessage(), 500);
}
