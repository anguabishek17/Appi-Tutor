<?php declare(strict_types=1);

/**
 * AppiTutors Phase 5B Demo Reset Script
 * 
 * Safely removes ONLY the demo dataset created by seed_demo.php:
 * 1. Demo Manager (demo.manager@appitutors.co.uk)
 * 2. Demo Tutor (demo.tutor@appitutors.co.uk) + Tutor Profile + Availability Slots + Tutor Subjects
 * 3. Demo Parent (demo.parent@appitutors.co.uk) + Children (Oliver & Sophie)
 * 4. Demo Bookings (References starting with 'DEMO-BK-')
 * 5. Associated Lesson Notes, Notification Logs, and Audit Logs
 * 
 * Non-demo users and records are strictly preserved.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

echo "==================================================\n";
echo "   APPITUTORS PHASE 5B DEMO RESET SCRIPT\n";
echo "==================================================\n";
echo "[WARNING] This will purge only accounts with:\n";
echo " - demo.manager@appitutors.co.uk\n";
echo " - demo.tutor@appitutors.co.uk\n";
echo " - demo.parent@appitutors.co.uk\n";
echo " - and bookings prefixed with 'DEMO-BK-'\n";
echo "==================================================\n\n";

$db = Connection::getInstance();

try {
    $db->beginTransaction();

    // 1. Delete Demo Lesson Notes
    echo "1. Removing Demo Lesson Notes...\n";
    $delNotes = $db->prepare('
        DELETE ln FROM lesson_notes ln
        JOIN bookings b ON ln.booking_id = b.id
        WHERE b.booking_reference LIKE "DEMO-BK-%"
    ');
    $delNotes->execute();

    // 2. Delete Demo Notification Logs
    echo "2. Removing Demo Notification Logs...\n";
    $delNotifs = $db->prepare('
        DELETE nl FROM notification_logs nl
        JOIN bookings b ON nl.booking_id = b.id
        WHERE b.booking_reference LIKE "DEMO-BK-%"
    ');
    $delNotifs->execute();

    // 3. Delete Demo Bookings
    echo "3. Removing Demo Bookings...\n";
    $delBookings = $db->prepare('DELETE FROM bookings WHERE booking_reference LIKE "DEMO-BK-%"');
    $delBookings->execute();

    // 4. Delete Demo Audit Logs
    echo "4. Removing Demo Audit Logs...\n";
    $delAudit = $db->prepare('DELETE FROM audit_logs WHERE user_agent = "AppiTutors Demo Seed"');
    $delAudit->execute();

    // 5. Delete Demo Children
    echo "5. Removing Demo Children...\n";
    $delChildren = $db->prepare('
        DELETE sc FROM students_children sc
        JOIN users u ON sc.parent_user_id = u.id
        WHERE u.email = "demo.parent@appitutors.co.uk"
    ');
    $delChildren->execute();

    // 6. Delete Demo Tutor Availability Slots & Subjects
    echo "6. Removing Demo Availability Slots & Subjects...\n";
    $delSlots = $db->prepare('
        DELETE a FROM availability_slots a
        JOIN tutor_profiles tp ON a.tutor_profile_id = tp.id
        JOIN users u ON tp.user_id = u.id
        WHERE u.email = "demo.tutor@appitutors.co.uk"
    ');
    $delSlots->execute();

    $delTutorSubj = $db->prepare('
        DELETE ts FROM tutor_subjects ts
        JOIN tutor_profiles tp ON ts.tutor_profile_id = tp.id
        JOIN users u ON tp.user_id = u.id
        WHERE u.email = "demo.tutor@appitutors.co.uk"
    ');
    $delTutorSubj->execute();

    // 7. Delete Demo Tutor Profile
    echo "7. Removing Demo Tutor Profile...\n";
    $delTutorProfile = $db->prepare('
        DELETE tp FROM tutor_profiles tp
        JOIN users u ON tp.user_id = u.id
        WHERE u.email = "demo.tutor@appitutors.co.uk"
    ');
    $delTutorProfile->execute();

    // 8. Delete Demo Users
    echo "8. Removing Demo User Accounts...\n";
    $delUsers = $db->prepare('
        DELETE FROM users 
        WHERE email IN (
            "demo.manager@appitutors.co.uk",
            "demo.tutor@appitutors.co.uk",
            "demo.parent@appitutors.co.uk"
        )
    ');
    $delUsers->execute();

    $db->commit();

    echo "\n==================================================\n";
    echo "   DEMO RESET COMPLETED SUCCESSFULLY!\n";
    echo "   All demo records have been cleanly removed.\n";
    echo "   Non-demo platform records were untouched.\n";
    echo "==================================================\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n[ERROR] Demo reset failed: " . $e->getMessage() . "\n";
    exit(1);
}
