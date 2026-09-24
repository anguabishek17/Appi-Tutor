<?php
declare(strict_types=1);

$routes = [
    '/' => 200,
    '/login.php' => 200,
    '/register.php' => 200,
    '/verify-email.php' => 200,
    '/unauthorized.php' => 200,
    '/parent/dashboard.php' => 302, // Should redirect to login when anonymous
    '/tutor/dashboard.php' => 302,  // Should redirect to login when anonymous
    '/manager/dashboard.php' => 302,// Should redirect to login when anonymous
    '/api/auth/me.php' => 401,      // 401 Unauthorized for API when anonymous
    '/api/manager/tutors/pending.php' => 401 // 401 Unauthorized for manager API
];

echo "=== ROUTE ACCESS & HTTP CODE VERIFICATION ===\n\n";

foreach ($routes as $route => $expectedStatus) {
    $url = 'http://127.0.0.1:8080' . $route;
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'follow_location' => 0
        ]
    ]);
    $content = @file_get_contents($url, false, $context);
    
    $statusLine = $http_response_header[0] ?? '';
    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
    $statusCode = isset($match[1]) ? (int)$match[1] : 0;

    $passed = ($statusCode === $expectedStatus);
    $badge = $passed ? '[PASS]' : '[FAIL]';
    echo sprintf("%s %-32s Expected: %d | Actual: %d\n", $badge, $route, $expectedStatus, $statusCode);
}
