<?php declare(strict_types=1);

/**
 * Bookings API: Reject Booking Request
 * Method: POST
 * Payload: { id: booking_id, rejection_reason: "...", csrf_token: ... }
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

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

$bookingId = filter_var($input['id'] ?? $input['booking_id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$rejectionReason = trim((string)($input['rejection_reason'] ?? ''));

if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 422);
}

// 3. Begin Atomic Transaction & Lock Booking & Slot with SELECT ... FOR UPDATE
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
            b.status
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
    if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot manage another tutor\'s booking.', 403);
    }

    // Status transition check: Allowed transition is strictly PENDING -> REJECTED
    if ($booking['status'] !== 'PENDING') {
        $db->rollBack();
        ResponseService::error("Cannot reject booking. Current status is '{$booking['status']}' (Only PENDING bookings can be rejected).", 422);
    }

    // 4. Update status to REJECTED
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "REJECTED", 
            rejection_reason = :reason,
            updated_at = NOW() 
        WHERE id = :id AND tutor_profile_id = :tpid
    ');
    $updateStmt->execute([
        ':reason' => !empty($rejectionReason) ? $rejectionReason : 'Declined by tutor',
        ':id' => $bookingId,
        ':tpid' => $tutorProfileId
    ]);

    // 5. Safely Decrement availability_slots.booked_count (Release Capacity without dropping below 0)
    $slotLockStmt = $db->prepare('
        SELECT id, booked_count 
        FROM availability_slots 
        WHERE tutor_profile_id = :tpid AND start_time = :st 
        FOR UPDATE
    ');
    $slotLockStmt->execute([
        ':tpid' => $tutorProfileId,
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

    // 6. Create Audit Log BOOKING_REJECTED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_REJECTED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $user['id'],
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => 'PENDING',
            'new_status' => 'REJECTED',
            'rejection_reason' => $rejectionReason,
            'tutor_profile_id' => $tutorProfileId,
            'parent_user_id' => $booking['parent_user_id'],
            'scheduled_start' => $booking['scheduled_start'],
            'capacity_released' => ($slot ? true : false)
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'REJECTED'
    ], 'Booking rejected and slot capacity released successfully');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to reject booking: ' . $e->getMessage(), 500);
}
