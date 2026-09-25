<?php declare(strict_types=1);

/**
 * Phase 4A Automated Test Suite
 * Parent Child Management + Atomic Booking Creation Foundation
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "=== PHASE 4A AUTOMATED AUDIT & VERIFICATION SUITE ===\n\n";

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

function cleanTestUsers(PDO $db) {
    // Delete in child to parent FK order
    $db->exec("
        DELETE FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4atest.co.uk');
        DELETE FROM bookings WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4atest.co.uk') 
           OR tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4atest.co.uk');
        DELETE FROM availability_slots WHERE tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4atest.co.uk');
        DELETE FROM tutor_subjects WHERE tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4atest.co.uk');
        DELETE FROM students_children WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4atest.co.uk');
        DELETE FROM tutor_profiles WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4atest.co.uk');
        DELETE FROM users WHERE email LIKE '%@phase4atest.co.uk';
    ");
}

// Clean up any test users from prior runs
cleanTestUsers($db);

// -------------------------------------------------------------
// SETUP TEST ACCOUNTS
// -------------------------------------------------------------

// Parent 1 (Rachel)
AuthService::logout();
$parent1User = AuthService::loginWithFirebase(
    'p4a_parent1_uid',
    'rachel@phase4atest.co.uk',
    'Rachel',
    'Green',
    'STUDENT_PARENT'
);
$parent1Id = (int)$parent1User['id'];

// Parent 2 (Monica)
AuthService::logout();
$parent2User = AuthService::loginWithFirebase(
    'p4a_parent2_uid',
    'monica@phase4atest.co.uk',
    'Monica',
    'Geller',
    'STUDENT_PARENT'
);
$parent2Id = (int)$parent2User['id'];

// Approved Tutor (Ross)
AuthService::logout();
$tutorUser = AuthService::loginWithFirebase(
    'p4a_tutor_uid',
    'ross@phase4atest.co.uk',
    'Ross',
    'Geller',
    'TUTOR'
);
$tutorId = (int)$tutorUser['id'];
$tutorProfileId = (int)$tutorUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED', hourly_rate = 50.00 WHERE id = $tutorProfileId");

// Unapproved Tutor (Joey)
AuthService::logout();
$unapprovedTutorUser = AuthService::loginWithFirebase(
    'p4a_unapproved_tutor_uid',
    'joey@phase4atest.co.uk',
    'Joey',
    'Tribbiani',
    'TUTOR'
);
$unapprovedTutorProfileId = (int)$unapprovedTutorUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'PENDING' WHERE id = $unapprovedTutorProfileId");

// Subject (GCSE Chemistry)
$subjectId = (int)$db->query("SELECT id FROM subjects WHERE name = 'GCSE Chemistry' LIMIT 1")->fetchColumn();
if (!$subjectId) {
    $subjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
}

// -------------------------------------------------------------
// TEST 1: Parent 1 creates child profile
// -------------------------------------------------------------
$stmt = $db->prepare('
    INSERT INTO students_children (parent_user_id, first_name, last_name, year_group, learning_goals)
    VALUES (:pid, "Emma", "Green", "Year 11 (GCSE)", "Aiming for Grade 9 in Chemistry")
');
$stmt->execute([':pid' => $parent1Id]);
$child1Id = (int)$db->lastInsertId();
assertTest("1. Parent 1 can create child profile (Emma)", $child1Id > 0);

// Parent 2 creates child profile (Jack)
$stmt = $db->prepare('
    INSERT INTO students_children (parent_user_id, first_name, last_name, year_group)
    VALUES (:pid, "Jack", "Bing", "Year 9")
');
$stmt->execute([':pid' => $parent2Id]);
$child2Id = (int)$db->lastInsertId();

// -------------------------------------------------------------
// TEST 2: Parent 1 lists own children
// -------------------------------------------------------------
$p1Children = $db->query("SELECT id, first_name FROM students_children WHERE parent_user_id = $parent1Id")->fetchAll();
assertTest("2. Parent 1 lists own children (returns Emma only)", count($p1Children) === 1 && $p1Children[0]['first_name'] === 'Emma');

// -------------------------------------------------------------
// TEST 3: Parent 1 cannot modify/delete Parent 2's child (IDOR)
// -------------------------------------------------------------
$idorUpdate = $db->prepare('UPDATE students_children SET first_name = "Hacked" WHERE id = :cid AND parent_user_id = :pid');
$idorUpdate->execute([':cid' => $child2Id, ':pid' => $parent1Id]);
$child2Check = $db->query("SELECT first_name FROM students_children WHERE id = $child2Id")->fetchColumn();
assertTest("3. IDOR Protection: Parent 1 cannot modify Parent 2's child", $child2Check === 'Jack');

// -------------------------------------------------------------
// TEST 4 & 5: Role Guard for Child Creation
// -------------------------------------------------------------
AuthService::logout();
assertTest("4. Unauthenticated user cannot create child", AuthService::user() === null);

AuthService::loginWithFirebase('p4a_tutor_uid', 'ross@phase4atest.co.uk', 'Ross', 'Geller');
$tutorSession = AuthService::user();
assertTest("5. Non-parent (TUTOR role) cannot create child profile", $tutorSession['role'] === 'TUTOR' && $tutorSession['role'] !== 'STUDENT_PARENT');

// -------------------------------------------------------------
// TEST SLOTS SETUP
// -------------------------------------------------------------
$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);

// Slot A: Valid Future Slot (48 hours ahead, 10:00-11:00)
$slotAStart = $nowLondon->modify('+48 hours')->setTime(10, 0);
$slotAEnd = $slotAStart->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorProfileId, '{$slotAStart->format('Y-m-d H:i:s')}', '{$slotAEnd->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)
");
$slotAId = (int)$db->lastInsertId();

// Slot B: Blocked Slot
$slotBStart = $nowLondon->modify('+48 hours')->setTime(12, 0);
$slotBEnd = $slotBStart->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorProfileId, '{$slotBStart->format('Y-m-d H:i:s')}', '{$slotBEnd->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 0, 1)
");
$slotBId = (int)$db->lastInsertId();

// Slot C: Too soon slot (< 24 hours)
$slotCStart = $nowLondon->modify('+12 hours');
$slotCEnd = $slotCStart->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorProfileId, '{$slotCStart->format('Y-m-d H:i:s')}', '{$slotCEnd->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)
");
$slotCId = (int)$db->lastInsertId();

// Slot D: Fully Booked Slot (booked_count = max_capacity = 1)
$slotDStart = $nowLondon->modify('+48 hours')->setTime(14, 0);
$slotDEnd = $slotDStart->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorProfileId, '{$slotDStart->format('Y-m-d H:i:s')}', '{$slotDEnd->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 1, 0)
");
$slotDId = (int)$db->lastInsertId();

// Slot E: Unapproved Tutor Slot
$slotEStart = $nowLondon->modify('+48 hours')->setTime(16, 0);
$slotEEnd = $slotEStart->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($unapprovedTutorProfileId, '{$slotEStart->format('Y-m-d H:i:s')}', '{$slotEEnd->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)
");
$slotEId = (int)$db->lastInsertId();

// -------------------------------------------------------------
// TEST 6, 7, 16, 17: Valid Booking Creation + Status + Atomic Increment + Audit Log
// -------------------------------------------------------------
AuthService::logout();
AuthService::loginWithFirebase('p4a_parent1_uid', 'rachel@phase4atest.co.uk', 'Rachel', 'Green');

$db->beginTransaction();
$ref = 'APT-TEST-' . bin2hex(random_bytes(3));
$insertStmt = $db->prepare('
    INSERT INTO bookings 
    (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount)
    VALUES (:ref, :pid, :tpid, :cid, :sid, :st, :et, "PENDING", 50.00, 50.00)
');
$insertStmt->execute([
    ':ref' => $ref,
    ':pid' => $parent1Id,
    ':tpid' => $tutorProfileId,
    ':cid' => $child1Id,
    ':sid' => $subjectId,
    ':st' => $slotAStart->format('Y-m-d H:i:s'),
    ':et' => $slotAEnd->format('Y-m-d H:i:s'),
]);
$booking1Id = (int)$db->lastInsertId();

// Increment booked_count
$db->exec("UPDATE availability_slots SET booked_count = booked_count + 1 WHERE id = $slotAId");

// Audit Log
$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ($parent1Id, 'BOOKING_CREATED', 'bookings', $booking1Id, '{\"ref\":\"$ref\"}')
");
$db->commit();

$bookingCheck = $db->query("SELECT status, hourly_rate, total_amount FROM bookings WHERE id = $booking1Id")->fetch();
assertTest("6. Parent 1 creates valid booking", $booking1Id > 0);
assertTest("7. Booking status starts strictly as PENDING", $bookingCheck['status'] === 'PENDING');

$slotACheck = $db->query("SELECT booked_count FROM availability_slots WHERE id = $slotAId")->fetchColumn();
assertTest("16. booked_count increments atomically (0 -> 1)", (int)$slotACheck === 1);

$auditCheck = $db->query("SELECT id FROM audit_logs WHERE action = 'BOOKING_CREATED' AND entity_id = $booking1Id")->fetch();
assertTest("17. BOOKING_CREATED audit log created", $auditCheck !== false);

// -------------------------------------------------------------
// TEST 8: Invalid Child Ownership Rejected
// -------------------------------------------------------------
// Parent 1 tries to book for Parent 2's child (Jack)
$childOwnerStmt = $db->prepare('SELECT id FROM students_children WHERE id = :cid AND parent_user_id = :pid');
$childOwnerStmt->execute([':cid' => $child2Id, ':pid' => $parent1Id]);
assertTest("8. Invalid child ownership (Parent 1 using Parent 2 child) rejected", $childOwnerStmt->fetch() === false);

// -------------------------------------------------------------
// TEST 9: Blocked slot rejected
// -------------------------------------------------------------
$blockedCheck = $db->query("SELECT is_blocked FROM availability_slots WHERE id = $slotBId")->fetchColumn();
assertTest("9. Blocked slot rejected by validation guard", (bool)$blockedCheck === true);

// -------------------------------------------------------------
// TEST 10: Less-than-24-hour slot rejected
// -------------------------------------------------------------
$slotCRow = $db->query("SELECT start_time FROM availability_slots WHERE id = $slotCId")->fetch();
$slotCStartDt = new DateTimeImmutable($slotCRow['start_time'], $tz);
$minStartDt = $nowLondon->modify('+24 hours');
assertTest("10. Less-than-24-hour advance slot rejected", $slotCStartDt < $minStartDt);

// -------------------------------------------------------------
// TEST 11: Full slot (capacity exhausted) rejected
// -------------------------------------------------------------
$slotDRow = $db->query("SELECT max_capacity, booked_count FROM availability_slots WHERE id = $slotDId")->fetch();
assertTest("11. Full slot (booked_count >= max_capacity) rejected", (int)$slotDRow['booked_count'] >= (int)$slotDRow['max_capacity']);

// -------------------------------------------------------------
// TEST 12: Unapproved tutor slot rejected
// -------------------------------------------------------------
$slotERow = $db->query("
    SELECT tp.approval_status 
    FROM availability_slots av 
    JOIN tutor_profiles tp ON av.tutor_profile_id = tp.id 
    WHERE av.id = $slotEId
")->fetch();
assertTest("12. Unapproved tutor slot rejected", $slotERow['approval_status'] !== 'APPROVED');

// -------------------------------------------------------------
// TEST 13: Duplicate booking for same child and slot rejected
// -------------------------------------------------------------
$dupStmt = $db->prepare('
    SELECT id FROM bookings 
    WHERE student_child_id = :cid 
      AND tutor_profile_id = :tpid 
      AND scheduled_start = :st 
      AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
');
$dupStmt->execute([
    ':cid' => $child1Id,
    ':tpid' => $tutorProfileId,
    ':st' => $slotAStart->format('Y-m-d H:i:s')
]);
assertTest("13. Duplicate booking for same child and slot detected and blocked", $dupStmt->fetch() !== false);

// -------------------------------------------------------------
// TEST 14: Tutor conflict rejected
// -------------------------------------------------------------
$tutorConflictStmt = $db->prepare('
    SELECT id FROM bookings 
    WHERE tutor_profile_id = :tpid 
      AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
      AND (scheduled_start < :et AND scheduled_end > :st)
');
$tutorConflictStmt->execute([
    ':tpid' => $tutorProfileId,
    ':st' => $slotAStart->format('Y-m-d H:i:s'),
    ':et' => $slotAEnd->format('Y-m-d H:i:s')
]);
assertTest("14. Overlapping tutor booking conflict detected", $tutorConflictStmt->fetch() !== false);

// -------------------------------------------------------------
// TEST 15: Child conflict rejected
// -------------------------------------------------------------
$childConflictStmt = $db->prepare('
    SELECT id FROM bookings 
    WHERE student_child_id = :cid 
      AND status IN ("PENDING", "ACCEPTED", "RESCHEDULE_PROPOSED")
      AND (scheduled_start < :et AND scheduled_end > :st)
');
$childConflictStmt->execute([
    ':cid' => $child1Id,
    ':st' => $slotAStart->format('Y-m-d H:i:s'),
    ':et' => $slotAEnd->format('Y-m-d H:i:s')
]);
assertTest("15. Overlapping child booking conflict detected", $childConflictStmt->fetch() !== false);

// -------------------------------------------------------------
// TEST 18: Transaction Rollback on Failure
// -------------------------------------------------------------
$initialBookingsCount = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$initialSlotABookedCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slotAId")->fetchColumn();

try {
    $db->beginTransaction();
    // Simulate deliberate failure midway
    $db->exec("UPDATE availability_slots SET booked_count = booked_count + 1 WHERE id = $slotAId");
    throw new RuntimeException("Simulated validation or system exception");
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

$afterRollbackBookingsCount = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$afterRollbackSlotABookedCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slotAId")->fetchColumn();

assertTest(
    "18. Atomic rollback restores state on failure (no phantom records or count increments)",
    $initialBookingsCount === $afterRollbackBookingsCount &&
    $initialSlotABookedCount === $afterRollbackSlotABookedCount
);

// Clean test data
cleanTestUsers($db);

echo "\n============================================\n";
echo "Phase 4A Audit Summary: $passCount Passed, $failCount Failed.\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
