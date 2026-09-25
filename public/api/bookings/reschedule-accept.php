<?php declare(strict_types=1);

/**
 * Bookings API: Parent Accept Reschedule
 * Method: POST
 * Payload: { id: booking_id, csrf_token: ... }
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require STUDENT_PARENT role & CSRF
$user = AuthMiddleware::requireRole(['STUDENT_PARENT'], true);
AuthMiddleware::requireCsrf();

$parentUserId = (int)$user['id'];
$db = Connection::getInstance();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$bookingId = filter_var($input['id'] ?? $input['booking_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 422);
}

// 2. Begin Atomic Transaction with SELECT ... FOR UPDATE
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
            b.proposed_reschedule_start,
            b.proposed_reschedule_end,
            b.hourly_rate,
            b.total_amount,
            u_tutor.email AS tutor_email,
            u_tutor.first_name AS tutor_first_name,
            u_tutor.last_name AS tutor_last_name,
            u_parent.first_name AS parent_first_name,
            u_parent.last_name AS parent_last_name,
            sc.first_name AS child_first_name,
            sc.last_name AS child_last_name
        FROM bookings b
        JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
        JOIN users u_tutor ON tp.user_id = u_tutor.id
        JOIN users u_parent ON b.parent_user_id = u_parent.id
        LEFT JOIN students_children sc ON b.student_child_id = sc.id
        WHERE b.id = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        ResponseService::error('Booking not found', 404);
    }

    // Ownership check: Parent A cannot manage Parent B's booking
    if ((int)$booking['parent_user_id'] !== $parentUserId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot manage another parent\'s booking.', 403);
    }

    // Status transition check: Must be RESCHEDULE_PROPOSED
    if ($booking['status'] !== 'RESCHEDULE_PROPOSED') {
        $db->rollBack();
        ResponseService::error("Cannot accept reschedule. Current status is '{$booking['status']}' (Only RESCHEDULE_PROPOSED bookings can be accepted).", 422);
    }

    $proposedSlotId = (int)($booking['proposed_availability_slot_id'] ?? 0);
    if (!$proposedSlotId) {
        $db->rollBack();
        ResponseService::error('No proposed availability slot found for this booking.', 422);
    }

    // Lock proposed availability slot
    $propSlotStmt = $db->prepare('
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
    $propSlotStmt->execute([':slot_id' => $proposedSlotId]);
    $proposedSlot = $propSlotStmt->fetch();

    if (!$proposedSlot) {
        $db->rollBack();
        ResponseService::error('Proposed availability slot no longer exists.', 422);
    }

    // Verify proposed slot is not blocked
    if ((bool)$proposedSlot['is_blocked']) {
        $db->rollBack();
        ResponseService::error('Proposed availability slot is now blocked.', 422);
    }

    // Check capacity of proposed slot
    if ((int)$proposedSlot['booked_count'] >= (int)$proposedSlot['max_capacity']) {
        $db->rollBack();
        ResponseService::error('Proposed availability slot is full.', 422);
    }

    // Revalidate 24h & Future check (Europe/London)
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $propStartLondon = new DateTimeImmutable($proposedSlot['start_time'], $tz);
    $propEndLondon = new DateTimeImmutable($proposedSlot['end_time'], $tz);

    $minAllowedStart = $nowLondon->modify('+24 hours');
    if ($propStartLondon < $minAllowedStart) {
        $db->rollBack();
        ResponseService::error('Proposed slot is no longer at least 24 hours in the future.', 422);
    }

    // Recheck child conflict for new time window
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
            ResponseService::error('Child has an active lesson conflict at the proposed time.', 422);
        }
    }

    // Recheck tutor conflict for ONE_TO_ONE
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
            ':tpid' => $booking['tutor_profile_id'],
            ':bid' => $bookingId,
            ':st' => $proposedSlot['start_time'],
            ':et' => $proposedSlot['end_time']
        ]);
        if ($tutorConflictStmt->fetch()) {
            $db->rollBack();
            ResponseService::error('Tutor has a conflict at the proposed time.', 422);
        }
    }

    // 3. Release old slot capacity
    $oldSlotLockStmt = $db->prepare('
        SELECT id, booked_count 
        FROM availability_slots 
        WHERE tutor_profile_id = :tpid AND start_time = :st 
        FOR UPDATE
    ');
    $oldSlotLockStmt->execute([
        ':tpid' => $booking['tutor_profile_id'],
        ':st' => $booking['scheduled_start']
    ]);
    $oldSlot = $oldSlotLockStmt->fetch();

    if ($oldSlot && (int)$oldSlot['booked_count'] > 0) {
        $decStmt = $db->prepare('
            UPDATE availability_slots 
            SET booked_count = GREATEST(0, booked_count - 1), updated_at = NOW() 
            WHERE id = :sid
        ');
        $decStmt->execute([':sid' => $oldSlot['id']]);
    }

    // 4. Increment proposed slot capacity
    $incStmt = $db->prepare('
        UPDATE availability_slots 
        SET booked_count = booked_count + 1, updated_at = NOW() 
        WHERE id = :sid
    ');
    $incStmt->execute([':sid' => $proposedSlotId]);

    // Recalculate duration & total price if needed
    $durationSeconds = $propEndLondon->getTimestamp() - $propStartLondon->getTimestamp();
    $durationHours = max(0.5, round($durationSeconds / 3600, 2));
    $totalAmount = round((float)$booking['hourly_rate'] * $durationHours, 2);

    // 5. Update booking to ACCEPTED with new scheduled times
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "ACCEPTED",
            scheduled_start = :new_st,
            scheduled_end = :new_et,
            total_amount = :total,
            proposed_availability_slot_id = NULL,
            proposed_reschedule_start = NULL,
            proposed_reschedule_end = NULL,
            reschedule_proposed_by = NULL,
            updated_at = NOW()
        WHERE id = :id AND parent_user_id = :puid
    ');
    $updateStmt->execute([
        ':new_st' => $proposedSlot['start_time'],
        ':new_et' => $proposedSlot['end_time'],
        ':total' => $totalAmount,
        ':id' => $bookingId,
        ':puid' => $parentUserId
    ]);

    // 6. Create Audit Log BOOKING_RESCHEDULE_ACCEPTED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_RESCHEDULE_ACCEPTED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $parentUserId,
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => 'RESCHEDULE_PROPOSED',
            'new_status' => 'ACCEPTED',
            'old_start' => $booking['scheduled_start'],
            'old_end' => $booking['scheduled_end'],
            'new_start' => $proposedSlot['start_time'],
            'new_end' => $proposedSlot['end_time'],
            'old_slot_id' => $oldSlot ? $oldSlot['id'] : null,
            'new_slot_id' => $proposedSlotId,
            'tutor_profile_id' => $booking['tutor_profile_id'],
            'parent_user_id' => $parentUserId
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    // Trigger Email Notification (After Commit)
    try {
        $parentName = trim(($booking['parent_first_name'] ?? '') . ' ' . ($booking['parent_last_name'] ?? ''));
        $tutorName = trim(($booking['tutor_first_name'] ?? '') . ' ' . ($booking['tutor_last_name'] ?? ''));
        $childName = trim(($booking['child_first_name'] ?? 'Self') . ' ' . ($booking['child_last_name'] ?? ''));

        \App\Services\BookingNotificationService::notifyRescheduleAccepted(
            [
                'id' => $bookingId,
                'booking_reference' => $booking['booking_reference']
            ],
            $booking['tutor_email'],
            $tutorName,
            $parentName,
            $childName,
            $proposedSlot['start_time'],
            $proposedSlot['end_time']
        );
    } catch (\Throwable $mailErr) {
        error_log("[Reschedule Accept Mail Error] " . $mailErr->getMessage());
    }

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'ACCEPTED',
        'scheduled_start' => $proposedSlot['start_time'],
        'scheduled_end' => $proposedSlot['end_time'],
    ], 'Reschedule accepted successfully and session updated');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to accept reschedule: ' . $e->getMessage(), 500);
}
