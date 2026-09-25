<?php
declare(strict_types=1);

/**
 * AppiTutors Configuration Loader
 */

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'AppiTutors',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    ],

    'database' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'dbname' => $_ENV['DB_NAME'] ?? 'appitutors_db',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'options' => [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ],
    ],

    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
        'secure' => filter_var($_ENV['SESSION_SECURE_COOKIE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'security' => [
        'csrf_secret' => $_ENV['CSRF_TOKEN_SECRET'] ?? 'default_secret_key_change_me',
    ],

    'firebase' => [
        'project_id' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
        'credentials_path' => $_ENV['FIREBASE_CREDENTIALS_PATH'] ?? dirname(__DIR__) . '/config/firebase_credentials.json',
        'client' => [
            'apiKey' => $_ENV['FIREBASE_API_KEY'] ?? '',
            'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '',
            'projectId' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
            'appId' => $_ENV['FIREBASE_APP_ID'] ?? '',
        ],
    ],

    'mail' => [
        'mailer' => $_ENV['MAIL_MAILER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 2525),
        'username' => $_ENV['MAIL_USERNAME'] ?? null,
        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? null,
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@appitutors.co.uk',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'AppiTutors',
    ],

    'paths' => [
        'root' => dirname(__DIR__),
        'storage' => dirname(__DIR__) . '/storage',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'dbs' => dirname(__DIR__) . '/storage/dbs_credentials',
    ],
];
