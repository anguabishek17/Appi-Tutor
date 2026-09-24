<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton Database Connection Manager
 */
class Connection
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

    /**
     * Initialize connection with custom configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get the active PDO connection
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = self::$config ?? (require dirname(__DIR__, 2) . '/config/app.php')['database'];

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['user'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                // In production, do not expose raw credentials or internal database error messages
                error_log('[AppiTutors Database Error] Connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection failed. Please ensure the database is running and configuration is correct.');
            }
        }

        return self::$instance;
    }

    /**
     * Reset connection (useful for testing or long-running scripts)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
