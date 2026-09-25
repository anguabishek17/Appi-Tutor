<?php declare(strict_types=1);

/**
 * Tutor API: Get Single Booking Detail
 * Method: GET
 * Query parameter: id (booking_id)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED TUTOR role
$user = AuthMiddleware::requireApprovedTutor(true);
$db = Connection::getInstance();

$bookingId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId <= 0) {
    ResponseService::error('Valid booking ID is required', 400);
}

// 2. Derive tutor_profile_id securely
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $user['id']]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];

// 3. Query specific booking enforcing tutor ownership
$stmt = $db->prepare('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.parent_user_id,
        b.tutor_profile_id,
        b.student_child_id,
        b.subject_id,
        b.scheduled_start,
        b.scheduled_end,
        b.status,
        b.hourly_rate,
        b.total_amount,
        b.student_notes,
        b.rejection_reason,
        b.cancellation_reason,
        b.created_at,
        s.name AS subject_name,
        c.name AS curriculum_name,
        sc.first_name AS child_first_name,
        sc.last_name AS child_last_name,
        sc.year_group AS child_year_group,
        sc.learning_goals AS child_learning_goals,
        sc.special_needs_notes AS child_special_needs,
        u_parent.first_name AS parent_first_name,
        u_parent.last_name AS parent_last_name,
        u_parent.phone AS parent_phone,
        av.session_type,
        av.delivery_mode
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    JOIN users u_parent ON b.parent_user_id = u_parent.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    LEFT JOIN availability_slots av ON av.tutor_profile_id = b.tutor_profile_id AND av.start_time = b.scheduled_start
    WHERE b.id = :id
    LIMIT 1
');
$stmt->execute([':id' => $bookingId]);
$booking = $stmt->fetch();

// 4. If booking not found or does not belong to this tutor, return 404/403 (IDOR Protection)
if (!$booking) {
    ResponseService::error('Booking not found', 404);
}

if ((int)$booking['tutor_profile_id'] !== $tutorProfileId) {
    ResponseService::error('Forbidden. You do not have permission to access this booking.', 403);
}

$booking['session_type'] = $booking['session_type'] ?? 'ONE_TO_ONE';
$booking['delivery_mode'] = $booking['delivery_mode'] ?? 'ONLINE';
$booking['hourly_rate'] = (float)$booking['hourly_rate'];
$booking['total_amount'] = (float)$booking['total_amount'];

ResponseService::json(['booking' => $booking], 'Booking details retrieved successfully');
