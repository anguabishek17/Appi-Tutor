<?php declare(strict_types=1);

/**
 * Migration for Phase 4D: Notification Logs table
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

$db = Connection::getInstance();

echo "Running Phase 4D Migration (notification_logs)...\n";

$sql = "
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NULL,
    event_type VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('SENT', 'FAILED', 'DEV_LOGGED') NOT NULL DEFAULT 'SENT',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$db->exec($sql);

$indexSql = "
CREATE INDEX IF NOT EXISTS idx_notification_booking ON notification_logs(booking_id);
CREATE INDEX IF NOT EXISTS idx_notification_event ON notification_logs(event_type);
CREATE INDEX IF NOT EXISTS idx_notification_status ON notification_logs(status);
";

try {
    $db->exec($indexSql);
} catch (\Throwable $e) {
    // Indexes may already exist
}

echo "Phase 4D Migration Completed Successfully.\n";
