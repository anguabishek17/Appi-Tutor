<?php
declare(strict_types=1);

/**
 * Parent & Student Portal: Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;

// Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">
                    A
                </div>
                <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span> <span class="text-xs ml-2 px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">Parent & Student Portal</span></span>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-red-600 hover:text-red-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="mb-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Hello, <?= htmlspecialchars($user['first_name']) ?> 👋</h1>
            <p class="text-slate-600 mt-1">Manage your family's tutoring sessions and search verified UK educators.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Account Role</div>
                <div class="mt-2 text-lg font-bold text-indigo-600">Student / Parent</div>
                <p class="text-xs text-slate-500 mt-2">Active access to tutor matching and bookings.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Children Profiles</div>
                <div class="mt-2 text-lg font-bold text-slate-900">Ready to add</div>
                <p class="text-xs text-slate-500 mt-2">Child profile creation unlocks in next module.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Lessons</div>
                <div class="mt-2 text-lg font-bold text-slate-900">0 Scheduled</div>
                <p class="text-xs text-slate-500 mt-2">Bookings workflow unlocks in next phase.</p>
            </div>
        </div>

        <div class="bg-indigo-600 rounded-2xl p-6 sm:p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl shadow-indigo-200">
            <div>
                <h3 class="text-xl font-bold">Find expert tutors for your child</h3>
                <p class="text-indigo-100 text-sm mt-1">Browse verified UK tutors across Primary, GCSE, and A-Level subjects.</p>
            </div>
            <a href="/" class="px-6 py-3 bg-white text-indigo-600 font-bold rounded-xl hover:bg-indigo-50 shadow-md transition whitespace-nowrap text-sm">
                Explore Website
            </a>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>
</body>
</html>
