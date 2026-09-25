<?php declare(strict_types=1);

/**
 * Bookings API: Parent Decline Reschedule
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
            b.proposed_reschedule_end
        FROM bookings b
        WHERE b.id = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        ResponseService::error('Booking not found', 404);
    }

    // Ownership check
    if ((int)$booking['parent_user_id'] !== $parentUserId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot manage another parent\'s booking.', 403);
    }

    // Status check: Must be RESCHEDULE_PROPOSED
    if ($booking['status'] !== 'RESCHEDULE_PROPOSED') {
        $db->rollBack();
        ResponseService::error("Cannot decline reschedule. Current status is '{$booking['status']}' (Only RESCHEDULE_PROPOSED bookings can be declined).", 422);
    }

    $proposedSlotId = $booking['proposed_availability_slot_id'];

    // Revert booking status to ACCEPTED and clear proposed reschedule fields
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "ACCEPTED",
            proposed_availability_slot_id = NULL,
            proposed_reschedule_start = NULL,
            proposed_reschedule_end = NULL,
            reschedule_proposed_by = NULL,
            updated_at = NOW()
        WHERE id = :id AND parent_user_id = :puid
    ');
    $updateStmt->execute([
        ':id' => $bookingId,
        ':puid' => $parentUserId
    ]);

    // Create Audit Log BOOKING_RESCHEDULE_DECLINED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_RESCHEDULE_DECLINED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $parentUserId,
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => 'RESCHEDULE_PROPOSED',
            'new_status' => 'ACCEPTED',
            'scheduled_start' => $booking['scheduled_start'],
            'scheduled_end' => $booking['scheduled_end'],
            'declined_proposed_slot_id' => $proposedSlotId,
            'tutor_profile_id' => $booking['tutor_profile_id'],
            'parent_user_id' => $parentUserId
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'ACCEPTED'
    ], 'Reschedule proposal declined. Original booking retained.');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to decline reschedule: ' . $e->getMessage(), 500);
}
