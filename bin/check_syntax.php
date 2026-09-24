<?php
$phpBin = 'C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe';
$files = [];

function scanPhpFiles($dir, &$files) {
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..' || $item === 'vendor' || $item === '.git') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            scanPhpFiles($path, $files);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
}

scanPhpFiles(dirname(__DIR__), $files);

$hasError = false;
foreach ($files as $f) {
    $out = [];
    $ret = 0;
    exec("\"$phpBin\" -l \"$f\"", $out, $ret);
    if ($ret !== 0) {
        echo "SYNTAX ERROR in $f: " . implode("\n", $out) . "\n";
        $hasError = true;
    } else {
        echo "OK: " . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $f) . "\n";
    }
}

if (!$hasError) {
    echo "\nAll PHP files passed syntax validation successfully!\n";
}
