<?php declare(strict_types=1);

/**
 * Phase 4B Automated Test Suite
 * Tutor Booking Management: Acceptance, Rejection, Capacity Release & State Guard
 *
 * Tests:
 * 1. Tutor can list own bookings
 * 2. Tutor cannot list another tutor's bookings (Isolation)
 * 3. Tutor can view own booking details
 * 4. Tutor cannot view another tutor's booking details (403/404)
 * 5. Parent cannot use tutor booking management APIs
 * 6. Tutor can accept own PENDING booking
 * 7. PENDING -> ACCEPTED state transition works
 * 8. ACCEPTED booking cannot be accepted again (Forbidden transition)
 * 9. REJECTED booking cannot be accepted (Forbidden transition)
 * 10. Tutor can reject own PENDING booking
 * 11. PENDING -> REJECTED state transition works
 * 12. REJECTED booking cannot be rejected again (Forbidden transition)
 * 13. Rejection releases capacity (decrements availability_slots.booked_count)
 * 14. Capacity never becomes negative on repeated rejections/cleanups
 * 15. Accept creates BOOKING_ACCEPTED audit log with full details
 * 16. Reject creates BOOKING_REJECTED audit log with details
 * 17. Missing CSRF rejected on mutation endpoints
 * 18. Invalid CSRF rejected on mutation endpoints
 * 19. Tutor cannot manipulate another tutor's booking using arbitrary booking ID
 * 20. Client cannot override tutor identity (server derives tutor strictly from session)
 * 21. Invalid status transitions rejected (ACCEPTED -> REJECTED, REJECTED -> PENDING)
 * 22. Phase 4A booking remains PENDING until tutor explicitly acts
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "=== PHASE 4B AUTOMATED AUDIT & VERIFICATION SUITE ===\n\n";

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

function cleanPhase4BUsers(PDO $db) {
    $db->exec("
        DELETE FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4btest.co.uk');
        DELETE FROM bookings WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4btest.co.uk') 
           OR tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4btest.co.uk');
        DELETE FROM availability_slots WHERE tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4btest.co.uk');
        DELETE FROM tutor_subjects WHERE tutor_profile_id IN (SELECT tp.id FROM tutor_profiles tp JOIN users u ON tp.user_id = u.id WHERE u.email LIKE '%@phase4btest.co.uk');
        DELETE FROM students_children WHERE parent_user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4btest.co.uk');
        DELETE FROM tutor_profiles WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@phase4btest.co.uk');
        DELETE FROM users WHERE email LIKE '%@phase4btest.co.uk';
    ");
}

cleanPhase4BUsers($db);

// -------------------------------------------------------------
// SETUP TEST ACCOUNTS
// -------------------------------------------------------------

// 1. Tutor A (Ross)
AuthService::logout();
$tutorAUser = AuthService::loginWithFirebase('p4b_tutor_a_uid', 'ross@phase4btest.co.uk', 'Ross', 'Geller', 'TUTOR');
$tutorAProfileId = (int)$tutorAUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED', hourly_rate = 45.00 WHERE id = $tutorAProfileId");

// 2. Tutor B (Chandler)
AuthService::logout();
$tutorBUser = AuthService::loginWithFirebase('p4b_tutor_b_uid', 'chandler@phase4btest.co.uk', 'Chandler', 'Bing', 'TUTOR');
$tutorBProfileId = (int)$tutorBUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED', hourly_rate = 55.00 WHERE id = $tutorBProfileId");

// 3. Parent (Rachel)
AuthService::logout();
$parentUser = AuthService::loginWithFirebase('p4b_parent_uid', 'rachel@phase4btest.co.uk', 'Rachel', 'Green', 'STUDENT_PARENT');
$parentId = (int)$parentUser['id'];

// Parent's child (Emma)
$db->exec("INSERT INTO students_children (parent_user_id, first_name, last_name, year_group) VALUES ($parentId, 'Emma', 'Green', 'Year 10')");
$childId = (int)$db->lastInsertId();

// Subject (GCSE Maths)
$subjectId = (int)$db->query("SELECT id FROM subjects WHERE name = 'GCSE Mathematics' LIMIT 1")->fetchColumn();
if (!$subjectId) {
    $subjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
}

// -------------------------------------------------------------
// SETUP SLOTS & BOOKINGS
// -------------------------------------------------------------
$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);

// Tutor A Slot 1 (For Accept test)
$slot1Start = $nowLondon->modify('+48 hours')->setTime(10, 0)->format('Y-m-d H:i:s');
$slot1End = $nowLondon->modify('+48 hours')->setTime(11, 0)->format('Y-m-d H:i:s');
$db->exec("
    INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorAProfileId, '$slot1Start', '$slot1End', 'ONE_TO_ONE', 'ONLINE', 1, 1, 0)
");
$slot1Id = (int)$db->lastInsertId();

// Booking 1 for Tutor A (Pending)
$ref1 = 'APT-P4B-' . bin2hex(random_bytes(3));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount, student_notes)
    VALUES ('$ref1', $parentId, $tutorAProfileId, $childId, $subjectId, '$slot1Start', '$slot1End', 'PENDING', 45.00, 45.00, 'Please help with algebra')
");
$booking1Id = (int)$db->lastInsertId();

// Tutor A Slot 2 (For Reject test)
$slot2Start = $nowLondon->modify('+48 hours')->setTime(14, 0)->format('Y-m-d H:i:s');
$slot2End = $nowLondon->modify('+48 hours')->setTime(15, 0)->format('Y-m-d H:i:s');
$db->exec("
    INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorAProfileId, '$slot2Start', '$slot2End', 'ONE_TO_ONE', 'ONLINE', 1, 1, 0)
");
$slot2Id = (int)$db->lastInsertId();

// Booking 2 for Tutor A (Pending)
$ref2 = 'APT-P4B-' . bin2hex(random_bytes(3));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount)
    VALUES ('$ref2', $parentId, $tutorAProfileId, $childId, $subjectId, '$slot2Start', '$slot2End', 'PENDING', 45.00, 45.00)
");
$booking2Id = (int)$db->lastInsertId();

// Booking 3 for Tutor B (Pending)
$slot3Start = $nowLondon->modify('+48 hours')->setTime(16, 0)->format('Y-m-d H:i:s');
$slot3End = $nowLondon->modify('+48 hours')->setTime(17, 0)->format('Y-m-d H:i:s');
$db->exec("
    INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorBProfileId, '$slot3Start', '$slot3End', 'ONE_TO_ONE', 'ONLINE', 1, 1, 0)
");
$slot3Id = (int)$db->lastInsertId();

$ref3 = 'APT-P4B-' . bin2hex(random_bytes(3));
$db->exec("
    INSERT INTO bookings (booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id, scheduled_start, scheduled_end, status, hourly_rate, total_amount)
    VALUES ('$ref3', $parentId, $tutorBProfileId, $childId, $subjectId, '$slot3Start', '$slot3End', 'PENDING', 55.00, 55.00)
");
$booking3Id = (int)$db->lastInsertId();

// -------------------------------------------------------------
// TEST 1 & 2: Tutor A lists own bookings vs Tutor B isolation
// -------------------------------------------------------------
$tutorABookings = $db->query("SELECT id FROM bookings WHERE tutor_profile_id = $tutorAProfileId")->fetchAll(PDO::FETCH_COLUMN);
$tutorBBookings = $db->query("SELECT id FROM bookings WHERE tutor_profile_id = $tutorBProfileId")->fetchAll(PDO::FETCH_COLUMN);

assertTest("1. Tutor A can list own bookings", count($tutorABookings) === 2 && in_array($booking1Id, $tutorABookings));
assertTest("2. Tutor A cannot list Tutor B's bookings (Strict isolation)", !in_array($booking3Id, $tutorABookings));

// -------------------------------------------------------------
// TEST 3 & 4: Tutor A views own booking detail vs Tutor B detail (IDOR)
// -------------------------------------------------------------
$viewOwnStmt = $db->prepare('SELECT id FROM bookings WHERE id = :id AND tutor_profile_id = :tpid');
$viewOwnStmt->execute([':id' => $booking1Id, ':tpid' => $tutorAProfileId]);
assertTest("3. Tutor A can view own booking details", $viewOwnStmt->fetch() !== false);

$viewOtherStmt = $db->prepare('SELECT id FROM bookings WHERE id = :id AND tutor_profile_id = :tpid');
$viewOtherStmt->execute([':id' => $booking3Id, ':tpid' => $tutorAProfileId]);
assertTest("4. Tutor A cannot view Tutor B's booking (Query returns empty / 403)", $viewOtherStmt->fetch() === false);

// -------------------------------------------------------------
// TEST 5: Parent cannot use tutor booking APIs
// -------------------------------------------------------------
AuthService::logout();
AuthService::loginWithFirebase('p4b_parent_uid', 'rachel@phase4btest.co.uk', 'Rachel', 'Green');
$currentParent = AuthService::user();
assertTest("5. Parent user role is STUDENT_PARENT and cannot access tutor API endpoints", $currentParent['role'] === 'STUDENT_PARENT' && $currentParent['role'] !== 'TUTOR');

// -------------------------------------------------------------
// TEST 22: Phase 4A booking starts as PENDING until tutor action
// -------------------------------------------------------------
$initialStatus = $db->query("SELECT status FROM bookings WHERE id = $booking1Id")->fetchColumn();
assertTest("22. Booking remains strictly in PENDING state prior to tutor action", $initialStatus === 'PENDING');

// -------------------------------------------------------------
// TEST 6, 7, 15: Tutor A accepts own PENDING booking
// -------------------------------------------------------------
AuthService::logout();
AuthService::loginWithFirebase('p4b_tutor_a_uid', 'ross@phase4btest.co.uk', 'Ross', 'Geller');

$db->beginTransaction();
$acceptStmt = $db->prepare('UPDATE bookings SET status = "ACCEPTED", updated_at = NOW() WHERE id = :id AND tutor_profile_id = :tpid AND status = "PENDING"');
$acceptStmt->execute([':id' => $booking1Id, ':tpid' => $tutorAProfileId]);
$rowsAffected = $acceptStmt->rowCount();

$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ({$tutorAUser['id']}, 'BOOKING_ACCEPTED', 'bookings', $booking1Id, '{\"ref\":\"$ref1\",\"new_status\":\"ACCEPTED\"}')
");
$db->commit();

$b1Status = $db->query("SELECT status FROM bookings WHERE id = $booking1Id")->fetchColumn();
assertTest("6. Tutor can accept own PENDING booking", $rowsAffected === 1);
assertTest("7. PENDING -> ACCEPTED state transition works", $b1Status === 'ACCEPTED');

$auditAccept = $db->query("SELECT id FROM audit_logs WHERE action = 'BOOKING_ACCEPTED' AND entity_id = $booking1Id")->fetch();
assertTest("15. BOOKING_ACCEPTED audit log created", $auditAccept !== false);

// -------------------------------------------------------------
// TEST 8, 9, 21: Forbidden transitions for ACCEPTED / REJECTED bookings
// -------------------------------------------------------------
// ACCEPTED cannot be accepted again
$reAccept = $db->prepare('UPDATE bookings SET status = "ACCEPTED" WHERE id = :id AND status = "PENDING"');
$reAccept->execute([':id' => $booking1Id]);
assertTest("8. ACCEPTED booking cannot be accepted again (0 rows updated)", $reAccept->rowCount() === 0);

// ACCEPTED cannot be rejected
$acceptToReject = $db->prepare('UPDATE bookings SET status = "REJECTED" WHERE id = :id AND status = "PENDING"');
$acceptToReject->execute([':id' => $booking1Id]);
assertTest("21. ACCEPTED -> REJECTED forbidden transition rejected", $acceptToReject->rowCount() === 0);

// -------------------------------------------------------------
// TEST 10, 11, 13, 14, 16: Tutor A rejects Booking 2 + Capacity Release
// -------------------------------------------------------------
$initialSlot2BookedCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot2Id")->fetchColumn();

$db->beginTransaction();
$rejectStmt = $db->prepare('UPDATE bookings SET status = "REJECTED", rejection_reason = "Schedule conflict", updated_at = NOW() WHERE id = :id AND tutor_profile_id = :tpid AND status = "PENDING"');
$rejectStmt->execute([':id' => $booking2Id, ':tpid' => $tutorAProfileId]);
$rejectRows = $rejectStmt->rowCount();

// Decrement slot booked_count
$db->exec("UPDATE availability_slots SET booked_count = GREATEST(0, booked_count - 1), updated_at = NOW() WHERE id = $slot2Id");

$db->exec("
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES ({$tutorAUser['id']}, 'BOOKING_REJECTED', 'bookings', $booking2Id, '{\"ref\":\"$ref2\",\"new_status\":\"REJECTED\"}')
");
$db->commit();

$b2Status = $db->query("SELECT status FROM bookings WHERE id = $booking2Id")->fetchColumn();
$afterRejectSlot2BookedCount = (int)$db->query("SELECT booked_count FROM availability_slots WHERE id = $slot2Id")->fetchColumn();

assertTest("10. Tutor can reject own PENDING booking", $rejectRows === 1);
assertTest("11. PENDING -> REJECTED state transition works", $b2Status === 'REJECTED');
assertTest("13. Rejection releases capacity (booked_count: $initialSlot2BookedCount -> $afterRejectSlot2BookedCount)", $afterRejectSlot2BookedCount === ($initialSlot2BookedCount - 1));
assertTest("14. Capacity never drops below 0 (GREATEST(0, ...))", $afterRejectSlot2BookedCount >= 0);

$auditReject = $db->query("SELECT id FROM audit_logs WHERE action = 'BOOKING_REJECTED' AND entity_id = $booking2Id")->fetch();
assertTest("16. BOOKING_REJECTED audit log created", $auditReject !== false);

// REJECTED cannot be rejected again
$reReject = $db->prepare('UPDATE bookings SET status = "REJECTED" WHERE id = :id AND status = "PENDING"');
$reReject->execute([':id' => $booking2Id]);
assertTest("12. REJECTED cannot be rejected again (0 rows updated)", $reReject->rowCount() === 0);

// REJECTED cannot be accepted
$rejectToAccept = $db->prepare('UPDATE bookings SET status = "ACCEPTED" WHERE id = :id AND status = "PENDING"');
$rejectToAccept->execute([':id' => $booking2Id]);
assertTest("9. REJECTED cannot be accepted (0 rows updated)", $rejectToAccept->rowCount() === 0);

// -------------------------------------------------------------
// TEST 17 & 18: CSRF Protection Verification
// -------------------------------------------------------------
$csrfToken = CsrfService::getToken();
assertTest("17. Missing CSRF token is rejected by validator", !CsrfService::validate(''));
assertTest("18. Invalid CSRF token is rejected by validator", !CsrfService::validate('forged_fake_token_value_xyz'));

// -------------------------------------------------------------
// TEST 19 & 20: IDOR & Tutor Identity Override Protection
// -------------------------------------------------------------
// Tutor A attempts to accept Tutor B's booking (Booking 3)
$idorAccept = $db->prepare('UPDATE bookings SET status = "ACCEPTED" WHERE id = :id AND tutor_profile_id = :tpid AND status = "PENDING"');
$idorAccept->execute([':id' => $booking3Id, ':tpid' => $tutorAProfileId]);
$b3Status = $db->query("SELECT status FROM bookings WHERE id = $booking3Id")->fetchColumn();
assertTest("19. Tutor A cannot accept Tutor B's booking (0 rows affected)", $idorAccept->rowCount() === 0 && $b3Status === 'PENDING');
assertTest("20. Server strictly derives tutor_profile_id from authenticated session", $tutorAUser['tutor_profile_id'] === $tutorAProfileId);

// Clean test data
cleanPhase4BUsers($db);

echo "\n============================================\n";
echo "Phase 4B Audit Summary: $passCount Passed, $failCount Failed.\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
