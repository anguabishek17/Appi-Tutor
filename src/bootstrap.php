<?php
declare(strict_types=1);

/**
 * AppiTutors Core Application Bootstrap
 */

// 1. Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 2. Define Root Path constants
define('APP_ROOT', dirname(__DIR__));
define('CONFIG_PATH', APP_ROOT . '/config');
define('STORAGE_PATH', APP_ROOT . '/storage');

// 3. Load Composer Autoloader if available; fallback to lightweight PSR-4 autoloader
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
} else {
    // Built-in fallback PSR-4 autoloader for running before composer install
    spl_autoload_register(function (string $class) {
        $prefix = 'App\\';
        $baseDir = APP_ROOT . '/src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// 4. Load Environment Variables (.env)
if (file_exists(APP_ROOT . '/.env')) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$name, $val] = explode('=', $line, 2);
            $name = trim($name);
            $val = trim($val);
            // Strip surrounding quotes
            $val = trim($val, "\"'");
            $_ENV[$name] = $val;
            putenv("$name=$val");
        }
    }
}

// 5. Load App Configuration
$config = require CONFIG_PATH . '/app.php';

// 6. Configure & Start Secure PHP Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', $config['session']['samesite']);
    
    if ($config['session']['secure']) {
        ini_set('session.cookie_secure', '1');
    }
    
    ini_set('session.gc_maxlifetime', (string)$config['session']['lifetime']);
    session_start();
}

// 7. Initialize Database Singleton Config
\App\Database\Connection::init($config['database']);

return $config;
