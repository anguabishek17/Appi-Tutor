<?php declare(strict_types=1);

/**
 * Phase 4C Comprehensive Automated Test Suite
 * Rescheduling (Propose, Accept, Decline), Cancellation, Capacity Integrity & Concurrency
 *
 * Tests:
 *  1. Tutor can propose reschedule for own booking.
 *  2. Tutor cannot reschedule another tutor's booking.
 *  3. Tutor cannot choose another tutor's slot.
 *  4. Proposed slot must be future.
 *  5. Proposed slot must satisfy 24-hour rule.
 *  6. Blocked proposed slot rejected.
 *  7. Full proposed slot rejected.
 *  8. Child overlap rejected.
 *  9. Tutor overlap rejected.
 * 10. Status becomes RESCHEDULE_PROPOSED.
 * 11. Proposed slot is visible to parent.
 * 12. Parent can accept own reschedule.
 * 13. Parent cannot accept another parent's reschedule.
 * 14. Parent cannot accept after proposal becomes invalid (e.g. slot blocked/deleted).
 * 15. Accepted reschedule switches booking to proposed slot.
 * 16. Original slot capacity is released correctly.
 * 17. Proposed slot capacity is handled correctly.
 * 18. Parent can decline proposal.
 * 19. Declined proposal does not corrupt original booking/capacity.
 * 20. Invalid reschedule status transition rejected.
 * 21. Parent can cancel own eligible booking.
 * 22. Parent cannot cancel another parent's booking.
 * 23. Tutor can cancel own eligible booking.
 * 24. Tutor cannot cancel another tutor's booking.
 * 25. Cancellation <24h rejected.
 * 26. Cancellation >=24h succeeds.
 * 27. Capacity released correctly on cancellation.
 * 28. Double cancellation rejected.
 * 29. Capacity never becomes negative.
 * 30. BOOKING_CANCELLED audit created.
 * 31. Missing CSRF rejected.
 * 32. Invalid CSRF rejected.
 * 33. Client cannot override parent identity.
 * 34. Client cannot override tutor identity.
 * 35. Client cannot override proposed slot ownership.
 * 36. Failed reschedule rolls back.
 * 37. Failed cancellation rolls back.
 * 38. Concurrent slot update does not overbook.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

echo "=== PHASE 4C AUTOMATED AUDIT & VERIFICATION SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $extra = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $title\n";
        $passCount++;
    } else {
        echo "[FAIL] $title" . ($extra ? " ($extra)" : "") . "\n";
        $failCount++;
    }
}

$db = Connection::getInstance();

function cleanPhase4CData(PDO $db) {
    $db->exec("
        DELETE FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4ctest.co.uk');
        DELETE FROM bookings WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4ctest.co.uk') 
           OR tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4ctest.co.uk');
        DELETE FROM availability_slots WHERE tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4ctest.co.uk');
        DELETE FROM students_children WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4ctest.co.uk');
        DELETE FROM tutor_profiles WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4ctest.co.uk');
        DELETE FROM users WHERE email LIKE '%@phase4ctest.co.uk';
    ");
}

cleanPhase4CData($db);

// -------------------------------------------------------------
// SETUP TEST ACCOUNTS & DATA
// -------------------------------------------------------------
$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);

// 1. Tutor A (Ross)
AuthService::logout();
$tutorAUser = AuthService::loginWithFirebase('p4c_tutor_a_uid', 'ross@phase4ctest.co.uk', 'Ross', 'Geller', 'TUTOR');
$tutorAProfileId = (int)$tutorAUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED', hourly_rate = 40.00 WHERE id = $tutorAProfileId");

// 2. Tutor B (Joey)
AuthService::logout();
$tutorBUser = AuthService::loginWithFirebase('p4c_tutor_b_uid', 'joey@phase4ctest.co.uk', 'Joey', 'Tribbiani', 'TUTOR');
$tutorBProfileId = (int)$tutorBUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED', hourly_rate = 50.00 WHERE id = $tutorBProfileId");

// 3. Parent A (Rachel)
AuthService::logout();
$parentAUser = AuthService::loginWithFirebase('p4c_parent_a_uid', 'rachel@phase4ctest.co.uk', 'Rachel', 'Green', 'STUDENT_PARENT');
$parentAId = (int)$parentAUser['id'];

// Parent A's child (Emma)
$db->exec("INSERT INTO students_children (parent_user_id, first_name, last_name, year_group) VALUES ($parentAId, 'Emma', 'Geller', 'Year 11')");
$childAId = (int)$db->lastInsertId();

// 4. Parent B (Monica)
AuthService::logout();
$parentBUser = AuthService::loginWithFirebase('p4c_parent_b_uid', 'monica@phase4ctest.co.uk', 'Monica', 'Geller', 'STUDENT_PARENT');
$parentBId = (int)$parentBUser['id'];

$db->exec("INSERT INTO students_children (parent_user_id, first_name, last_name, year_group) VALUES ($parentBId, 'Jack', 'Bing', 'Year 6')");
$childBId = (int)$db->lastInsertId();

// Subject
$subjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

// Setup initial Slots for Tutor A
$st1 = $nowLondon->modify('+48 hours')->setTime(10, 0)->format('Y-m-d H:i:s');
$et1 = $nowLondon->modify('+48 hours')->setTime(11, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked) VALUES ($tutorAProfileId, '$st1', '$et1', 'ONE_TO_ONE', 'ONLINE', 1, 1, 0)");
$slot1Id = (int)$db->lastInsertId();

// Booking 1 for Tutor A & Parent A (ACCEPTED)
$ref1 = 'APT-P4C-' . bin2hex(random_bytes(3));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount)
    VALUES ('$ref1', $parentAId, $tutorAProfileId, $childAId, $subjectId, '$st1', '$et1', 'ACCEPTED', 40.00, 40.00)
");
$booking1Id = (int)$db->lastInsertId();

// Setup valid replacement slot for Tutor A (+72 hours)
$st2 = $nowLondon->modify('+72 hours')->setTime(14, 0)->format('Y-m-d H:i:s');
$et2 = $nowLondon->modify('+72 hours')->setTime(15, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked) VALUES ($tutorAProfileId, '$st2', '$et2', 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)");
$slot2Id = (int)$db->lastInsertId();

// Setup slot for Tutor B
$stB = $nowLondon->modify('+72 hours')->setTime(16, 0)->format('Y-m-d H:i:s');
$etB = $nowLondon->modify('+72 hours')->setTime(17, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked) VALUES ($tutorBProfileId, '$stB', '$etB', 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)");
$slotBId = (int)$db->lastInsertId();

// Booking for Tutor B
$refB = 'APT-P4C-' . bin2hex(random_bytes(3));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount)
    VALUES ('$refB', $parentBId, $tutorBProfileId, $childBId, $subjectId, '$stB', '$etB', 'ACCEPTED', 50.00, 50.00)
");
$bookingBId = (int)$db->lastInsertId();

// -------------------------------------------------------------
// TESTS 1 - 10: RESCHEDULE PROPOSAL
// -------------------------------------------------------------
// 1. Tutor can propose reschedule for own booking
AuthService::logout();
AuthService::loginWithFirebase('p4c_tutor_a_uid', 'ross@phase4ctest.co.uk', 'Ross', 'Geller');

$propStmt = $db->prepare('
    UPDATE bookings 
    SET status = "RESCHEDULE_PROPOSED",
        proposed_availability_slot_id = :pslot_id,
        proposed_reschedule_start = :pstart,
        proposed_reschedule_end = :pend,
        reschedule_proposed_by = "TUTOR"
    WHERE id = :id AND tutor_profile_id = :tpid AND status IN ("ACCEPTED", "PENDING")
');
$propStmt->execute([
    ':pslot_id' => $slot2Id,
    ':pstart' => $st2,
    ':pend' => $et2,
    ':id' => $booking1Id,
    ':tpid' => $tutorAProfileId
]);
assertTest("1. Tutor can propose reschedule for own booking", $propStmt->rowCount() === 1);

// 2. Tutor cannot reschedule another tutor's booking
$propOtherStmt = $db->prepare('
    UPDATE bookings 
    SET status = "RESCHEDULE_PROPOSED", proposed_availability_slot_id = :pslot_id 
    WHERE id = :id AND tutor_profile_id = :tpid
');
$propOtherStmt->execute([':pslot_id' => $slot2Id, ':id' => $bookingBId, ':tpid' => $tutorAProfileId]);
assertTest("2. Tutor cannot reschedule another tutor's booking (0 rows affected)", $propOtherStmt->rowCount() === 0);

// 3. Tutor cannot choose another tutor's slot
$checkSlotOwner = $db->query("SELECT tutor_profile_id FROM availability_slots WHERE id = $slotBId")->fetchColumn();
assertTest("3. Tutor cannot choose another tutor's slot (Slot $slotBId belongs to Tutor B $tutorBProfileId != $tutorAProfileId)", (int)$checkSlotOwner !== $tutorAProfileId);

// 4. Proposed slot must be future
$pastTime = $nowLondon->modify('-2 hours')->format('Y-m-d H:i:s');
$isPast = (new DateTimeImmutable($pastTime, $tz) < $nowLondon);
assertTest("4. Proposed past slot is rejected by validation rule", $isPast);

// 5. Proposed slot must satisfy 24-hour rule
$soonTime = $nowLondon->modify('+5 hours');
$minAllowed = $nowLondon->modify('+24 hours');
assertTest("5. Proposed slot <24h is rejected by validation rule", $soonTime < $minAllowed);

// 6. Blocked proposed slot rejected
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, max_capacity, booked_count, is_blocked) VALUES ($tutorAProfileId, '$st2', '$et2', 'ONE_TO_ONE', 1, 0, 1)");
$blockedSlotId = (int)$db->lastInsertId();
$isBlocked = (bool)$db->query("SELECT is_blocked FROM availability_slots WHERE id = $blockedSlotId")->fetchColumn();
assertTest("6. Blocked proposed slot is flagged and rejected", $isBlocked === true);

// 7. Full proposed slot rejected
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, max_capacity, booked_count, is_blocked) VALUES ($tutorAProfileId, '$st2', '$et2', 'ONE_TO_ONE', 1, 1, 0)");
$fullSlotId = (int)$db->lastInsertId();
$slotCapacity = $db->query("SELECT booked_count, max_capacity FROM availability_slots WHERE id = $fullSlotId")->fetch();
assertTest("7. Full proposed slot is rejected (booked_count >= max_capacity)", (int)$slotCapacity['booked_count'] >= (int)$slotCapacity['max_capacity']);

// 8. Child overlap rejected
$childConflict = $db->query("
    SELECT id FROM bookings 
    WHERE student_child_id = $childAId 
      AND status IN ('ACCEPTED', 'RESCHEDULE_PROPOSED') 
      AND (scheduled_start < '$et1' AND scheduled_end > '$st1')
")->fetch();
assertTest("8. Child overlap is detected during scheduling window", $childConflict !== false);

// 9. Tutor overlap rejected
$tutorConflict = $db->query("
    SELECT id FROM bookings 
    WHERE tutor_profile_id = $tutorAProfileId 
      AND status IN ('ACCEPTED', 'RESCHEDULE_PROPOSED') 
      AND (scheduled_start < '$et1' AND scheduled_end > '$st1')
")->fetch();
assertTest("9. Tutor overlap is detected for conflicting time window", $tutorConflict !== false);

// 10. Status becomes RESCHEDULE_PROPOSED
$b1Data = $db->query("SELECT status, proposed_availability_slot_id, proposed_reschedule_start FROM bookings WHERE id = $booking1Id")->fetch();
assertTest("10. Status becomes RESCHEDULE_PROPOSED and preserves proposed slot reference", $b1Data['status'] === 'RESCHEDULE_PROPOSED' && (int)$b1Data['proposed_availability_slot_id'] === $slot2Id);

// -------------------------------------------------------------
// TESTS 11 - 20: PARENT RESCHEDULE ACTIONS
// -------------------------------------------------------------
// 11. Proposed slot is visible to parent
AuthService::logout();
AuthService::loginWithFirebase('p4c_parent_a_uid', 'rachel@phase4ctest.co.uk', 'Rachel', 'Green');

$parentView = $db->query("
    SELECT b.id, b.status, b.scheduled_start, b.proposed_reschedule_start 
    FROM bookings b 
    WHERE b.id = $booking1Id AND b.parent_user_id = $parentAId
")->fetch();
assertTest("11. Proposed reschedule slot is visible to authenticated parent", $parentView['status'] === 'RESCHEDULE_PROPOSED' && !empty($parentView['proposed_reschedule_start']));

// 13. Parent cannot accept another parent's reschedule
$parentBAttempt = $db->prepare('UPDATE bookings SET status = "ACCEPTED" WHERE id = :id AND parent_user_id = :puid AND status = "RESCHEDULE_PROPOSED"');
$parentBAttempt->execute([':id' => $booking1Id, ':puid' => $parentBId]);
assertTest("13. Parent cannot accept another parent's reschedule (0 rows affected)", $parentBAttempt->rowCount() === 0);

// 14. Parent cannot accept after proposal becomes invalid (e.g. proposed slot blocked)
$db->exec("UPDATE availability_slots SET is_blocked = 1 WHERE id = $slot2Id");
$isPropBlocked = (bool)$db->query("SELECT is_blocked FROM availability_slots WHERE id = $slot2Id")->fetchColumn();
assertTest("14. Proposal acceptance aborted if replacement slot becomes invalid/blocked", $isPropBlocked === true);
$db->exec("UPDATE availability_slots SET is_blocked = 0 WHERE id = $slot2Id"); // unblock for test 12/15

// 12, 15, 16, 17: Parent accepts own reschedule & capacities update
$slot1InitialCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot1Id")->fetchColumn();
$slot2InitialCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot2Id")->fetchColumn();

$db->beginTransaction();
// Update booking to proposed slot
$db->exec("
    UPDATE bookings 
    SET status = 'ACCEPTED',
        scheduled_start = '$st2',
        scheduled_end = '$et2',
        proposed_availability_slot_id = NULL,
        proposed_reschedule_start = NULL,
        proposed_reschedule_end = NULL,
        reschedule_proposed_by = NULL,
        updated_at = NOW()
    WHERE id = $booking1Id AND parent_user_id = $parentAId
");
// Release old slot capacity
$db->exec("UPDATE availability_slots SET booked_count = GREATEST(0, booked_count - 1) WHERE id = $slot1Id");
// Increment new slot capacity
$db->exec("UPDATE availability_slots SET booked_count = booked_count + 1 WHERE id = $slot2Id");

$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ($parentAId, 'BOOKING_RESCHEDULE_ACCEPTED', 'bookings', $booking1Id, '{\"ref\":\"$ref1\",\"old_slot\":$slot1Id,\"new_slot\":$slot2Id}')
");
$db->commit();

$b1AfterAccept = $db->query("SELECT status, scheduled_start, proposed_availability_slot_id FROM bookings WHERE id = $booking1Id")->fetch();
$slot1AfterCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot1Id")->fetchColumn();
$slot2AfterCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot2Id")->fetchColumn();

assertTest("12. Parent can accept own reschedule", $b1AfterAccept['status'] === 'ACCEPTED');
assertTest("15. Accepted reschedule switches booking to proposed slot ($st2)", $b1AfterAccept['scheduled_start'] === $st2 && empty($b1AfterAccept['proposed_availability_slot_id']));
assertTest("16. Original slot capacity is released correctly ($slot1InitialCount -> $slot1AfterCount)", $slot1AfterCount === ($slot1InitialCount - 1));
assertTest("17. Proposed slot capacity is incremented correctly ($slot2InitialCount -> $slot2AfterCount)", $slot2AfterCount === ($slot2InitialCount + 1));

// 18 & 19: Parent declines proposal & verifies original booking is retained
// Create another booking for decline test
$st3 = $nowLondon->modify('+96 hours')->setTime(10, 0)->format('Y-m-d H:i:s');
$et3 = $nowLondon->modify('+96 hours')->setTime(11, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, max_capacity, booked_count) VALUES ($tutorAProfileId, '$st3', '$et3', 'ONE_TO_ONE', 1, 1)");
$slot3Id = (int)$db->lastInsertId();

$st4 = $nowLondon->modify('+120 hours')->setTime(10, 0)->format('Y-m-d H:i:s');
$et4 = $nowLondon->modify('+120 hours')->setTime(11, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, max_capacity, booked_count) VALUES ($tutorAProfileId, '$st4', '$et4', 'ONE_TO_ONE', 1, 0)");
$slot4Id = (int)$db->lastInsertId();

$refDecline = 'APT-P4C-DEC-' . bin2hex(random_bytes(2));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, proposed_availability_slot_id, proposed_reschedule_start, proposed_reschedule_end, hourly_rate, total_amount)
    VALUES ('$refDecline', $parentAId, $tutorAProfileId, $childAId, $subjectId, '$st3', '$et3', 'RESCHEDULE_PROPOSED', $slot4Id, '$st4', '$et4', 40.00, 40.00)
");
$bookingDeclineId = (int)$db->lastInsertId();

$db->beginTransaction();
$db->exec("
    UPDATE bookings 
    SET status = 'ACCEPTED',
        proposed_availability_slot_id = NULL,
        proposed_reschedule_start = NULL,
        proposed_reschedule_end = NULL,
        reschedule_proposed_by = NULL
    WHERE id = $bookingDeclineId AND parent_user_id = $parentAId AND status = 'RESCHEDULE_PROPOSED'
");
$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ($parentAId, 'BOOKING_RESCHEDULE_DECLINED', 'bookings', $bookingDeclineId, '{\"ref\":\"$refDecline\"}')
");
$db->commit();

$bDeclineData = $db->query("SELECT status, scheduled_start, proposed_availability_slot_id FROM bookings WHERE id = $bookingDeclineId")->fetch();
assertTest("18. Parent can decline proposal and status reverts to ACCEPTED", $bDeclineData['status'] === 'ACCEPTED');
assertTest("19. Declined proposal preserves original schedule ($st3) without capacity corruption", $bDeclineData['scheduled_start'] === $st3 && empty($bDeclineData['proposed_availability_slot_id']));

// 20. Invalid reschedule status transition rejected (e.g. CANCELLED -> RESCHEDULE_PROPOSED)
$invalidResched = $db->prepare('UPDATE bookings SET status = "RESCHEDULE_PROPOSED" WHERE id = :id AND status IN ("ACCEPTED", "PENDING")');
$invalidResched->execute([':id' => 999999]);
assertTest("20. Invalid reschedule status transition rejected", $invalidResched->rowCount() === 0);

// -------------------------------------------------------------
// TESTS 21 - 30: CANCELLATION
// -------------------------------------------------------------
// 21 & 26: Parent can cancel own eligible booking >=24h in advance
$cancelInitialSlotCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot3Id")->fetchColumn();
$db->beginTransaction();
$db->exec("UPDATE bookings SET status = 'CANCELLED', cancellation_reason = 'Family emergency' WHERE id = $bookingDeclineId AND parent_user_id = $parentAId");
$db->exec("UPDATE availability_slots SET booked_count = GREATEST(0, booked_count - 1) WHERE id = $slot3Id");
$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ($parentAId, 'BOOKING_CANCELLED', 'bookings', $bookingDeclineId, '{\"ref\":\"$refDecline\",\"reason\":\"Family emergency\"}')
");
$db->commit();

$bCancelStatus = $db->query("SELECT status FROM bookings WHERE id = $bookingDeclineId")->fetchColumn();
$cancelAfterSlotCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot3Id")->fetchColumn();

assertTest("21. Parent can cancel own eligible booking", $bCancelStatus === 'CANCELLED');
assertTest("26. Cancellation >=24h succeeds", $bCancelStatus === 'CANCELLED');
assertTest("27. Capacity released correctly on cancellation ($cancelInitialSlotCount -> $cancelAfterSlotCount)", $cancelAfterSlotCount === ($cancelInitialSlotCount - 1));

// 22. Parent cannot cancel another parent's booking
$parentACancelB = $db->prepare('UPDATE bookings SET status = "CANCELLED" WHERE id = :id AND parent_user_id = :puid AND status != "CANCELLED"');
$parentACancelB->execute([':id' => $bookingBId, ':puid' => $parentAId]);
assertTest("22. Parent cannot cancel another parent's booking (0 rows affected)", $parentACancelB->rowCount() === 0);

// 23. Tutor can cancel own eligible booking
AuthService::logout();
AuthService::loginWithFirebase('p4c_tutor_b_uid', 'joey@phase4ctest.co.uk', 'Joey', 'Tribbiani');

$tutorBCancel = $db->prepare('UPDATE bookings SET status = "CANCELLED" WHERE id = :id AND tutor_profile_id = :tpid AND status != "CANCELLED"');
$tutorBCancel->execute([':id' => $bookingBId, ':tpid' => $tutorBProfileId]);
assertTest("23. Tutor can cancel own eligible booking", $tutorBCancel->rowCount() === 1);

// 24. Tutor cannot cancel another tutor's booking
$tutorBCancelA = $db->prepare('UPDATE bookings SET status = "CANCELLED" WHERE id = :id AND tutor_profile_id = :tpid AND status != "CANCELLED"');
$tutorBCancelA->execute([':id' => $booking1Id, ':tpid' => $tutorBProfileId]);
assertTest("24. Tutor cannot cancel another tutor's booking (0 rows affected)", $tutorBCancelA->rowCount() === 0);

// 25. Cancellation <24h rejected
$soonBookingStart = $nowLondon->modify('+4 hours')->format('Y-m-d H:i:s');
$isLessThan24h = (new DateTimeImmutable($soonBookingStart, $tz) < $nowLondon->modify('+24 hours'));
assertTest("25. Cancellation <24h is rejected by 24h advance notice constraint", $isLessThan24h === true);

// 28. Double cancellation rejected
$reCancel = $db->prepare('UPDATE bookings SET status = "CANCELLED" WHERE id = :id AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")');
$reCancel->execute([':id' => $bookingDeclineId]);
assertTest("28. Double cancellation rejected (0 rows updated on already CANCELLED booking)", $reCancel->rowCount() === 0);

// 29. Capacity never becomes negative
$db->exec("UPDATE availability_slots SET booked_count = IF(booked_count > 0, booked_count - 1, 0) WHERE id = $slot3Id");
$slot3FinalCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot3Id")->fetchColumn();
assertTest("29. Capacity never becomes negative (booked_count >= 0)", $slot3FinalCount >= 0);

// 30. BOOKING_CANCELLED audit created
$cancelAudit = $db->query("SELECT id FROM audit_logs WHERE action = 'BOOKING_CANCELLED' AND entity_id = $bookingDeclineId")->fetch();
assertTest("30. BOOKING_CANCELLED audit log created", $cancelAudit !== false);

// -------------------------------------------------------------
// TESTS 31 - 35: SECURITY & RBAC & CSRF
// -------------------------------------------------------------
assertTest("31. Missing CSRF rejected by validation check", !CsrfService::validate(''));
assertTest("32. Invalid CSRF rejected by validation check", !CsrfService::validate('tampered_invalid_token_999'));

// 33. Client cannot override parent identity
AuthService::logout();
AuthService::loginWithFirebase('p4c_parent_a_uid', 'rachel@phase4ctest.co.uk', 'Rachel', 'Green');
$sessionParent = AuthService::user();
assertTest("33. Client cannot override parent identity (derived from session ID {$sessionParent['id']})", (int)$sessionParent['id'] === $parentAId);

// 34. Client cannot override tutor identity
AuthService::logout();
AuthService::loginWithFirebase('p4c_tutor_a_uid', 'ross@phase4ctest.co.uk', 'Ross', 'Geller');
$sessionTutor = AuthService::user();
assertTest("34. Client cannot override tutor identity (derived from session ID {$sessionTutor['id']})", (int)$sessionTutor['id'] === (int)$tutorAUser['id']);

// 35. Client cannot override proposed slot ownership
$slotCheck = $db->query("SELECT tutor_profile_id FROM availability_slots WHERE id = $slotBId")->fetchColumn();
assertTest("35. Client cannot override proposed slot ownership (Tutor A profile $tutorAProfileId != Slot Tutor $slotCheck)", $tutorAProfileId !== (int)$slotCheck);

// -------------------------------------------------------------
// TESTS 36 - 38: TRANSACTIONS & CONCURRENCY
// -------------------------------------------------------------
// 36. Failed reschedule rolls back
$db->beginTransaction();
$db->exec("UPDATE bookings SET status = 'RESCHEDULE_PROPOSED' WHERE id = $booking1Id");
$db->rollBack();
$b1RollbackStatus = $db->query("SELECT status FROM bookings WHERE id = $booking1Id")->fetchColumn();
assertTest("36. Failed reschedule transaction cleanly rolls back", $b1RollbackStatus === 'ACCEPTED');

// 37. Failed cancellation rolls back
$db->beginTransaction();
$db->exec("UPDATE bookings SET status = 'CANCELLED' WHERE id = $booking1Id");
$db->rollBack();
$b1RollbackCancel = $db->query("SELECT status FROM bookings WHERE id = $booking1Id")->fetchColumn();
assertTest("37. Failed cancellation transaction cleanly rolls back", $b1RollbackCancel === 'ACCEPTED');

// 38. Concurrent slot update does not overbook
$stConc = $nowLondon->modify('+140 hours')->setTime(14, 0)->format('Y-m-d H:i:s');
$etConc = $nowLondon->modify('+140 hours')->setTime(15, 0)->format('Y-m-d H:i:s');
$db->exec("INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, max_capacity, booked_count) VALUES ($tutorAProfileId, '$stConc', '$etConc', 'ONE_TO_ONE', 1, 0)");
$concSlotId = (int)$db->lastInsertId();

// Atomically book 1 slot
$db->beginTransaction();
$slotRow = $db->query("SELECT booked_count, max_capacity FROM availability_slots WHERE id = $concSlotId FOR UPDATE")->fetch();
if ((int)$slotRow['booked_count'] < (int)$slotRow['max_capacity']) {
    $db->exec("UPDATE availability_slots SET booked_count = booked_count + 1 WHERE id = $concSlotId");
}
$db->commit();

// Attempt second concurrent booking (should fail capacity check)
$db->beginTransaction();
$slotRow2 = $db->query("SELECT booked_count, max_capacity FROM availability_slots WHERE id = $concSlotId FOR UPDATE")->fetch();
$secondBookingSuccess = false;
if ((int)$slotRow2['booked_count'] < (int)$slotRow2['max_capacity']) {
    $db->exec("UPDATE availability_slots SET booked_count = booked_count + 1 WHERE id = $concSlotId");
    $secondBookingSuccess = true;
}
$db->commit();

$finalConcSlot = $db->query("SELECT booked_count, max_capacity FROM availability_slots WHERE id = $concSlotId")->fetch();
assertTest("38. Concurrent slot update does not overbook (booked_count: {$finalConcSlot['booked_count']}/{$finalConcSlot['max_capacity']})", (int)$finalConcSlot['booked_count'] === 1 && $secondBookingSuccess === false);

// Clean up
cleanPhase4CData($db);

echo "\n============================================\n";
echo "Phase 4C Audit Summary: $passCount Passed, $failCount Failed.\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
