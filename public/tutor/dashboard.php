<?php declare(strict_types=1);

/**
 * Tutor Portal: Active Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;

// Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor();
$db = Connection::getInstance();
$userId = (int)$user['id'];

// Fetch tutor details and metrics
$tpStmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.teaching_mode,
        (SELECT COUNT(*) FROM tutor_subjects ts WHERE ts.tutor_profile_id = tp.id) AS subjects_count,
        (SELECT COUNT(*) FROM availability_slots av WHERE av.tutor_profile_id = tp.id AND av.is_blocked = 0 AND av.start_time > NOW()) AS active_slots_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.tutor_profile_id = tp.id AND b.status = "PENDING") AS pending_bookings_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.tutor_profile_id = tp.id AND b.status = "ACCEPTED" AND b.scheduled_start >= NOW()) AS upcoming_lessons_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.tutor_profile_id = tp.id AND b.status = "COMPLETED") AS completed_lessons_count
    FROM tutor_profiles tp
    WHERE tp.user_id = :uid
    LIMIT 1
');
$tpStmt->execute([':uid' => $userId]);
$profile = $tpStmt->fetch() ?: [];

$tutorProfileId = (int)($profile['tutor_profile_id'] ?? 0);
$subjectsCount = (int)($profile['subjects_count'] ?? 0);
$activeSlotsCount = (int)($profile['active_slots_count'] ?? 0);
$pendingBookingsCount = (int)($profile['pending_bookings_count'] ?? 0);
$upcomingLessonsCount = (int)($profile['upcoming_lessons_count'] ?? 0);
$completedLessonsCount = (int)($profile['completed_lessons_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Dashboard | AppiTutors</title>
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
                <a href="/tutor/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">
                        A
                    </div>
                    <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span> <span class="text-xs ml-2 px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-medium">Approved Tutor</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-2 text-sm font-semibold">
                    <a href="/tutor/dashboard.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">Overview</a>
                    <a href="/tutor/profile.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Profile</a>
                    <a href="/tutor/subjects.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Subjects</a>
                    <a href="/tutor/availability.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Availability</a>
                    <a href="/tutor/bookings.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg flex items-center gap-1.5">
                        <span>Bookings</span>
                        <?php if ($pendingBookingsCount > 0): ?>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-400 text-slate-900"><?= $pendingBookingsCount ?></span>
                        <?php endif; ?>
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <?php if ($tutorProfileId > 0): ?>
                    <a href="/tutor.php?id=<?= $tutorProfileId ?>" target="_blank" class="text-xs text-indigo-600 hover:underline">View Public Profile ↗</a>
                <?php endif; ?>
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="mb-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋</h1>
            <p class="text-slate-600 mt-1">Manage your tutor profile, subjects taught, and scheduled availability slots.</p>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Requests</div>
                <div class="mt-2 text-2xl font-extrabold <?= $pendingBookingsCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                    <?= $pendingBookingsCount ?> Pending
                </div>
                <p class="text-xs text-slate-500 mt-2"><a href="/tutor/bookings.php" class="text-indigo-600 hover:underline">Review requests &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Lessons</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $upcomingLessonsCount ?> Confirmed</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/tutor/bookings.php" class="text-indigo-600 hover:underline">View calendar &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Completed Lessons</div>
                <div class="mt-2 text-2xl font-extrabold text-blue-600"><?= $completedLessonsCount ?> Sessions</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/tutor/bookings.php" class="text-indigo-600 hover:underline">View history & notes &rarr;</a></p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Open Slots</div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900"><?= $activeSlotsCount ?> Slots</div>
                <p class="text-xs text-slate-500 mt-2"><a href="/tutor/availability.php" class="text-indigo-600 hover:underline">Manage schedule &rarr;</a></p>
            </div>
        </div>

        <!-- Quick Action Hub -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Tutor Operations Hub</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="/tutor/bookings.php" class="p-5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/20 transition flex flex-col justify-between">
                    <div>
                        <div class="text-2xl mb-2">📋</div>
                        <div class="font-bold text-slate-900">Bookings & Requests</div>
                        <div class="text-xs text-slate-500 mt-1">Accept or decline pending parent session requests.</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-xs mt-4">Manage Bookings &rarr;</span>
                </a>

                <a href="/tutor/availability.php" class="p-5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/20 transition flex flex-col justify-between">
                    <div>
                        <div class="text-2xl mb-2">📅</div>
                        <div class="font-bold text-slate-900">Calendar Slots</div>
                        <div class="text-xs text-slate-500 mt-1">Add dated availability slots for 1-to-1 or group lessons.</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-xs mt-4">Manage Slots &rarr;</span>
                </a>

                <a href="/tutor/subjects.php" class="p-5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/20 transition flex flex-col justify-between">
                    <div>
                        <div class="text-2xl mb-2">📚</div>
                        <div class="font-bold text-slate-900">Subjects & Curricula</div>
                        <div class="text-xs text-slate-500 mt-1">Choose the specific subjects (KS1-3, GCSE, A-Level, IB) you tutor.</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-xs mt-4">Select Subjects &rarr;</span>
                </a>

                <a href="/tutor/profile.php" class="p-5 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/20 transition flex flex-col justify-between">
                    <div>
                        <div class="text-2xl mb-2">👤</div>
                        <div class="font-bold text-slate-900">Profile & Rates</div>
                        <div class="text-xs text-slate-500 mt-1">Set headline, hourly rate (£<?= number_format((float)($profile['hourly_rate'] ?? 35), 2) ?>), bio, and qualifications.</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-xs mt-4">Edit Profile &rarr;</span>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>
</body>
</html>
