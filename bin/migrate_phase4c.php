<?php declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

$db = Connection::getInstance();

echo "Running Phase 4C DB Migrations...\n";

try {
    $db->exec("
        ALTER TABLE bookings 
        ADD COLUMN proposed_availability_slot_id INT UNSIGNED NULL AFTER proposed_reschedule_end,
        ADD CONSTRAINT fk_bookings_proposed_slot FOREIGN KEY (proposed_availability_slot_id) REFERENCES availability_slots(id) ON DELETE SET NULL
    ");
    echo "[OK] Added proposed_availability_slot_id column with foreign key to bookings\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        echo "[INFO] proposed_availability_slot_id column already exists\n";
    } else {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}
