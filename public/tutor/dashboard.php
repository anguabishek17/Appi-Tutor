<?php declare(strict_types=1);

/**
 * Tutor Portal: Active Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\UIHelper;

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

// Fetch pending requests needing review
$pendingRequests = [];
if ($tutorProfileId > 0) {
    $prStmt = $db->prepare('
        SELECT 
            b.id AS booking_id,
            b.booking_reference,
            b.scheduled_start,
            b.scheduled_end,
            b.created_at,
            s.name AS subject_name,
            u_p.first_name AS parent_first_name,
            u_p.last_name AS parent_last_name,
            sc.first_name AS child_first_name,
            sc.last_name AS child_last_name
        FROM bookings b
        JOIN users u_p ON b.parent_user_id = u_p.id
        JOIN subjects s ON b.subject_id = s.id
        LEFT JOIN students_children sc ON b.student_child_id = sc.id
        WHERE b.tutor_profile_id = :tpid AND b.status = "PENDING"
        ORDER BY b.created_at ASC
        LIMIT 3
    ');
    $prStmt->execute([':tpid' => $tutorProfileId]);
    $pendingRequests = $prStmt->fetchAll();
}

// Fetch upcoming confirmed lessons
$upcomingLessons = [];
if ($tutorProfileId > 0) {
    $upStmt = $db->prepare('
        SELECT 
            b.id AS booking_id,
            b.booking_reference,
            b.scheduled_start,
            b.scheduled_end,
            b.status,
            s.name AS subject_name,
            u_p.first_name AS parent_first_name,
            u_p.last_name AS parent_last_name,
            sc.first_name AS child_first_name,
            sc.last_name AS child_last_name
        FROM bookings b
        JOIN users u_p ON b.parent_user_id = u_p.id
        JOIN subjects s ON b.subject_id = s.id
        LEFT JOIN students_children sc ON b.student_child_id = sc.id
        WHERE b.tutor_profile_id = :tpid AND b.status = "ACCEPTED" AND b.scheduled_start >= NOW()
        ORDER BY b.scheduled_start ASC
        LIMIT 3
    ');
    $upStmt->execute([':tpid' => $tutorProfileId]);
    $upcomingLessons = $upStmt->fetchAll();
}
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
<body class="min-h-full flex flex-col justify-between text-slate-800 bg-slate-50 antialiased">

    <?php UIHelper::renderHeader($user, 'dashboard'); ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Page Title & Header -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋
                </h1>
                <p class="text-slate-600 mt-1 text-sm">
                    Manage your teaching profile, availability calendar, and lesson notes.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($tutorProfileId > 0): ?>
                    <a href="/tutor.php?id=<?= $tutorProfileId ?>" target="_blank" class="px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition shadow-xs">
                        Public Profile ↗
                    </a>
                <?php endif; ?>
                <a href="/tutor/availability.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    + Add Availability
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Requests</div>
                    <div class="mt-2 text-2xl font-extrabold <?= $pendingBookingsCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                        <?= $pendingBookingsCount ?> Pending
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Awaiting your response</span>
                    <a href="/tutor/bookings.php" class="text-indigo-600 font-bold hover:underline">Review &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Lessons</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $upcomingLessonsCount ?> Confirmed</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Scheduled sessions</span>
                    <a href="/tutor/bookings.php" class="text-indigo-600 font-bold hover:underline">Schedule &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Completed Lessons</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600"><?= $completedLessonsCount ?> Sessions</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">With lesson notes</span>
                    <a href="/tutor/bookings.php" class="text-indigo-600 font-bold hover:underline">History &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Subjects</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900"><?= $subjectsCount ?> Subjects</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500"><?= $activeSlotsCount ?> open calendar slots</span>
                    <a href="/tutor/subjects.php" class="text-indigo-600 font-bold hover:underline">Edit &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Pending Requests Alert Banner (if any) -->
        <?php if (!empty($pendingRequests)): ?>
            <div class="bg-amber-50/80 border border-amber-200/90 rounded-2xl p-6 mb-8 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                        <h2 class="text-base font-bold text-amber-900">Action Required: Pending Booking Requests</h2>
                    </div>
                    <a href="/tutor/bookings.php" class="text-xs font-bold text-amber-900 hover:underline">View All Requests &rarr;</a>
                </div>
                <div class="space-y-3">
                    <?php foreach ($pendingRequests as $pr): ?>
                        <div class="bg-white rounded-xl p-4 border border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-900 text-sm">
                                    <?= htmlspecialchars($pr['subject_name']) ?> with <?= htmlspecialchars($pr['parent_first_name'] . ' ' . $pr['parent_last_name']) ?>
                                    <?php if (!empty($pr['child_first_name'])): ?>
                                        <span class="font-normal text-slate-500">(Student: <?= htmlspecialchars($pr['child_first_name'] . ' ' . $pr['child_last_name']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-slate-600 mt-0.5">
                                    Requested for: <strong><?= UIHelper::formatDateTime($pr['scheduled_start']) ?></strong> (<?= UIHelper::formatTimeRange($pr['scheduled_start'], $pr['scheduled_end']) ?>)
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="/tutor/bookings.php" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-xs transition">
                                    Review Request &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions & Operations Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Left 2 Cols: Upcoming Confirmed Lessons -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Upcoming Confirmed Lessons</h2>
                    <a href="/tutor/bookings.php" class="text-xs font-semibold text-indigo-600 hover:underline">Full Calendar &rarr;</a>
                </div>

                <?php if (empty($upcomingLessons)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto text-xl font-bold">
                            📖
                        </div>
                        <h3 class="text-base font-bold text-slate-900">No upcoming confirmed lessons</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            Add open calendar slots to make it easy for UK parents to discover your profile and book tuition.
                        </p>
                        <div class="pt-2">
                            <a href="/tutor/availability.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                                Manage Availability Slots
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($upcomingLessons as $ul): ?>
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($ul['subject_name']) ?></span>
                                        <?= UIHelper::renderBookingStatusBadge($ul['status']) ?>
                                    </div>
                                    <div class="text-xs text-slate-600 mt-1">
                                        Parent: <strong><?= htmlspecialchars($ul['parent_first_name'] . ' ' . $ul['parent_last_name']) ?></strong>
                                        <?php if (!empty($ul['child_first_name'])): ?>
                                            • Student: <strong><?= htmlspecialchars($ul['child_first_name'] . ' ' . $ul['child_last_name']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-indigo-700 font-semibold mt-1">
                                        <?= UIHelper::formatDateTime($ul['scheduled_start']) ?> (<?= UIHelper::formatTimeRange($ul['scheduled_start'], $ul['scheduled_end']) ?>)
                                    </div>
                                </div>
                                <div>
                                    <a href="/tutor/bookings.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition inline-block text-center w-full sm:w-auto">
                                        Open Lesson &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right 1 Col: Quick Actions Hub -->
            <div class="space-y-4">
                <h2 class="text-lg font-bold text-slate-900">Quick Actions</h2>
                <div class="space-y-3">
                    <a href="/tutor/profile.php" class="bg-white p-4 rounded-2xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/20 transition flex items-center gap-3.5 group shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg group-hover:scale-105 transition">👤</div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-slate-900">Update Profile</div>
                            <div class="text-[11px] text-slate-500">Bio, rate (£<?= number_format((float)($profile['hourly_rate'] ?? 35), 2) ?>/hr), qualifications</div>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-xs font-bold">&rarr;</span>
                    </a>

                    <a href="/tutor/subjects.php" class="bg-white p-4 rounded-2xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/20 transition flex items-center gap-3.5 group shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg group-hover:scale-105 transition">📚</div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-slate-900">Manage Subjects</div>
                            <div class="text-[11px] text-slate-500"><?= $subjectsCount ?> subjects active in catalog</div>
                        </div>
                        <span class="text-slate-400 group-hover:text-emerald-600 text-xs font-bold">&rarr;</span>
                    </a>

                    <a href="/tutor/availability.php" class="bg-white p-4 rounded-2xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/20 transition flex items-center gap-3.5 group shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg group-hover:scale-105 transition">📅</div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-slate-900">Manage Availability</div>
                            <div class="text-[11px] text-slate-500"><?= $activeSlotsCount ?> active slots scheduled</div>
                        </div>
                        <span class="text-slate-400 group-hover:text-blue-600 text-xs font-bold">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php UIHelper::renderFooter(); ?>
</body>
</html>

