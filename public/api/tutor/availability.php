<?php declare(strict_types=1);

/**
 * Tutor API: Manage Availability Slots
 * GET: List upcoming and active availability slots for the logged-in approved tutor
 * POST: Create a new dated availability slot
 * DELETE (or POST with action=delete/block): Delete or toggle block on tutor's own slot
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\ResponseService;
use App\Services\CsrfService;

header('Content-Type: application/json; charset=utf-8');

// 1. Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor(true);
$db = Connection::getInstance();
$userId = (int)$user['id'];

// Resolve tutor_profile_id securely
$tpStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
$tpStmt->execute([':uid' => $userId]);
$tutorProfile = $tpStmt->fetch();

if (!$tutorProfile) {
    ResponseService::error('Tutor profile not found', 404);
}

$tutorProfileId = (int)$tutorProfile['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Explicit Timezone: Europe/London per SRS rules
$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);

if ($method === 'GET') {
    // Fetch upcoming slots for this tutor
    $stmt = $db->prepare('
        SELECT 
            id,
            tutor_profile_id,
            start_time,
            end_time,
            session_type,
            delivery_mode,
            max_capacity,
            booked_count,
            is_blocked,
            created_at
        FROM availability_slots
        WHERE tutor_profile_id = :tpid
        ORDER BY start_time ASC
    ');
    $stmt->execute([':tpid' => $tutorProfileId]);
    $slots = $stmt->fetchAll();

    ResponseService::json([
        'slots' => $slots,
        'count' => count($slots),
        'server_time_london' => $nowLondon->format('Y-m-d H:i:s')
    ], 'Availability slots retrieved successfully');
}

if ($method === 'POST') {
    AuthMiddleware::requireCsrf();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? 'create';

    // -------------------------------------------------------------
    // Action: DELETE SLOT (Tutor can delete only their own slot)
    // -------------------------------------------------------------
    if ($action === 'delete') {
        $slotId = filter_var($input['slot_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$slotId || $slotId <= 0) {
            ResponseService::error('Valid slot_id is required', 422);
        }

        // Verify slot belongs to this tutor
        $checkStmt = $db->prepare('SELECT id, tutor_profile_id, booked_count FROM availability_slots WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $slotId]);
        $slot = $checkStmt->fetch();

        if (!$slot || (int)$slot['tutor_profile_id'] !== $tutorProfileId) {
            ResponseService::error('Slot not found or unauthorized to delete', 403);
        }

        if ((int)$slot['booked_count'] > 0) {
            ResponseService::error('Cannot delete a slot with active bookings. Please contact management.', 400);
        }

        $delStmt = $db->prepare('DELETE FROM availability_slots WHERE id = :id AND tutor_profile_id = :tpid');
        $delStmt->execute([':id' => $slotId, ':tpid' => $tutorProfileId]);

        ResponseService::json(['slot_id' => $slotId], 'Availability slot deleted successfully');
    }

    // -------------------------------------------------------------
    // Action: TOGGLE BLOCK (Tutor can block/unblock their own slot)
    // -------------------------------------------------------------
    if ($action === 'toggle_block') {
        $slotId = filter_var($input['slot_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$slotId || $slotId <= 0) {
            ResponseService::error('Valid slot_id is required', 422);
        }

        $checkStmt = $db->prepare('SELECT id, tutor_profile_id, is_blocked FROM availability_slots WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $slotId]);
        $slot = $checkStmt->fetch();

        if (!$slot || (int)$slot['tutor_profile_id'] !== $tutorProfileId) {
            ResponseService::error('Slot not found or unauthorized to modify', 403);
        }

        $newBlockedState = (int)!((bool)$slot['is_blocked']);
        $updStmt = $db->prepare('UPDATE availability_slots SET is_blocked = :b, updated_at = NOW() WHERE id = :id AND tutor_profile_id = :tpid');
        $updStmt->execute([':b' => $newBlockedState, ':id' => $slotId, ':tpid' => $tutorProfileId]);

        ResponseService::json([
            'slot_id' => $slotId,
            'is_blocked' => (bool)$newBlockedState
        ], 'Slot status updated successfully');
    }

    // -------------------------------------------------------------
    // Action: CREATE DATED AVAILABILITY SLOT
    // -------------------------------------------------------------
    $dateStr = trim((string)($input['slot_date'] ?? '')); // e.g. 2026-10-20
    $startTimeStr = trim((string)($input['start_time'] ?? '')); // e.g. 10:00 or 10:00:00 or full ISO
    $endTimeStr = trim((string)($input['end_time'] ?? '')); // e.g. 11:00
    $sessionType = strtoupper(trim((string)($input['session_type'] ?? 'ONE_TO_ONE')));
    $deliveryMode = strtoupper(trim((string)($input['delivery_mode'] ?? 'ONLINE')));
    $maxCapacity = filter_var($input['max_capacity'] ?? 1, FILTER_VALIDATE_INT);

    $errors = [];

    // Parse DateTime objects in Europe/London timezone
    $startDateTime = null;
    $endDateTime = null;

    if (!empty($dateStr) && !empty($startTimeStr) && !empty($endTimeStr)) {
        try {
            $startDateTime = new DateTimeImmutable("$dateStr $startTimeStr", $tz);
            $endDateTime = new DateTimeImmutable("$dateStr $endTimeStr", $tz);
        } catch (\Exception $e) {
            $errors['time'] = 'Invalid date or time format provided.';
        }
    } else {
        $errors['time'] = 'Date, start time, and end time are required.';
    }

    if ($startDateTime && $endDateTime) {
        // Rule 1: start_time must be before end_time
        if ($startDateTime >= $endDateTime) {
            $errors['time_order'] = 'Start time must be strictly before end time.';
        }

        // Rule 2: slot must be at least 24 hours in the future
        $minAllowedStart = $nowLondon->modify('+24 hours');
        if ($startDateTime < $minAllowedStart) {
            $errors['advance_notice'] = 'Slots must be created at least 24 hours in advance (London time).';
        }
    }

    // Rule 3: Session type and delivery mode enum validation
    if (!in_array($sessionType, ['ONE_TO_ONE', 'GROUP'], true)) {
        $errors['session_type'] = 'Invalid session type. Choose ONE_TO_ONE or GROUP.';
    }

    if (!in_array($deliveryMode, ['ONLINE', 'IN_PERSON'], true)) {
        $errors['delivery_mode'] = 'Invalid delivery mode. Choose ONLINE or IN_PERSON.';
    }

    // Rule 4: Capacity rules
    if ($sessionType === 'ONE_TO_ONE') {
        $maxCapacity = 1;
    } elseif ($sessionType === 'GROUP') {
        if ($maxCapacity === false || $maxCapacity < 2 || $maxCapacity > 30) {
            $errors['max_capacity'] = 'GROUP session capacity must be between 2 and 30 students.';
        }
    }

    if (!empty($errors)) {
        ResponseService::error('Validation failed', 422, $errors);
    }

    $formattedStart = $startDateTime->format('Y-m-d H:i:s');
    $formattedEnd = $endDateTime->format('Y-m-d H:i:s');

    // Rule 5: Tutor cannot create overlapping slots
    $overlapStmt = $db->prepare('
        SELECT id FROM availability_slots 
        WHERE tutor_profile_id = :tpid 
          AND (
            (start_time < :end_time AND end_time > :start_time)
          )
        LIMIT 1
    ');
    $overlapStmt->execute([
        ':tpid' => $tutorProfileId,
        ':start_time' => $formattedStart,
        ':end_time' => $formattedEnd
    ]);

    if ($overlapStmt->fetch()) {
        ResponseService::error('This slot overlaps with an existing availability slot in your schedule.', 422);
    }

    // Insert new slot
    $insertStmt = $db->prepare('
        INSERT INTO availability_slots 
        (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
        VALUES (:tpid, :start_time, :end_time, :session_type, :delivery_mode, :max_capacity, 0, 0)
    ');
    $insertStmt->execute([
        ':tpid' => $tutorProfileId,
        ':start_time' => $formattedStart,
        ':end_time' => $formattedEnd,
        ':session_type' => $sessionType,
        ':delivery_mode' => $deliveryMode,
        ':max_capacity' => $maxCapacity
    ]);

    $newSlotId = (int)$db->lastInsertId();

    ResponseService::json([
        'slot_id' => $newSlotId,
        'tutor_profile_id' => $tutorProfileId,
        'start_time' => $formattedStart,
        'end_time' => $formattedEnd,
        'session_type' => $sessionType,
        'delivery_mode' => $deliveryMode,
        'max_capacity' => $maxCapacity
    ], 'Availability slot created successfully', 201);
}

ResponseService::error('Method Not Allowed', 405);
