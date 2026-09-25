<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

$pdo = Connection::getInstance();

echo "Running Phase 4E Migration...\n";

try {
    // 1. Add attendance_status to bookings table if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'attendance_status'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE bookings
            ADD COLUMN attendance_status ENUM('NOT_RECORDED', 'ATTENDED', 'PARTIAL', 'ABSENT') NOT NULL DEFAULT 'NOT_RECORDED' AFTER status
        ");
        echo "[+] Added attendance_status column to bookings table.\n";
    } else {
        echo "[.] attendance_status column already exists on bookings table.\n";
    }

    // 2. Add lesson_summary and next_lesson_focus and student_progress to lesson_notes if not exist
    $stmt = $pdo->query("SHOW COLUMNS FROM lesson_notes LIKE 'lesson_summary'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE lesson_notes
            ADD COLUMN lesson_summary TEXT NULL AFTER topics_covered
        ");
        echo "[+] Added lesson_summary column to lesson_notes table.\n";
    } else {
        echo "[.] lesson_summary column already exists on lesson_notes table.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM lesson_notes LIKE 'student_progress'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE lesson_notes
            ADD COLUMN student_progress TEXT NULL AFTER student_progress_rating
        ");
        echo "[+] Added student_progress column to lesson_notes table.\n";
    } else {
        echo "[.] student_progress column already exists on lesson_notes table.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM lesson_notes LIKE 'next_lesson_focus'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE lesson_notes
            ADD COLUMN next_lesson_focus TEXT NULL AFTER parent_feedback_notes
        ");
        echo "[+] Added next_lesson_focus column to lesson_notes table.\n";
    } else {
        echo "[.] next_lesson_focus column already exists on lesson_notes table.\n";
    }

    // Modify topics_covered to be NULLable just in case draft notes are saved incrementally
    $pdo->exec("
        ALTER TABLE lesson_notes
        MODIFY COLUMN topics_covered TEXT NULL
    ");

    echo "Phase 4E Migration completed successfully.\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
