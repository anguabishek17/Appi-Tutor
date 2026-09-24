<?php
declare(strict_types=1);

/**
 * Public Page: Unauthorized Access Notice
 */

require_once __DIR__ . '/../src/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="w-16 h-16 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h2 class="text-3xl font-extrabold text-slate-900">Access Restricted (403)</h2>
        <p class="mt-2 text-sm text-slate-600">
            You do not have the required permissions to view this section of AppiTutors.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-200/80 text-center space-y-4">
            <p class="text-xs text-slate-500">
                If you believe this is an error or need elevated access, please contact your platform administrator.
            </p>
            <a href="/dashboard.php" class="inline-flex justify-center w-full py-2.5 px-4 border border-transparent rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition">
                Return to My Dashboard
            </a>
            <a href="/logout.php" class="inline-flex justify-center w-full py-2.5 px-4 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition">
                Sign In with a Different Account
            </a>
        </div>
    </div>

</body>
</html>
