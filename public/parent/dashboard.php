<?php declare(strict_types=1);

/**
 * Parent & Student Portal: Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;

// Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT']);
$db = Connection::getInstance();
$parentUserId = (int)$user['id'];

// Get metric counts
$childrenCount = (int)$db->query("SELECT COUNT(*) FROM students_children WHERE parent_user_id = $parentUserId")->fetchColumn();
$bookingsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE parent_user_id = $parentUserId")->fetchColumn();
$pendingBookingsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE parent_user_id = $parentUserId AND status = 'PENDING'")->fetchColumn();
$upcomingLessonsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE parent_user_id = $parentUserId AND status IN ('ACCEPTED', 'RESCHEDULE_PROPOSED')")->fetchColumn();
$completedLessonsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE parent_user_id = $parentUserId AND status = 'COMPLETED'")->fetchColumn();
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

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/parent/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">
                        A
                    </div>
                    <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span> <span class="text-xs ml-2 px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">Parent Portal</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-2 text-sm font-semibold">
                    <a href="/parent/dashboard.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">Overview</a>
                    <a href="/parent/children.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Children</a>
                    <a href="/parent/bookings.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">My Bookings</a>
                    <a href="/tutors.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Find a Tutor</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="mb-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Hello, <?= htmlspecialchars($user['first_name']) ?> 👋</h1>
            <p class="text-slate-600 mt-1">Manage your family's tutoring sessions, track attendance, and view teacher lesson summaries.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Requests</div>
                <div class="mt-2 text-2xl font-extrabold <?= $pendingBookingsCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                    <?= $pendingBookingsCount ?> Pending
                </div>
                <p class="text-xs text-slate-500 mt-2"><a href="/parent/bookings.php" class="text-indigo-600 hover:underline">View requests &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Lessons</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $upcomingLessonsCount ?> Confirmed</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/parent/bookings.php" class="text-indigo-600 hover:underline">View schedule &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Completed Lessons</div>
                <div class="mt-2 text-2xl font-extrabold text-blue-600"><?= $completedLessonsCount ?> Sessions</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/parent/bookings.php" class="text-indigo-600 hover:underline">View lesson summaries &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Children Registered</div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900"><?= $childrenCount ?> Students</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/parent/children.php" class="text-indigo-600 hover:underline">Manage profiles &rarr;</a></p>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="text-3xl mb-2">👶</div>
                    <h3 class="font-extrabold text-slate-900 text-lg">Manage Children Profiles</h3>
                    <p class="text-slate-600 text-xs mt-1 leading-relaxed">Add student learning goals, year groups (Primary, 11+, GCSE, A-Level), and special study requirements.</p>
                </div>
                <a href="/parent/children.php" class="mt-4 inline-block px-4 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold text-xs rounded-xl transition w-fit">
                    Open Children Hub &rarr;
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="text-3xl mb-2">🔍</div>
                    <h3 class="font-extrabold text-slate-900 text-lg">Find & Book Expert Tutors</h3>
                    <p class="text-slate-600 text-xs mt-1 leading-relaxed">Search vetted, DBS-checked educators across England, Scotland, Wales, and Northern Ireland.</p>
                </div>
                <a href="/tutors.php" class="mt-4 inline-block px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700 font-bold text-xs rounded-xl shadow-md transition w-fit">
                    Search Marketplace &rarr;
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>
</body>
</html>
