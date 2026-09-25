<?php declare(strict_types=1);

/**
 * Bookings API: Tutor Propose Reschedule
 * Method: POST
 * Payload: { id: booking_id, proposed_availability_slot_id: slot_id, csrf_token: ... }
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

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

$bookingId = filter_var($input['id'] ?? $input['booking_id'] ?? 0, FILTER_VALIDATE_INT);
$proposedSlotId = filter_var($input['proposed_availability_slot_id'] ?? $input['slot_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 422);
}

if (!$proposedSlotId || $proposedSlotId <= 0) {
    ResponseService::error('Valid proposed availability slot ID is required', 422);
}

// 3. Begin Atomic Transaction & Lock Booking & Proposed Slot with SELECT ... FOR UPDATE
$db->beginTransaction();

try {
    // Lock booking
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
            b.proposed_availability_slot_id,
            tp.approval_status,
            u_tutor.status AS tutor_user_status
        FROM bookings b
        JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
        JOIN users u_tutor ON tp.user_id = u_tutor.id
        WHERE b.id = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        ResponseService::error('Booking not found', 404);
    }

    // Ownership check: Tutor A cannot reschedule Tutor B's booking
    if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot manage another tutor\'s booking.', 403);
    }

    // Status transition check: Allowed transitions: ACCEPTED -> RESCHEDULE_PROPOSED or PENDING -> RESCHEDULE_PROPOSED
    if (!in_array($booking['status'], ['ACCEPTED', 'PENDING'], true)) {
        $db->rollBack();
        ResponseService::error("Cannot propose reschedule. Current status is '{$booking['status']}' (Only ACCEPTED or PENDING bookings can be rescheduled).", 422);
    }

    // Verify tutor account is approved & active
    if ($booking['approval_status'] !== 'APPROVED' || $booking['tutor_user_status'] !== 'ACTIVE') {
        $db->rollBack();
        ResponseService::error('Tutor account is not approved or active.', 422);
    }

    // Lock proposed availability slot
    $slotStmt = $db->prepare('
        SELECT 
            id,
            tutor_profile_id,
            start_time,
            end_time,
            session_type,
            delivery_mode,
            max_capacity,
            booked_count,
            is_blocked
        FROM availability_slots
        WHERE id = :slot_id
        FOR UPDATE
    ');
    $slotStmt->execute([':slot_id' => $proposedSlotId]);
    $proposedSlot = $slotStmt->fetch();

    if (!$proposedSlot) {
        $db->rollBack();
        ResponseService::error('Proposed availability slot not found.', 404);
    }

    // Slot Ownership check: Tutor cannot choose another tutor's slot
    if ((int)$proposedSlot['tutor_profile_id'] !== $tutorProfileId) {
        $db->rollBack();
        ResponseService::error('Forbidden. The proposed slot does not belong to you.', 403);
    }

    // Blocked check
    if ((bool)$proposedSlot['is_blocked']) {
        $db->rollBack();
        ResponseService::error('Proposed availability slot is blocked.', 422);
    }

    // 24-hour advance notice rule & Future check (Europe/London)
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $propStartLondon = new DateTimeImmutable($proposedSlot['start_time'], $tz);
    $propEndLondon = new DateTimeImmutable($proposedSlot['end_time'], $tz);

    $minAllowedStart = $nowLondon->modify('+24 hours');
    if ($propStartLondon < $minAllowedStart) {
        $db->rollBack();
        ResponseService::error('Proposed slots must be at least 24 hours in the future (London time).', 422);
    }

    // Capacity check
    if ((int)$proposedSlot['booked_count'] >= (int)$proposedSlot['max_capacity']) {
        $db->rollBack();
        ResponseService::error('Proposed slot is fully booked. No capacity remaining.', 422);
    }

    // Child conflict check on proposed slot time window
    if (!empty($booking['student_child_id'])) {
        $childConflictStmt = $db->prepare('
            SELECT id FROM bookings 
            WHERE student_child_id = :cid 
              AND id != :bid
              AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
              AND (scheduled_start < :et AND scheduled_end > :st)
            LIMIT 1
        ');
        $childConflictStmt->execute([
            ':cid' => $booking['student_child_id'],
            ':bid' => $bookingId,
            ':st' => $proposedSlot['start_time'],
            ':et' => $proposedSlot['end_time']
        ]);
        if ($childConflictStmt->fetch()) {
            $db->rollBack();
            ResponseService::error('Student has an active booking conflict during the proposed time window.', 422);
        }
    }

    // Tutor conflict check (if ONE_TO_ONE)
    if ($proposedSlot['session_type'] === 'ONE_TO_ONE') {
        $tutorConflictStmt = $db->prepare('
            SELECT id FROM bookings 
            WHERE tutor_profile_id = :tpid 
              AND id != :bid
              AND status IN ("PENDING", "ACCEPTED")
              AND (scheduled_start < :et AND scheduled_end > :st)
            LIMIT 1
        ');
        $tutorConflictStmt->execute([
            ':tpid' => $tutorProfileId,
            ':bid' => $bookingId,
            ':st' => $proposedSlot['start_time'],
            ':et' => $proposedSlot['end_time']
        ]);
        if ($tutorConflictStmt->fetch()) {
            $db->rollBack();
            ResponseService::error('Tutor already has an active booking during the proposed time window.', 422);
        }
    }

    // Update booking status to RESCHEDULE_PROPOSED and store proposed replacement slot details
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "RESCHEDULE_PROPOSED",
            proposed_availability_slot_id = :pslot_id,
            proposed_reschedule_start = :pstart,
            proposed_reschedule_end = :pend,
            reschedule_proposed_by = "TUTOR",
            updated_at = NOW()
        WHERE id = :id AND tutor_profile_id = :tpid
    ');
    $updateStmt->execute([
        ':pslot_id' => $proposedSlotId,
        ':pstart' => $proposedSlot['start_time'],
        ':pend' => $proposedSlot['end_time'],
        ':id' => $bookingId,
        ':tpid' => $tutorProfileId
    ]);

    // Create Audit Log BOOKING_RESCHEDULE_PROPOSED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_RESCHEDULE_PROPOSED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $user['id'],
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => $booking['status'],
            'new_status' => 'RESCHEDULE_PROPOSED',
            'original_start' => $booking['scheduled_start'],
            'original_end' => $booking['scheduled_end'],
            'proposed_slot_id' => $proposedSlotId,
            'proposed_start' => $proposedSlot['start_time'],
            'proposed_end' => $proposedSlot['end_time'],
            'tutor_profile_id' => $tutorProfileId,
            'parent_user_id' => $booking['parent_user_id'],
            'reschedule_proposed_by' => 'TUTOR'
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'RESCHEDULE_PROPOSED',
        'proposed_availability_slot_id' => $proposedSlotId,
        'proposed_start' => $proposedSlot['start_time'],
        'proposed_end' => $proposedSlot['end_time'],
    ], 'Reschedule proposed successfully and awaiting parent response');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to propose reschedule: ' . $e->getMessage(), 500);
}
