<?php declare(strict_types=1);

/**
 * Bookings API: Cancel Booking (Parent or Tutor)
 * Method: POST
 * Payload: { id: booking_id, cancellation_reason: "...", csrf_token: ... }
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require Authenticated User (STUDENT_PARENT or TUTOR) & CSRF
$user = AuthMiddleware::requireRole(['STUDENT_PARENT', 'TUTOR'], true);
AuthMiddleware::requireCsrf();

$userId = (int)$user['id'];
$userRole = (string)$user['role_name'];
$db = Connection::getInstance();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$bookingId = filter_var($input['id'] ?? $input['booking_id'] ?? 0, FILTER_VALIDATE_INT);
$reason = trim((string)($input['cancellation_reason'] ?? ''));

if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 422);
}

// 2. Begin Atomic Transaction & Lock Booking with SELECT ... FOR UPDATE
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
            tp.user_id AS tutor_user_id
        FROM bookings b
        JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
        WHERE b.id = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        ResponseService::error('Booking not found', 404);
    }

    // Ownership check: Parent can cancel own child's booking; Tutor can cancel own booking
    $isAuthorized = false;
    if ($userRole === 'STUDENT_PARENT' && (int)$booking['parent_user_id'] === $userId) {
        $isAuthorized = true;
    } elseif ($userRole === 'TUTOR' && (int)$booking['tutor_user_id'] === $userId) {
        $isAuthorized = true;
    }

    if (!$isAuthorized) {
        $db->rollBack();
        ResponseService::error('Forbidden. You are not authorized to cancel this booking.', 403);
    }

    // Status transition check: Allowed: PENDING, ACCEPTED, RESCHEDULE_PROPOSED -> CANCELLED
    $allowedStatuses = ['PENDING', 'ACCEPTED', 'RESCHEDULE_PROPOSED'];
    if (!in_array($booking['status'], $allowedStatuses, true)) {
        $db->rollBack();
        ResponseService::error("Cannot cancel booking with status '{$booking['status']}'.", 422);
    }

    // 24-hour rule check (Europe/London)
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $startLondon = new DateTimeImmutable($booking['scheduled_start'], $tz);

    $minAllowedCancel = $nowLondon->modify('+24 hours');
    if ($startLondon < $minAllowedCancel) {
        $db->rollBack();
        ResponseService::error('Bookings cannot be cancelled less than 24 hours before the scheduled start time.', 422);
    }

    // 3. Update booking status to CANCELLED
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "CANCELLED",
            cancellation_reason = :reason,
            proposed_availability_slot_id = NULL,
            proposed_reschedule_start = NULL,
            proposed_reschedule_end = NULL,
            reschedule_proposed_by = NULL,
            updated_at = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        ':reason' => !empty($reason) ? $reason : "Cancelled by {$userRole}",
        ':id' => $bookingId
    ]);

    // 4. Safely release slot capacity (booked_count = GREATEST(0, booked_count - 1))
    $slotLockStmt = $db->prepare('
        SELECT id, booked_count 
        FROM availability_slots 
        WHERE tutor_profile_id = :tpid AND start_time = :st 
        FOR UPDATE
    ');
    $slotLockStmt->execute([
        ':tpid' => $booking['tutor_profile_id'],
        ':st' => $booking['scheduled_start']
    ]);
    $slot = $slotLockStmt->fetch();

    if ($slot && (int)$slot['booked_count'] > 0) {
        $decStmt = $db->prepare('
            UPDATE availability_slots 
            SET booked_count = GREATEST(0, booked_count - 1), updated_at = NOW() 
            WHERE id = :sid
        ');
        $decStmt->execute([':sid' => $slot['id']]);
    }

    // 5. Create Audit Log BOOKING_CANCELLED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_CANCELLED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $userId,
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => $booking['status'],
            'new_status' => 'CANCELLED',
            'cancelled_by_role' => $userRole,
            'cancellation_reason' => $reason,
            'scheduled_start' => $booking['scheduled_start'],
            'scheduled_end' => $booking['scheduled_end'],
            'slot_id' => $slot ? $slot['id'] : null,
            'capacity_released' => ($slot ? true : false)
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'CANCELLED'
    ], 'Booking cancelled and slot capacity released successfully');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to cancel booking: ' . $e->getMessage(), 500);
}
