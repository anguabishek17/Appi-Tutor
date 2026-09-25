<?php declare(strict_types=1);

/**
 * Tutor API: List Bookings
 * Method: GET
 * Query parameters:
 *  - status (optional: PENDING, ACCEPTED, REJECTED, etc.)
 *  - timeframe (optional: upcoming, past, all)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED TUTOR role
$user = AuthMiddleware::requireApprovedTutor(true);
$db = Connection::getInstance();

// 2. Derive tutor_profile_id securely from DB
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $user['id']]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];

// 3. Parse filter params
$statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
$timeframe = strtolower(trim((string)($_GET['timeframe'] ?? 'all')));

$whereClauses = ['b.tutor_profile_id = :tpid'];
$params = [':tpid' => $tutorProfileId];

if (!empty($statusFilter)) {
    $whereClauses[] = 'b.status = :status';
    $params[':status'] = $statusFilter;
}

if ($timeframe === 'upcoming') {
    $whereClauses[] = 'b.scheduled_start >= NOW()';
} elseif ($timeframe === 'past') {
    $whereClauses[] = 'b.scheduled_start < NOW()';
}

$whereSql = implode(' AND ', $whereClauses);

// 4. Query bookings for this tutor
$stmt = $db->prepare("
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
        b.attendance_status,
        b.hourly_rate,
        b.total_amount,
        b.student_notes,
        b.created_at,
        s.name AS subject_name,
        c.name AS curriculum_name,
        sc.first_name AS child_first_name,
        sc.last_name AS child_last_name,
        sc.year_group AS child_year_group,
        sc.learning_goals AS child_learning_goals,
        u_parent.first_name AS parent_first_name,
        u_parent.last_name AS parent_last_name,
        ln.id AS lesson_note_id,
        av.session_type,
        av.delivery_mode
    FROM bookings b
    JOIN subjects s ON b.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    JOIN users u_parent ON b.parent_user_id = u_parent.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    LEFT JOIN lesson_notes ln ON b.id = ln.booking_id
    LEFT JOIN availability_slots av ON av.tutor_profile_id = b.tutor_profile_id AND av.start_time = b.scheduled_start
    WHERE {$whereSql}
    ORDER BY b.scheduled_start DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Count summaries
$pendingCount = 0;
$acceptedCount = 0;
$completedCount = 0;
$rejectedCount = 0;

foreach ($bookings as &$bk) {
    if ($bk['status'] === 'PENDING') $pendingCount++;
    if ($bk['status'] === 'ACCEPTED') $acceptedCount++;
    if ($bk['status'] === 'COMPLETED') $completedCount++;
    if ($bk['status'] === 'REJECTED') $rejectedCount++;
    
    // Provide default fallback for session type / delivery mode if slot record was removed
    $bk['session_type'] = $bk['session_type'] ?? 'ONE_TO_ONE';
    $bk['delivery_mode'] = $bk['delivery_mode'] ?? 'ONLINE';
    $bk['attendance_status'] = $bk['attendance_status'] ?? 'NOT_RECORDED';
    $bk['has_notes'] = !empty($bk['lesson_note_id']);
    $bk['hourly_rate'] = (float)$bk['hourly_rate'];
    $bk['total_amount'] = (float)$bk['total_amount'];
}
unset($bk);

ResponseService::json([
    'bookings' => $bookings,
    'total' => count($bookings),
    'counts' => [
        'pending' => $pendingCount,
        'accepted' => $acceptedCount,
        'completed' => $completedCount,
        'rejected' => $rejectedCount
    ]
], 'Tutor bookings retrieved successfully');
