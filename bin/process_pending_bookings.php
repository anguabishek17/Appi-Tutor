<?php declare(strict_types=1);

/**
 * CLI Processor: Automatic Pending Booking Cancellation
 *
 * SRS Rule:
 * A booking with status PENDING must automatically become SYSTEM_CANCELLED
 * when the tutor has not acted by exactly 24 hours before the lesson start time.
 * (i.e. scheduled_start <= NOW + 24 HOURS in Europe/London timezone).
 *
 * Safety & Invariants:
 * - CLI only (rejects HTTP invocation)
 * - Atomic transaction per booking with SELECT ... FOR UPDATE
 * - Only transitions PENDING -> SYSTEM_CANCELLED
 * - Decrements slot booked_count safely (IF booked_count > 0)
 * - Idempotent
 * - Complete audit logging (BOOKING_SYSTEM_CANCELLED)
 * - Dispatches transactional email notifications to Parent & Tutor after commit
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This processor can only be executed via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;
use App\Services\BookingNotificationService;

echo "==================================================\n";
echo "AppiTutors Pending Booking Processor\n";
echo "==================================================\n\n";

$db = Connection::getInstance();

$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);
$deadlineThreshold = $nowLondon->modify('+24 hours')->format('Y-m-d H:i:s');

echo "Current Time (London): " . $nowLondon->format('Y-m-d H:i:s') . "\n";
echo "Cancellation Deadline Threshold: <= {$deadlineThreshold}\n\n";

// 1. Fetch candidate PENDING bookings where scheduled_start <= now + 24 hours
$stmt = $db->prepare('
    SELECT id, booking_reference, scheduled_start, tutor_profile_id, parent_user_id
    FROM bookings
    WHERE status = "PENDING"
      AND scheduled_start <= :threshold
    ORDER BY scheduled_start ASC
');
$stmt->execute([':threshold' => $deadlineThreshold]);
$candidates = $stmt->fetchAll();

$checkedCount = count($candidates);
$cancelledCount = 0;
$skippedCount = 0;
$errorCount = 0;

echo "Found {$checkedCount} candidate booking(s) to inspect.\n\n";

foreach ($candidates as $candidate) {
    $bookingId = (int)$candidate['id'];

    $db->beginTransaction();

    try {
        // Lock booking row
        $lockStmt = $db->prepare('
            SELECT 
                b.id,
                b.booking_reference,
                b.status,
                b.scheduled_start,
                b.scheduled_end,
                b.tutor_profile_id,
                b.parent_user_id,
                u_parent.email AS parent_email,
                u_parent.first_name AS parent_first_name,
                u_parent.last_name AS parent_last_name,
                u_tutor.email AS tutor_email,
                u_tutor.first_name AS tutor_first_name,
                u_tutor.last_name AS tutor_last_name
            FROM bookings b
            JOIN users u_parent ON b.parent_user_id = u_parent.id
            JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
            JOIN users u_tutor ON tp.user_id = u_tutor.id
            WHERE b.id = :id
            FOR UPDATE
        ');
        $lockStmt->execute([':id' => $bookingId]);
        $booking = $lockStmt->fetch();

        if (!$booking) {
            $db->rollBack();
            $skippedCount++;
            continue;
        }

        // Strict Status Re-check: Only PENDING bookings can be system cancelled
        if ($booking['status'] !== 'PENDING') {
            $db->rollBack();
            $skippedCount++;
            echo "Skipped Booking {$booking['booking_reference']}: Status changed to '{$booking['status']}'\n";
            continue;
        }

        // Strict Deadline Re-check
        $schedStart = new DateTimeImmutable($booking['scheduled_start'], $tz);
        $minAllowed = $nowLondon->modify('+24 hours');
        if ($schedStart > $minAllowed) {
            $db->rollBack();
            $skippedCount++;
            echo "Skipped Booking {$booking['booking_reference']}: Deadline not yet reached\n";
            continue;
        }

        // Lock & safely release availability slot capacity
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
                SET booked_count = IF(booked_count > 0, booked_count - 1, 0), updated_at = NOW() 
                WHERE id = :sid
            ');
            $decStmt->execute([':sid' => $slot['id']]);
        }

        // Update status to SYSTEM_CANCELLED
        $updStmt = $db->prepare('
            UPDATE bookings 
            SET status = "SYSTEM_CANCELLED",
                cancellation_reason = "Tutor did not respond within 24 hours of lesson start",
                updated_at = NOW()
            WHERE id = :id AND status = "PENDING"
        ');
        $updStmt->execute([':id' => $bookingId]);

        // Audit Log: BOOKING_SYSTEM_CANCELLED
        $auditStmt = $db->prepare('
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (NULL, "BOOKING_SYSTEM_CANCELLED", "bookings", :eid, :details, "127.0.0.1", "CLI_CRON_PROCESSOR")
        ');
        $auditStmt->execute([
            ':eid' => $bookingId,
            ':details' => json_encode([
                'booking_reference' => $booking['booking_reference'],
                'previous_status' => 'PENDING',
                'new_status' => 'SYSTEM_CANCELLED',
                'scheduled_start' => $booking['scheduled_start'],
                'tutor_profile_id' => $booking['tutor_profile_id'],
                'parent_user_id' => $booking['parent_user_id'],
                'reason' => 'Tutor did not respond before 24-hour action deadline'
            ])
        ]);

        $db->commit();
        $cancelledCount++;

        echo "Booking: {$booking['booking_reference']}\n";
        echo "Status: PENDING -> SYSTEM_CANCELLED (Slot capacity released)\n";

        // Dispatch transactional email notifications AFTER commit
        try {
            $parentName = trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name']);
            $tutorName = trim($booking['tutor_first_name'] . ' ' . $booking['tutor_last_name']);
            BookingNotificationService::notifySystemCancelled(
                $booking,
                $booking['parent_email'],
                $parentName,
                $booking['tutor_email'],
                $tutorName
            );
        } catch (\Throwable $mailErr) {
            error_log("[CLI Cron Notification Error] " . $mailErr->getMessage());
        }

    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorCount++;
        error_log("[PendingBookingProcessor Error on ID {$bookingId}]: " . $e->getMessage());
        echo "Error processing booking ID {$bookingId}: " . $e->getMessage() . "\n";
    }
}

echo "\n==================================================\n";
echo "Summary:\n";
echo "Checked: {$checkedCount}\n";
echo "System Cancelled: {$cancelledCount}\n";
echo "Skipped: {$skippedCount}\n";
echo "Errors: {$errorCount}\n";
echo "==================================================\n";

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    exit($errorCount > 0 ? 1 : 0);
}
