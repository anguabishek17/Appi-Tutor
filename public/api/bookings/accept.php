<?php declare(strict_types=1);

/**
 * Bookings API: Accept Booking Request
 * Method: POST
 * Payload: { id: booking_id, csrf_token: ... }
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
if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 422);
}

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
            b.total_amount,
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

    // Ownership check (Prevent Tutor A manipulating Tutor B's booking)
    if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
        $db->rollBack();
        ResponseService::error('Forbidden. You cannot manage another tutor\'s booking.', 403);
    }

    // Status transition check: Allowed transition is strictly PENDING -> ACCEPTED
    if ($booking['status'] !== 'PENDING') {
        $db->rollBack();
        ResponseService::error("Cannot accept booking. Current status is '{$booking['status']}' (Only PENDING bookings can be accepted).", 422);
    }

    // Verify tutor is approved & active
    if ($booking['approval_status'] !== 'APPROVED' || $booking['tutor_user_status'] !== 'ACTIVE') {
        $db->rollBack();
        ResponseService::error('Tutor account is not approved or active.', 422);
    }

    // Check if lesson time has already passed
    $tz = new DateTimeZone('Europe/London');
    $nowLondon = new DateTimeImmutable('now', $tz);
    $slotStartLondon = new DateTimeImmutable($booking['scheduled_start'], $tz);

    if ($slotStartLondon < $nowLondon) {
        $db->rollBack();
        ResponseService::error('Cannot accept a past lesson slot.', 422);
    }

    // Check for conflicting ACCEPTED booking for tutor at the same scheduled time window
    $conflictStmt = $db->prepare('
        SELECT id FROM bookings 
        WHERE tutor_profile_id = :tpid 
          AND id != :bid
          AND status = "ACCEPTED"
          AND (scheduled_start < :et AND scheduled_end > :st)
        LIMIT 1
    ');
    $conflictStmt->execute([
        ':tpid' => $tutorProfileId,
        ':bid' => $bookingId,
        ':st' => $booking['scheduled_start'],
        ':et' => $booking['scheduled_end']
    ]);

    if ($conflictStmt->fetch()) {
        $db->rollBack();
        ResponseService::error('You already have another accepted booking during this scheduled time window.', 422);
    }

    // Update status to ACCEPTED
    $updateStmt = $db->prepare('
        UPDATE bookings 
        SET status = "ACCEPTED", updated_at = NOW() 
        WHERE id = :id AND tutor_profile_id = :tpid
    ');
    $updateStmt->execute([
        ':id' => $bookingId,
        ':tpid' => $tutorProfileId
    ]);

    // Create Audit Log BOOKING_ACCEPTED
    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, "BOOKING_ACCEPTED", "bookings", :eid, :details, :ip, :ua)
    ');
    $auditStmt->execute([
        ':uid' => $user['id'],
        ':eid' => $bookingId,
        ':details' => json_encode([
            'booking_reference' => $booking['booking_reference'],
            'previous_status' => 'PENDING',
            'new_status' => 'ACCEPTED',
            'tutor_profile_id' => $tutorProfileId,
            'parent_user_id' => $booking['parent_user_id'],
            'scheduled_start' => $booking['scheduled_start'],
            'scheduled_end' => $booking['scheduled_end']
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $db->commit();

    ResponseService::json([
        'booking_id' => $bookingId,
        'booking_reference' => $booking['booking_reference'],
        'status' => 'ACCEPTED'
    ], 'Booking accepted successfully');

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    ResponseService::error('Failed to accept booking: ' . $e->getMessage(), 500);
}
