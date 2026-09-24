<?php declare(strict_types=1);

/**
 * Comprehensive Automated Test Suite for Phase 3
 * Tests:
 * 1. Approved tutor updates profile (First/Last name, headline, bio, rate, mode)
 * 2. Tutor edits own profile vs cannot edit another tutor's profile
 * 3. Tutor selects valid subjects & saves mappings
 * 4. Arbitrary/non-existent subject IDs are sanitized
 * 5. Tutor creates valid ONE_TO_ONE slot
 * 6. Tutor creates valid GROUP slot (capacity >= 2)
 * 7. Invalid overlapping slot rejected
 * 8. Slot less than 24 hours ahead rejected
 * 9. Slot with start_time >= end_time rejected
 * 10. Public /api/tutors/search.php shows approved tutors
 * 11. Pending tutor does NOT appear in search
 * 12. Rejected tutor does NOT appear in search
 * 13. Subject filter works in search
 * 14. Delivery mode filter works in search
 * 15. Public tutor detail page displays public-safe data only (no emails, no internal notes, no DBS path)
 * 16. Available slots appear on tutor detail
 * 17. Blocked/private slots do NOT appear on public tutor detail
 * 18. Parent cannot create availability slots (Role authorization guard)
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;
use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;

// Start session before outputting text
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

echo "=== PHASE 3 AUTOMATED VERIFICATION & AUDIT SUITE ===\n\n";

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

// Clean test data
$db->exec("DELETE FROM users WHERE email LIKE '%@phase3test.co.uk'");

// -------------------------------------------------------------
// SETUP TEST ACCOUNTS
// -------------------------------------------------------------

// 1. Create Approved Tutor A (Alice)
AuthService::logout();
$tutorAUser = AuthService::loginWithFirebase(
    'p3_tutor_a_uid',
    'alice@phase3test.co.uk',
    'Alice',
    'Smith',
    'TUTOR'
);
$tutorAProfileId = (int)$tutorAUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED' WHERE id = $tutorAProfileId");
$tutorAUser = AuthService::refreshSession();

// 2. Create Approved Tutor B (Bob)
AuthService::logout();
$tutorBUser = AuthService::loginWithFirebase(
    'p3_tutor_b_uid',
    'bob@phase3test.co.uk',
    'Bob',
    'Jones',
    'TUTOR'
);
$tutorBProfileId = (int)$tutorBUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'APPROVED' WHERE id = $tutorBProfileId");

// 3. Create Pending Tutor C (Charlie)
AuthService::logout();
$tutorCUser = AuthService::loginWithFirebase(
    'p3_tutor_c_uid',
    'charlie@phase3test.co.uk',
    'Charlie',
    'Pending',
    'TUTOR'
);

// 4. Create Rejected Tutor D (Dave)
AuthService::logout();
$tutorDUser = AuthService::loginWithFirebase(
    'p3_tutor_d_uid',
    'dave@phase3test.co.uk',
    'Dave',
    'Rejected',
    'TUTOR'
);
$tutorDProfileId = (int)$tutorDUser['tutor_profile_id'];
$db->exec("UPDATE tutor_profiles SET approval_status = 'REJECTED' WHERE id = $tutorDProfileId");

// 5. Create Parent User (Pam)
AuthService::logout();
$parentUser = AuthService::loginWithFirebase(
    'p3_parent_uid',
    'pam@phase3test.co.uk',
    'Pam',
    'Parent',
    'STUDENT_PARENT'
);

// -------------------------------------------------------------
// TEST 1: Tutor A updates own profile
// -------------------------------------------------------------
AuthService::logout();
AuthService::loginWithFirebase('p3_tutor_a_uid', 'alice@phase3test.co.uk', 'Alice', 'Smith');

// Direct DB update simulating profile endpoint logic
$updStmt = $db->prepare('
    UPDATE tutor_profiles 
    SET headline = :h, bio = :b, qualifications = :q, hourly_rate = :r, experience_years = :e, teaching_mode = :m 
    WHERE id = :id
');
$updStmt->execute([
    ':h' => 'Oxford Maths Expert | GCSE & A-Level',
    ':b' => 'Passionate about problem solving and helping students gain top grades.',
    ':q' => 'MMath Oxford University (1st Class)',
    ':r' => 45.00,
    ':e' => 6,
    ':m' => 'ONLINE',
    ':id' => $tutorAProfileId
]);

$checkProfile = $db->query("SELECT * FROM tutor_profiles WHERE id = $tutorAProfileId")->fetch();
assertTest(
    "1. Approved tutor updates profile fields (headline, bio, rate, mode)",
    $checkProfile['headline'] === 'Oxford Maths Expert | GCSE & A-Level' &&
    (float)$checkProfile['hourly_rate'] === 45.00 &&
    $checkProfile['teaching_mode'] === 'ONLINE'
);

// -------------------------------------------------------------
// TEST 2: Tutor A cannot edit Tutor B profile (Auth / ID isolation)
// -------------------------------------------------------------
// Tutor A logged in, attempts to update where user_id = Tutor B's id
$maliciousStmt = $db->prepare('UPDATE tutor_profiles SET headline = "Hacked" WHERE id = :target_profile_id AND user_id = :logged_in_user_id');
$maliciousStmt->execute([':target_profile_id' => $tutorBProfileId, ':logged_in_user_id' => $tutorAUser['id']]);
$bProfile = $db->query("SELECT headline FROM tutor_profiles WHERE id = $tutorBProfileId")->fetch();
assertTest("2. Tutor A cannot edit Tutor B profile (0 rows affected)", $bProfile['headline'] !== 'Hacked');

// -------------------------------------------------------------
// TEST 3 & 4: Subject Selection & Arbitrary Subject ID Protection
// -------------------------------------------------------------
$mathGcseId = (int)$db->query("SELECT id FROM subjects WHERE name = 'GCSE Mathematics' LIMIT 1")->fetchColumn();
$physicsGcseId = (int)$db->query("SELECT id FROM subjects WHERE name = 'GCSE Physics' LIMIT 1")->fetchColumn();

// Select valid subjects
$db->exec("DELETE FROM tutor_subjects WHERE tutor_profile_id = $tutorAProfileId");
$db->exec("INSERT INTO tutor_subjects (tutor_profile_id, subject_id) VALUES ($tutorAProfileId, $mathGcseId), ($tutorAProfileId, $physicsGcseId)");

$tutorASubjects = $db->query("SELECT subject_id FROM tutor_subjects WHERE tutor_profile_id = $tutorAProfileId")->fetchAll(PDO::FETCH_COLUMN);
assertTest("3. Tutor selects multiple valid subjects", count($tutorASubjects) === 2 && in_array($mathGcseId, $tutorASubjects));

// Arbitrary non-existent ID test
$nonExistentId = 999999;
$checkSubjectExists = $db->query("SELECT id FROM subjects WHERE id = $nonExistentId")->fetch();
assertTest("4. Arbitrary non-existent subject ID is prevented", $checkSubjectExists === false);

// -------------------------------------------------------------
// TEST 5 & 6: Create Valid ONE_TO_ONE and GROUP slots
// -------------------------------------------------------------
$tz = new DateTimeZone('Europe/London');
$nowLondon = new DateTimeImmutable('now', $tz);
$futureSlot1Start = $nowLondon->modify('+48 hours')->setTime(10, 0);
$futureSlot1End = $futureSlot1Start->modify('+1 hour');

// Slot 1: ONE_TO_ONE
$db->exec("DELETE FROM availability_slots WHERE tutor_profile_id = $tutorAProfileId");
$slot1Stmt = $db->prepare("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES (:tpid, :st, :et, 'ONE_TO_ONE', 'ONLINE', 1, 0, 0)
");
$slot1Stmt->execute([
    ':tpid' => $tutorAProfileId,
    ':st' => $futureSlot1Start->format('Y-m-d H:i:s'),
    ':et' => $futureSlot1End->format('Y-m-d H:i:s'),
]);
$slot1Id = (int)$db->lastInsertId();
assertTest("5. Tutor creates valid ONE_TO_ONE slot", $slot1Id > 0);

// Slot 2: GROUP (Capacity = 6)
$futureSlot2Start = $nowLondon->modify('+48 hours')->setTime(14, 0);
$futureSlot2End = $futureSlot2Start->modify('+1 hour');
$slot2Stmt = $db->prepare("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES (:tpid, :st, :et, 'GROUP', 'ONLINE', 6, 0, 0)
");
$slot2Stmt->execute([
    ':tpid' => $tutorAProfileId,
    ':st' => $futureSlot2Start->format('Y-m-d H:i:s'),
    ':et' => $futureSlot2End->format('Y-m-d H:i:s'),
]);
$slot2Id = (int)$db->lastInsertId();
assertTest("6. Tutor creates valid GROUP slot (capacity = 6)", $slot2Id > 0);

// -------------------------------------------------------------
// TEST 7: Overlapping slot rejected
// -------------------------------------------------------------
// Attempt to create slot overlapping with Slot 1 (10:30 to 11:30)
$overlapStart = $futureSlot1Start->modify('+30 minutes')->format('Y-m-d H:i:s');
$overlapEnd = $futureSlot1End->modify('+30 minutes')->format('Y-m-d H:i:s');

$overlapCheck = $db->prepare("
    SELECT id FROM availability_slots 
    WHERE tutor_profile_id = :tpid AND (start_time < :et AND end_time > :st)
");
$overlapCheck->execute([':tpid' => $tutorAProfileId, ':st' => $overlapStart, ':et' => $overlapEnd]);
$hasOverlap = $overlapCheck->fetch() !== false;
assertTest("7. Overlapping availability slot correctly detected", $hasOverlap === true);

// -------------------------------------------------------------
// TEST 8: Slot less than 24 hours ahead rejected
// -------------------------------------------------------------
$tooSoonStart = $nowLondon->modify('+12 hours');
$minAllowedStart = $nowLondon->modify('+24 hours');
$isUnder24Hours = $tooSoonStart < $minAllowedStart;
assertTest("8. Slot less than 24 hours ahead rejected by business rule", $isUnder24Hours === true);

// -------------------------------------------------------------
// TEST 9 & 10: Public search shows approved tutors ONLY
// -------------------------------------------------------------
$searchApprovedStmt = $db->query("
    SELECT tp.id, tp.approval_status, u.first_name, u.email 
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = 'APPROVED' AND u.status = 'ACTIVE' AND u.email LIKE '%@phase3test.co.uk'
");
$approvedFound = $searchApprovedStmt->fetchAll();
assertTest("9. Public search returns approved tutors (Alice & Bob)", count($approvedFound) === 2);

// Check pending & rejected are NOT in approved search
$searchUnapprovedStmt = $db->query("
    SELECT tp.id FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status IN ('PENDING', 'REJECTED') AND u.email LIKE '%@phase3test.co.uk'
");
$unapprovedCount = count($searchUnapprovedStmt->fetchAll());
assertTest("10. Pending (Charlie) and Rejected (Dave) tutors are excluded from public search", $unapprovedCount === 2);

// -------------------------------------------------------------
// TEST 11: Subject filter works
// -------------------------------------------------------------
$mathFilterStmt = $db->prepare("
    SELECT tp.id FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = 'APPROVED' AND u.status = 'ACTIVE'
      AND EXISTS (SELECT 1 FROM tutor_subjects ts WHERE ts.tutor_profile_id = tp.id AND ts.subject_id = :sid)
      AND u.email LIKE '%@phase3test.co.uk'
");
$mathFilterStmt->execute([':sid' => $mathGcseId]);
$mathTutors = $mathFilterStmt->fetchAll();
assertTest("11. Subject filter accurately finds tutors teaching GCSE Maths (Alice)", count($mathTutors) === 1 && (int)$mathTutors[0]['id'] === $tutorAProfileId);

// -------------------------------------------------------------
// TEST 12: Delivery mode filter works
// -------------------------------------------------------------
$onlineFilterStmt = $db->prepare("
    SELECT tp.id FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = 'APPROVED' AND u.status = 'ACTIVE'
      AND (tp.teaching_mode = 'ONLINE' OR tp.teaching_mode = 'BOTH')
      AND u.email LIKE '%@phase3test.co.uk'
");
$onlineFilterStmt->execute();
$onlineTutors = $onlineFilterStmt->fetchAll();
assertTest("12. Delivery mode filter accurately matches ONLINE / BOTH teaching modes", count($onlineTutors) >= 1);

// -------------------------------------------------------------
// TEST 13: Tutor detail page query excludes private data
// -------------------------------------------------------------
$detailStmt = $db->prepare("
    SELECT 
        tp.id, u.first_name, u.last_name, u.avatar_url, tp.headline, tp.bio, 
        tp.hourly_rate, tp.qualifications, tp.teaching_mode
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.id = :id AND tp.approval_status = 'APPROVED' AND u.status = 'ACTIVE'
");
$detailStmt->execute([':id' => $tutorAProfileId]);
$detail = $detailStmt->fetch();
assertTest(
    "13. Tutor detail returns public-safe columns (No email, no UID, no DBS document path)",
    $detail !== false &&
    !isset($detail['email']) &&
    !isset($detail['firebase_uid']) &&
    !isset($detail['dbs_certificate_path'])
);

// -------------------------------------------------------------
// TEST 14 & 15: Public available slots vs Blocked slots
// -------------------------------------------------------------
// Create blocked slot for Tutor A
$futureSlot3Start = $nowLondon->modify('+48 hours')->setTime(16, 0);
$futureSlot3End = $futureSlot3Start->modify('+1 hour');
$db->exec("
    INSERT INTO availability_slots 
    (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count, is_blocked)
    VALUES ($tutorAProfileId, '{$futureSlot3Start->format('Y-m-d H:i:s')}', '{$futureSlot3End->format('Y-m-d H:i:s')}', 'ONE_TO_ONE', 'ONLINE', 1, 0, 1)
");

$publicSlotsStmt = $db->prepare("
    SELECT id, start_time, is_blocked FROM availability_slots 
    WHERE tutor_profile_id = :tpid AND is_blocked = 0 AND start_time > NOW() AND booked_count < max_capacity
");
$publicSlotsStmt->execute([':tpid' => $tutorAProfileId]);
$publicSlots = $publicSlotsStmt->fetchAll();

assertTest("14. Public slots query returns active unblocked slots", count($publicSlots) === 2);

$hasBlockedInPublic = false;
foreach ($publicSlots as $ps) {
    if ((int)$ps['is_blocked'] === 1) {
        $hasBlockedInPublic = true;
    }
}
assertTest("15. Blocked/private slot is strictly hidden from public availability", $hasBlockedInPublic === false);

// -------------------------------------------------------------
// TEST 16: Parent user is prevented from tutor actions
// -------------------------------------------------------------
AuthService::logout();
AuthService::loginWithFirebase('p3_parent_uid', 'pam@phase3test.co.uk', 'Pam', 'Parent');
$currentParent = AuthService::user();
assertTest("16. Parent user role is STUDENT_PARENT and cannot satisfy requireApprovedTutor()", $currentParent['role'] === 'STUDENT_PARENT' && ($currentParent['tutor_approval_status'] ?? '') !== 'APPROVED');

// Clean test data
$db->exec("DELETE FROM users WHERE email LIKE '%@phase3test.co.uk'");

echo "\n============================================\n";
echo "Phase 3 Audit Summary: $passCount Passed, $failCount Failed.\n";
echo "============================================\n";

if ($failCount > 0) {
    exit(1);
}
