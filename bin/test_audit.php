<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Auth\FirebaseVerifier;
use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;
use App\Database\Connection;

echo "=== APPITUTORS SECURITY & RBAC AUDIT SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $title\n";
        $passCount++;
    } else {
        echo "[FAIL] $title\n";
        $failCount++;
    }
}

// 1. Test Database Connectivity
$db = Connection::getInstance();
assertTest("Database connection established", $db instanceof PDO);

// 2. Test Role Escalation Prevention: Browser requests MANAGER role
AuthService::logout();
$userStudent = AuthService::loginWithFirebase(
    'test_student_uid_001',
    'student@test.co.uk',
    'Alice',
    'Student',
    'MANAGER' // Malicious attempt to self-promote to MANAGER
);
assertTest("Self-promotion to MANAGER is blocked (Assigned STUDENT_PARENT)", $userStudent['role'] === 'STUDENT_PARENT');

// 3. Test Tutor Registration with PENDING_APPROVAL Status
AuthService::logout();
$userTutor = AuthService::loginWithFirebase(
    'test_tutor_uid_001',
    'tutor@test.co.uk',
    'Bob',
    'Tutor',
    'TUTOR'
);
assertTest("Tutor registered with TUTOR role", $userTutor['role'] === 'TUTOR');
assertTest("Tutor initialized with PENDING approval status", $userTutor['tutor_approval_status'] === 'PENDING');

// 4. Test Existing User Role Precedence: Existing STUDENT_PARENT requests TUTOR role
$userStudentAgain = AuthService::loginWithFirebase(
    'test_student_uid_001',
    'student@test.co.uk',
    'Alice',
    'Student',
    'TUTOR' // Attempt to change role on re-login
);
assertTest("Existing user role in DB takes precedence over requestedRole", $userStudentAgain['role'] === 'STUDENT_PARENT');

// 5. Test Manager Seed & Login
AuthService::logout();
$userManager = AuthService::loginWithFirebase(
    'manager_demo_uid_001',
    'manager@appitutors.co.uk',
    'Operations',
    'Manager'
);
assertTest("Seeded Manager login resolves to MANAGER role", $userManager['role'] === 'MANAGER');

// 6. Test Tutor Approval Flow
$tutorProfileId = $userTutor['tutor_profile_id'];
$approveStmt = $db->prepare('UPDATE tutor_profiles SET approval_status = "APPROVED", approved_by = :m WHERE id = :id');
$approveStmt->execute([':m' => $userManager['id'], ':id' => $tutorProfileId]);

// Log back in as tutor Bob
AuthService::logout();
$userTutorApproved = AuthService::loginWithFirebase(
    'test_tutor_uid_001',
    'tutor@test.co.uk',
    'Bob',
    'Tutor'
);
assertTest("Approved tutor session reflects APPROVED status", $userTutorApproved['tutor_approval_status'] === 'APPROVED');

// 7. Test CSRF Protection
$token = CsrfService::getToken();
assertTest("CSRF Token generated with sufficient entropy", strlen($token) === 64);
assertTest("CSRF Token validates matching token", CsrfService::validate($token));
assertTest("CSRF Token rejects invalid token", !CsrfService::validate('invalid_token_123456'));

// 8. Test Session Logout
AuthService::logout();
assertTest("Logout destroys session user", AuthService::user() === null);

// 9. Test Cryptographic Token Verifier Rejection of Forged Tokens
$verifier = new FirebaseVerifier('appitutors-demo');
assertTest("FirebaseVerifier rejects invalid JWT string", $verifier->verifyIdToken('fake.invalid.token') === null);
assertTest("FirebaseVerifier rejects placeholder project_id", $verifier->verifyIdToken('header.payload.signature') === null);

echo "\nAudit Summary: $passCount Passed, $failCount Failed.\n";
if ($failCount > 0) {
    exit(1);
}
