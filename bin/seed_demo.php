<?php declare(strict_types=1);

/**
 * AppiTutors Phase 5B Demo Seed Script
 * 
 * Safely & Idempotently Seeds:
 * 1. Demo Manager: Sarah Mitchell (demo.manager@appitutors.co.uk)
 * 2. Demo Tutor: James Carter (demo.tutor@appitutors.co.uk) - Approved UK Maths & Physics specialist
 * 3. Demo Parent: Emily Wilson (demo.parent@appitutors.co.uk) - 2 Children (Oliver & Sophie)
 * 4. Tutor Subjects: GCSE Maths, GCSE Physics, A-Level Maths, A-Level Physics
 * 5. Future Availability Slots in Europe/London timezone
 * 6. Four Representative Booking Scenarios:
 *    - Scenario A: PENDING (Oliver Wilson - GCSE Maths)
 *    - Scenario B: ACCEPTED / Upcoming (Sophie Wilson - GCSE Physics)
 *    - Scenario C: RESCHEDULE_PROPOSED (Oliver Wilson - A-Level Maths)
 *    - Scenario D: COMPLETED historical lesson with Attendance & Lesson Notes (Sophie Wilson - GCSE Maths)
 * 7. Transactional notification logs and audit logs for the demo data
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

echo "==================================================\n";
echo "   APPITUTORS PHASE 5B DEMO SEED SCRIPT\n";
echo "==================================================\n\n";

$db = Connection::getInstance();

try {
    $db->beginTransaction();

    // ----------------------------------------------------
    // 1. Roles Lookup
    // ----------------------------------------------------
    $rolesStmt = $db->query('SELECT id, name FROM roles');
    $roles = [];
    foreach ($rolesStmt->fetchAll() as $r) {
        $roles[$r['name']] = (int)$r['id'];
    }

    if (!isset($roles['MANAGER'], $roles['TUTOR'], $roles['STUDENT_PARENT'])) {
        throw new RuntimeException("Required roles not found in database.");
    }

    // ----------------------------------------------------
    // 2. Demo Manager: Sarah Mitchell
    // ----------------------------------------------------
    echo "1. Seeding Demo Manager (Sarah Mitchell)...\n";
    $mgrStmt = $db->prepare('
        INSERT INTO users (firebase_uid, role_id, email, first_name, last_name, phone, avatar_url, status)
        VALUES (:fuid, :role_id, :email, :first_name, :last_name, :phone, :avatar_url, "ACTIVE")
        ON DUPLICATE KEY UPDATE 
            role_id = VALUES(role_id),
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            avatar_url = VALUES(avatar_url),
            status = VALUES(status)
    ');
    $mgrStmt->execute([
        ':fuid' => 'demo_manager_uid_mitchell',
        ':role_id' => $roles['MANAGER'],
        ':email' => 'demo.manager@appitutors.co.uk',
        ':first_name' => 'Sarah',
        ':last_name' => 'Mitchell',
        ':phone' => '+44 20 7946 0192',
        ':avatar_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80'
    ]);

    $mgrIdStmt = $db->prepare('SELECT id FROM users WHERE email = "demo.manager@appitutors.co.uk" LIMIT 1');
    $mgrIdStmt->execute();
    $managerId = (int)$mgrIdStmt->fetchColumn();

    // ----------------------------------------------------
    // 3. Demo Tutor: James Carter
    // ----------------------------------------------------
    echo "2. Seeding Demo Tutor (James Carter)...\n";
    $tutorUserStmt = $db->prepare('
        INSERT INTO users (firebase_uid, role_id, email, first_name, last_name, phone, avatar_url, status)
        VALUES (:fuid, :role_id, :email, :first_name, :last_name, :phone, :avatar_url, "ACTIVE")
        ON DUPLICATE KEY UPDATE 
            role_id = VALUES(role_id),
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            avatar_url = VALUES(avatar_url),
            status = VALUES(status)
    ');
    $tutorUserStmt->execute([
        ':fuid' => 'demo_tutor_uid_carter',
        ':role_id' => $roles['TUTOR'],
        ':email' => 'demo.tutor@appitutors.co.uk',
        ':first_name' => 'James',
        ':last_name' => 'Carter',
        ':phone' => '+44 7700 900142',
        ':avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80'
    ]);

    $tutorUserIdStmt = $db->prepare('SELECT id FROM users WHERE email = "demo.tutor@appitutors.co.uk" LIMIT 1');
    $tutorUserIdStmt->execute();
    $tutorUserId = (int)$tutorUserIdStmt->fetchColumn();

    // Tutor Profile
    $tutorProfStmt = $db->prepare('
        INSERT INTO tutor_profiles (
            user_id, headline, bio, hourly_rate, experience_years, 
            qualifications, approval_status, teaching_mode, approved_by, 
            approval_notes, is_featured, dbs_verified_at
        ) VALUES (
            :user_id, :headline, :bio, :rate, :exp, 
            :quals, "APPROVED", :mode, :approved_by, 
            :notes, 1, NOW()
        )
        ON DUPLICATE KEY UPDATE 
            headline = VALUES(headline),
            bio = VALUES(bio),
            hourly_rate = VALUES(hourly_rate),
            experience_years = VALUES(experience_years),
            qualifications = VALUES(qualifications),
            approval_status = "APPROVED",
            teaching_mode = VALUES(teaching_mode),
            approved_by = VALUES(approved_by),
            approval_notes = VALUES(approval_notes),
            is_featured = 1
    ');
    $tutorProfStmt->execute([
        ':user_id' => $tutorUserId,
        ':headline' => 'Experienced UK Maths & Physics Specialist | GCSE & A-Level',
        ':bio' => "Experienced UK tutor supporting students across GCSE and A-Level Maths and Physics. Focused on clear explanations, confidence building, structured practice, and measurable exam progress.\n\nOver the past 6 years, I have helped more than 80 students achieve their target grades in Edexcel, AQA, and OCR exam boards with tailored problem-solving strategies.",
        ':rate' => 45.00,
        ':exp' => 6,
        ':quals' => "BSc (Hons) Mathematics & Physics (First Class) - University of Bristol\nPGCE Secondary Mathematics\nEnhanced DBS Certificate (Updated 2026)",
        ':mode' => 'BOTH',
        ':approved_by' => $managerId,
        ':notes' => 'Verified enhanced DBS certificate and verified degree credentials. Approved for full platform teaching.'
    ]);

    $tutorProfIdStmt = $db->prepare('SELECT id FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
    $tutorProfIdStmt->execute([':uid' => $tutorUserId]);
    $tutorProfileId = (int)$tutorProfIdStmt->fetchColumn();

    // ----------------------------------------------------
    // 4. Assign Tutor Subjects
    // ----------------------------------------------------
    echo "3. Assigning Tutor Subjects (GCSE & A-Level Maths/Physics)...\n";
    $subjectsToAssign = [
        'GCSE Mathematics (Foundation & Higher)',
        'GCSE Physics',
        'A-Level Mathematics',
        'A-Level Physics'
    ];

    $subjectIds = [];
    foreach ($subjectsToAssign as $sName) {
        $sStmt = $db->prepare('SELECT id FROM subjects WHERE name = :name LIMIT 1');
        $sStmt->execute([':name' => $sName]);
        $sId = $sStmt->fetchColumn();
        if ($sId) {
            $subjectIds[$sName] = (int)$sId;
            $tsStmt = $db->prepare('
                INSERT INTO tutor_subjects (tutor_profile_id, subject_id, custom_rate)
                VALUES (:tpid, :sid, :rate)
                ON DUPLICATE KEY UPDATE custom_rate = VALUES(custom_rate)
            ');
            $tsStmt->execute([
                ':tpid' => $tutorProfileId,
                ':sid' => (int)$sId,
                ':rate' => 45.00
            ]);
        }
    }

    // ----------------------------------------------------
    // 5. Demo Parent: Emily Wilson & Children
    // ----------------------------------------------------
    echo "4. Seeding Demo Parent (Emily Wilson) & Children...\n";
    $parentStmt = $db->prepare('
        INSERT INTO users (firebase_uid, role_id, email, first_name, last_name, phone, avatar_url, status)
        VALUES (:fuid, :role_id, :email, :first_name, :last_name, :phone, :avatar_url, "ACTIVE")
        ON DUPLICATE KEY UPDATE 
            role_id = VALUES(role_id),
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            phone = VALUES(phone),
            avatar_url = VALUES(avatar_url),
            status = VALUES(status)
    ');
    $parentStmt->execute([
        ':fuid' => 'demo_parent_uid_wilson',
        ':role_id' => $roles['STUDENT_PARENT'],
        ':email' => 'demo.parent@appitutors.co.uk',
        ':first_name' => 'Emily',
        ':last_name' => 'Wilson',
        ':phone' => '+44 7700 900581',
        ':avatar_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80'
    ]);

    $parentIdStmt = $db->prepare('SELECT id FROM users WHERE email = "demo.parent@appitutors.co.uk" LIMIT 1');
    $parentIdStmt->execute();
    $parentUserId = (int)$parentIdStmt->fetchColumn();

    // Child 1: Oliver Wilson (Year 10)
    $c1Stmt = $db->prepare('
        SELECT id FROM students_children 
        WHERE parent_user_id = :pid AND first_name = "Oliver" AND last_name = "Wilson" 
        LIMIT 1
    ');
    $c1Stmt->execute([':pid' => $parentUserId]);
    $child1Id = $c1Stmt->fetchColumn();

    if ($child1Id) {
        $c1Upd = $db->prepare('
            UPDATE students_children 
            SET year_group = "Year 10", school_name = "St Albans High School", learning_goals = :goals
            WHERE id = :id
        ');
        $c1Upd->execute([
            ':goals' => 'Improve GCSE Mathematics confidence and prepare for upcoming mock examinations.',
            ':id' => $child1Id
        ]);
    } else {
        $c1Ins = $db->prepare('
            INSERT INTO students_children (parent_user_id, first_name, last_name, date_of_birth, year_group, school_name, learning_goals)
            VALUES (:pid, "Oliver", "Wilson", "2010-04-15", "Year 10", "St Albans High School", :goals)
        ');
        $c1Ins->execute([
            ':pid' => $parentUserId,
            ':goals' => 'Improve GCSE Mathematics confidence and prepare for upcoming mock examinations.'
        ]);
        $child1Id = (int)$db->lastInsertId();
    }

    // Child 2: Sophie Wilson (Year 8)
    $c2Stmt = $db->prepare('
        SELECT id FROM students_children 
        WHERE parent_user_id = :pid AND first_name = "Sophie" AND last_name = "Wilson" 
        LIMIT 1
    ');
    $c2Stmt->execute([':pid' => $parentUserId]);
    $child2Id = $c2Stmt->fetchColumn();

    if ($child2Id) {
        $c2Upd = $db->prepare('
            UPDATE students_children 
            SET year_group = "Year 8", school_name = "St Albans Academy", learning_goals = :goals
            WHERE id = :id
        ');
        $c2Upd->execute([
            ':goals' => 'Build stronger foundations in Mathematics and improve problem-solving skills.',
            ':id' => $child2Id
        ]);
    } else {
        $c2Ins = $db->prepare('
            INSERT INTO students_children (parent_user_id, first_name, last_name, date_of_birth, year_group, school_name, learning_goals)
            VALUES (:pid, "Sophie", "Wilson", "2012-09-22", "Year 8", "St Albans Academy", :goals)
        ');
        $c2Ins->execute([
            ':pid' => $parentUserId,
            ':goals' => 'Build stronger foundations in Mathematics and improve problem-solving skills.'
        ]);
        $child2Id = (int)$db->lastInsertId();
    }

    // ----------------------------------------------------
    // 6. Demo Availability Slots (Europe/London)
    // ----------------------------------------------------
    echo "5. Seeding Future & Historical Availability Slots...\n";
    $tz = new DateTimeZone('Europe/London');
    $now = new DateTimeImmutable('now', $tz);

    // Slot 1: Tomorrow + 2 days (Future for Scenario A - Pending)
    $slot1Start = $now->modify('+2 days')->setTime(16, 0, 0);
    $slot1End = $slot1Start->modify('+1 hour');

    // Slot 2: Tomorrow + 3 days (Future for Scenario B - Accepted)
    $slot2Start = $now->modify('+3 days')->setTime(17, 0, 0);
    $slot2End = $slot2Start->modify('+1 hour');

    // Slot 3: Tomorrow + 4 days (Future for Scenario C - Reschedule Proposed Original)
    $slot3Start = $now->modify('+4 days')->setTime(10, 0, 0);
    $slot3End = $slot3Start->modify('+1 hour');

    // Slot 4: Tomorrow + 5 days (Future for Reschedule Target)
    $slot4Start = $now->modify('+5 days')->setTime(14, 0, 0);
    $slot4End = $slot4Start->modify('+1 hour');

    // Slot 5: Tomorrow + 6 days (Open slot for demo booking by client)
    $slot5Start = $now->modify('+6 days')->setTime(11, 0, 0);
    $slot5End = $slot5Start->modify('+1 hour');

    // Slot 6: Tomorrow + 7 days (Open Group Slot)
    $slot6Start = $now->modify('+7 days')->setTime(15, 0, 0);
    $slot6End = $slot6Start->modify('+1 hour');

    // Slot 7: Historical slot 3 days ago (Completed)
    $slot7Start = $now->modify('-3 days')->setTime(16, 0, 0);
    $slot7End = $slot7Start->modify('+1 hour');

    $slotDefinitions = [
        ['start' => $slot1Start, 'end' => $slot1End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 1],
        ['start' => $slot2Start, 'end' => $slot2End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 1],
        ['start' => $slot3Start, 'end' => $slot3End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 1],
        ['start' => $slot4Start, 'end' => $slot4End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 0],
        ['start' => $slot5Start, 'end' => $slot5End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 0],
        ['start' => $slot6Start, 'end' => $slot6End, 'type' => 'GROUP', 'mode' => 'ONLINE', 'cap' => 6, 'booked' => 0],
        ['start' => $slot7Start, 'end' => $slot7End, 'type' => 'ONE_TO_ONE', 'mode' => 'ONLINE', 'cap' => 1, 'booked' => 1],
    ];

    $slotIds = [];
    foreach ($slotDefinitions as $idx => $sDef) {
        $sStartStr = $sDef['start']->format('Y-m-d H:i:s');
        $sEndStr = $sDef['end']->format('Y-m-d H:i:s');

        $checkStmt = $db->prepare('
            SELECT id FROM availability_slots 
            WHERE tutor_profile_id = :tpid AND start_time = :start 
            LIMIT 1
        ');
        $checkStmt->execute([':tpid' => $tutorProfileId, ':start' => $sStartStr]);
        $existingSlotId = $checkStmt->fetchColumn();

        if ($existingSlotId) {
            $upd = $db->prepare('
                UPDATE availability_slots 
                SET end_time = :end, session_type = :type, delivery_mode = :mode, max_capacity = :cap, booked_count = :booked
                WHERE id = :id
            ');
            $upd->execute([
                ':end' => $sEndStr,
                ':type' => $sDef['type'],
                ':mode' => $sDef['mode'],
                ':cap' => $sDef['cap'],
                ':booked' => $sDef['booked'],
                ':id' => $existingSlotId
            ]);
            $slotIds[$idx] = (int)$existingSlotId;
        } else {
            $ins = $db->prepare('
                INSERT INTO availability_slots (tutor_profile_id, start_time, end_time, session_type, delivery_mode, max_capacity, booked_count)
                VALUES (:tpid, :start, :end, :type, :mode, :cap, :booked)
            ');
            $ins->execute([
                ':tpid' => $tutorProfileId,
                ':start' => $sStartStr,
                ':end' => $sEndStr,
                ':type' => $sDef['type'],
                ':mode' => $sDef['mode'],
                ':cap' => $sDef['cap'],
                ':booked' => $sDef['booked']
            ]);
            $slotIds[$idx] = (int)$db->lastInsertId();
        }
    }

    // ----------------------------------------------------
    // 7. Demo Bookings Scenarios
    // ----------------------------------------------------
    echo "6. Seeding Demo Booking Scenarios (PENDING, ACCEPTED, RESCHEDULE_PROPOSED, COMPLETED)...\n";

    $gcseMathsId = $subjectIds['GCSE Mathematics (Foundation & Higher)'] ?? 17;
    $gcsePhysicsId = $subjectIds['GCSE Physics'] ?? 22;
    $aLevelMathsId = $subjectIds['A-Level Mathematics'] ?? 33;

    // Helper to upsert booking by reference
    $upsertBooking = function (
        string $ref,
        int $parentId,
        int $tutorId,
        ?int $childId,
        int $subjectId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $status,
        string $attStatus,
        ?DateTimeImmutable $rescheduleStart = null,
        ?DateTimeImmutable $rescheduleEnd = null,
        ?int $rescheduleSlotId = null,
        ?string $rescheduleBy = null,
        string $notes = ''
    ) use ($db) {
        $check = $db->prepare('SELECT id FROM bookings WHERE booking_reference = :ref LIMIT 1');
        $check->execute([':ref' => $ref]);
        $existingId = $check->fetchColumn();

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');
        $rStartStr = $rescheduleStart ? $rescheduleStart->format('Y-m-d H:i:s') : null;
        $rEndStr = $rescheduleEnd ? $rescheduleEnd->format('Y-m-d H:i:s') : null;

        if ($existingId) {
            $upd = $db->prepare('
                UPDATE bookings 
                SET parent_user_id = :pid,
                    tutor_profile_id = :tpid,
                    student_child_id = :cid,
                    subject_id = :sid,
                    scheduled_start = :start,
                    scheduled_end = :end,
                    status = :status,
                    attendance_status = :att,
                    proposed_reschedule_start = :rstart,
                    proposed_reschedule_end = :rend,
                    proposed_availability_slot_id = :rslot,
                    reschedule_proposed_by = :rby,
                    hourly_rate = 45.00,
                    total_amount = 45.00,
                    student_notes = :notes,
                    meeting_link = "https://meet.google.com/demo-appi-lesson"
                WHERE id = :id
            ');
            $upd->execute([
                ':pid' => $parentId,
                ':tpid' => $tutorId,
                ':cid' => $childId,
                ':sid' => $subjectId,
                ':start' => $startStr,
                ':end' => $endStr,
                ':status' => $status,
                ':att' => $attStatus,
                ':rstart' => $rStartStr,
                ':rend' => $rEndStr,
                ':rslot' => $rescheduleSlotId,
                ':rby' => $rescheduleBy,
                ':notes' => $notes,
                ':id' => $existingId
            ]);
            return (int)$existingId;
        } else {
            $ins = $db->prepare('
                INSERT INTO bookings (
                    booking_reference, parent_user_id, tutor_profile_id, student_child_id, subject_id,
                    scheduled_start, scheduled_end, status, attendance_status,
                    proposed_reschedule_start, proposed_reschedule_end, proposed_availability_slot_id, reschedule_proposed_by,
                    hourly_rate, total_amount, student_notes, meeting_link
                ) VALUES (
                    :ref, :pid, :tpid, :cid, :sid,
                    :start, :end, :status, :att,
                    :rstart, :rend, :rslot, :rby,
                    45.00, 45.00, :notes, "https://meet.google.com/demo-appi-lesson"
                )
            ');
            $ins->execute([
                ':ref' => $ref,
                ':pid' => $parentId,
                ':tpid' => $tutorId,
                ':cid' => $childId,
                ':sid' => $subjectId,
                ':start' => $startStr,
                ':end' => $endStr,
                ':status' => $status,
                ':att' => $attStatus,
                ':rstart' => $rStartStr,
                ':rend' => $rEndStr,
                ':rslot' => $rescheduleSlotId,
                ':rby' => $rescheduleBy,
                ':notes' => $notes
            ]);
            return (int)$db->lastInsertId();
        }
    };

    // Scenario A: PENDING (Oliver Wilson - GCSE Maths)
    $b1Id = $upsertBooking(
        'DEMO-BK-PEND-01',
        $parentUserId,
        $tutorProfileId,
        (int)$child1Id,
        $gcseMathsId,
        $slot1Start,
        $slot1End,
        'PENDING',
        'NOT_RECORDED',
        null, null, null, null,
        'Oliver would like to review quadratic equations and algebraic fractions.'
    );

    // Scenario B: ACCEPTED (Sophie Wilson - GCSE Physics)
    $b2Id = $upsertBooking(
        'DEMO-BK-ACPT-02',
        $parentUserId,
        $tutorProfileId,
        (int)$child2Id,
        $gcsePhysicsId,
        $slot2Start,
        $slot2End,
        'ACCEPTED',
        'NOT_RECORDED',
        null, null, null, null,
        'Sophie needs help understanding forces, vectors, and Newton’s laws of motion.'
    );

    // Scenario C: RESCHEDULE_PROPOSED (Oliver Wilson - A-Level Maths)
    $b3Id = $upsertBooking(
        'DEMO-BK-RSCH-03',
        $parentUserId,
        $tutorProfileId,
        (int)$child1Id,
        $aLevelMathsId,
        $slot3Start,
        $slot3End,
        'RESCHEDULE_PROPOSED',
        'NOT_RECORDED',
        $slot4Start,
        $slot4End,
        $slotIds[3],
        'TUTOR',
        'Request to shift session by 24 hours due to tutor university seminar clash.'
    );

    // Scenario D: COMPLETED (Sophie Wilson - GCSE Maths, 3 days ago)
    $b4Id = $upsertBooking(
        'DEMO-BK-CMPL-04',
        $parentUserId,
        $tutorProfileId,
        (int)$child2Id,
        $gcseMathsId,
        $slot7Start,
        $slot7End,
        'COMPLETED',
        'ATTENDED',
        null, null, null, null,
        'Initial assessment and foundation revision in algebra.'
    );

    // ----------------------------------------------------
    // 8. Demo Lesson Notes for Scenario D
    // ----------------------------------------------------
    echo "7. Seeding Lesson Notes for Completed Booking...\n";
    $notesStmt = $db->prepare('
        INSERT INTO lesson_notes (
            booking_id, tutor_user_id, topics_covered, lesson_summary, 
            homework_assigned, student_progress_rating, student_progress, 
            private_tutor_notes, parent_feedback_notes, next_lesson_focus
        ) VALUES (
            :bid, :tuid, :topics, :summary, 
            :hw, :rating, :progress, 
            :private_notes, :feedback, :next_focus
        )
        ON DUPLICATE KEY UPDATE 
            topics_covered = VALUES(topics_covered),
            lesson_summary = VALUES(lesson_summary),
            homework_assigned = VALUES(homework_assigned),
            student_progress_rating = VALUES(student_progress_rating),
            student_progress = VALUES(student_progress),
            private_tutor_notes = VALUES(private_tutor_notes),
            parent_feedback_notes = VALUES(parent_feedback_notes),
            next_lesson_focus = VALUES(next_lesson_focus)
    ');
    $notesStmt->execute([
        ':bid' => $b4Id,
        ':tuid' => $tutorUserId,
        ':topics' => "1. Quadratic equations & factorisation\n2. Completing the square\n3. Applying the quadratic formula to word problems",
        ':summary' => 'Reviewed algebraic manipulation and quadratic equations. Sophie worked through several multi-step examples with growing confidence.',
        ':hw' => 'Pages 42-44 exercises 3 to 8 (Edexcel GCSE Higher Maths workbook). Focus on showing full intermediate working.',
        ':rating' => 5,
        ':progress' => 'Student demonstrated improved confidence solving multi-step algebraic problems and required minimal assistance on the final 3 exam questions.',
        ':private_notes' => 'Sophie is very capable but sometimes doubts her answers on negative signs. Will reinforce sign conventions next session.',
        ':feedback' => 'Excellent focus today! Sophie grasped completing the square very quickly and completed all practice problems accurately.',
        ':next_focus' => 'Continue with simultaneous equations, graphical solutions, and exam-style questions.'
    ]);

    // ----------------------------------------------------
    // 9. Demo Notification Logs
    // ----------------------------------------------------
    echo "8. Seeding Representative Notification Logs...\n";
    // Delete existing demo notification logs first to maintain idempotency
    $delNotif = $db->prepare('DELETE FROM notification_logs WHERE booking_id IN (:b1, :b2, :b3, :b4)');
    $delNotif->execute([':b1' => $b1Id, ':b2' => $b2Id, ':b3' => $b3Id, ':b4' => $b4Id]);

    $notifStmt = $db->prepare('
        INSERT INTO notification_logs (booking_id, recipient_email, recipient_name, event_type, subject, status)
        VALUES 
            (:b1, "demo.tutor@appitutors.co.uk", "James Carter", "BOOKING_REQUEST_CREATED", "[DEMO] New Booking Request from Emily Wilson", "SENT"),
            (:b2, "demo.parent@appitutors.co.uk", "Emily Wilson", "BOOKING_ACCEPTED", "[DEMO] Booking Confirmed with James Carter", "SENT"),
            (:b3, "demo.parent@appitutors.co.uk", "Emily Wilson", "BOOKING_RESCHEDULE_PROPOSED", "[DEMO] Reschedule Proposed for Your Lesson", "SENT"),
            (:b4, "demo.parent@appitutors.co.uk", "Emily Wilson", "BOOKING_COMPLETED", "[DEMO] Lesson Completed - Lesson Notes Available", "SENT")
    ');
    $notifStmt->execute([
        ':b1' => $b1Id,
        ':b2' => $b2Id,
        ':b3' => $b3Id,
        ':b4' => $b4Id
    ]);

    // ----------------------------------------------------
    // 10. Demo Audit Logs
    // ----------------------------------------------------
    echo "9. Seeding Representative Audit Logs...\n";
    $delAudit = $db->prepare('DELETE FROM audit_logs WHERE user_agent = "AppiTutors Demo Seed"');
    $delAudit->execute();

    $auditStmt = $db->prepare('
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (:uid, :action, :etype, :eid, :details, "127.0.0.1", "AppiTutors Demo Seed")
    ');

    $auditEvents = [
        ['uid' => $managerId, 'action' => 'TUTOR_APPROVED', 'etype' => 'tutor_profiles', 'eid' => $tutorProfileId, 'details' => json_encode(['tutor_email' => 'demo.tutor@appitutors.co.uk', 'notes' => 'Enhanced DBS verified'])],
        ['uid' => $parentUserId, 'action' => 'BOOKING_CREATED', 'etype' => 'bookings', 'eid' => $b1Id, 'details' => json_encode(['booking_reference' => 'DEMO-BK-PEND-01', 'subject' => 'GCSE Mathematics'])],
        ['uid' => $tutorUserId, 'action' => 'BOOKING_ACCEPTED', 'etype' => 'bookings', 'eid' => $b2Id, 'details' => json_encode(['booking_reference' => 'DEMO-BK-ACPT-02', 'status' => 'ACCEPTED'])],
        ['uid' => $tutorUserId, 'action' => 'BOOKING_COMPLETED', 'etype' => 'bookings', 'eid' => $b4Id, 'details' => json_encode(['booking_reference' => 'DEMO-BK-CMPL-04', 'attendance' => 'ATTENDED'])],
    ];

    foreach ($auditEvents as $ae) {
        $auditStmt->execute([
            ':uid' => $ae['uid'],
            ':action' => $ae['action'],
            ':etype' => $ae['etype'],
            ':eid' => $ae['eid'],
            ':details' => $ae['details']
        ]);
    }

    $db->commit();

    echo "\n==================================================\n";
    echo "   DEMO SEED COMPLETED SUCCESSFULLY!\n";
    echo "==================================================\n";
    echo "Summary of Seeded Demo Accounts:\n";
    echo "1. Manager: Sarah Mitchell (demo.manager@appitutors.co.uk)\n";
    echo "2. Tutor:   James Carter (demo.tutor@appitutors.co.uk) - Verified UK Specialist\n";
    echo "3. Parent:  Emily Wilson (demo.parent@appitutors.co.uk)\n";
    echo "   - Child 1: Oliver Wilson (Year 10 - St Albans High School)\n";
    echo "   - Child 2: Sophie Wilson (Year 8 - St Albans Academy)\n";
    echo "4. Booking Scenarios:\n";
    echo "   - [PENDING]             DEMO-BK-PEND-01 (Oliver - GCSE Maths)\n";
    echo "   - [ACCEPTED]            DEMO-BK-ACPT-02 (Sophie - GCSE Physics)\n";
    echo "   - [RESCHEDULE_PROPOSED] DEMO-BK-RSCH-03 (Oliver - A-Level Maths)\n";
    echo "   - [COMPLETED]           DEMO-BK-CMPL-04 (Sophie - GCSE Maths + Lesson Notes)\n";
    echo "==================================================\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n[ERROR] Demo seeding failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
