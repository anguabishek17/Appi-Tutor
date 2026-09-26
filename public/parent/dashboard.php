<?php declare(strict_types=1);

/**
 * Parent & Student Portal: Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\UIHelper;

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

// Get next 3 upcoming lessons
$upcomingStmt = $db->prepare('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.scheduled_start,
        b.scheduled_end,
        b.status,
        s.name AS subject_name,
        u_tutor.first_name AS tutor_first_name,
        u_tutor.last_name AS tutor_last_name,
        sc.first_name AS child_first_name,
        sc.last_name AS child_last_name
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    JOIN subjects s ON b.subject_id = s.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    WHERE b.parent_user_id = :parent_id 
      AND b.status IN (\'ACCEPTED\', \'RESCHEDULE_PROPOSED\')
    ORDER BY b.scheduled_start ASC
    LIMIT 3
');
$upcomingStmt->execute([':parent_id' => $parentUserId]);
$upcomingLessons = $upcomingStmt->fetchAll();

// Get recent bookings for activity list
$recentStmt = $db->prepare('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.scheduled_start,
        b.status,
        b.created_at,
        s.name AS subject_name,
        u_tutor.first_name AS tutor_first_name,
        u_tutor.last_name AS tutor_last_name
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    JOIN subjects s ON b.subject_id = s.id
    WHERE b.parent_user_id = :parent_id
    ORDER BY b.created_at DESC
    LIMIT 4
');
$recentStmt->execute([':parent_id' => $parentUserId]);
$recentBookings = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal Dashboard | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800 bg-slate-50 antialiased">

    <?php UIHelper::renderHeader($user, 'dashboard'); ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Page Welcome Header -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Hello, <?= htmlspecialchars($user['first_name']) ?> 👋
                </h1>
                <p class="text-slate-600 mt-1 text-sm">
                    Manage your family's tuition, monitor lesson attendance, and book verified UK tutors.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/parent/children.php" class="px-4 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition shadow-xs">
                    + Add Child
                </a>
                <a href="/tutors.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                    Find a Tutor
                </a>
            </div>
        </div>

        <!-- 4 Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Children</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900"><?= $childrenCount ?> Students</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Registered profiles</span>
                    <a href="/parent/children.php" class="text-indigo-600 font-bold hover:underline">Manage &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Lessons</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $upcomingLessonsCount ?> Confirmed</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Next 7 days</span>
                    <a href="/parent/bookings.php" class="text-indigo-600 font-bold hover:underline">View all &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Requests</div>
                    <div class="mt-2 text-2xl font-extrabold <?= $pendingBookingsCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                        <?= $pendingBookingsCount ?> Pending
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Awaiting tutor review</span>
                    <a href="/parent/bookings.php" class="text-indigo-600 font-bold hover:underline">Track &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Completed Lessons</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600"><?= $completedLessonsCount ?> Completed</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Notes & summaries</span>
                    <a href="/parent/bookings.php" class="text-indigo-600 font-bold hover:underline">History &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Main Section: Upcoming Lessons & Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Left 2 Cols: Upcoming Lessons -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Upcoming Confirmed Lessons</h2>
                    <a href="/parent/bookings.php" class="text-xs font-semibold text-indigo-600 hover:underline">View All Bookings &rarr;</a>
                </div>

                <?php if (empty($upcomingLessons)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto text-xl font-bold">
                            📅
                        </div>
                        <h3 class="text-base font-bold text-slate-900">No upcoming lessons</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            You don't have any confirmed lessons scheduled yet. Browse vetted UK tutors and book a suitable slot.
                        </p>
                        <div class="pt-2">
                            <a href="/tutors.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                                Find a Tutor
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($upcomingLessons as $lesson): ?>
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-slate-300 transition">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-extrabold text-sm shrink-0 mt-0.5">
                                        <?= strtoupper(substr($lesson['tutor_first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($lesson['subject_name']) ?></span>
                                            <?= UIHelper::renderBookingStatusBadge($lesson['status']) ?>
                                        </div>
                                        <div class="text-xs text-slate-600 mt-1">
                                            Tutor: <strong><?= htmlspecialchars($lesson['tutor_first_name'] . ' ' . $lesson['tutor_last_name']) ?></strong>
                                            <?php if (!empty($lesson['child_first_name'])): ?>
                                                • Student: <strong><?= htmlspecialchars($lesson['child_first_name'] . ' ' . $lesson['child_last_name']) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-indigo-700 font-semibold mt-1">
                                            <?= UIHelper::formatDateTime($lesson['scheduled_start']) ?> (<?= UIHelper::formatTimeRange($lesson['scheduled_start'], $lesson['scheduled_end']) ?>)
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="/parent/bookings.php" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition inline-block text-center w-full sm:w-auto">
                                        Booking Details &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right 1 Col: Quick Actions & Help -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="/parent/children.php" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">👶</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">Add or Edit Child</div>
                                <div class="text-[11px] text-slate-500">Manage academic year & goals</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-indigo-600 text-xs font-bold">&rarr;</span>
                        </a>

                        <a href="/tutors.php" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">🔍</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">Find a UK Tutor</div>
                                <div class="text-[11px] text-slate-500">Filter by GCSE, A-Level, 11+</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-emerald-600 text-xs font-bold">&rarr;</span>
                        </a>

                        <a href="/parent/bookings.php" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">📖</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">Lesson Summaries</div>
                                <div class="text-[11px] text-slate-500">Review notes & homework feedback</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-blue-600 text-xs font-bold">&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity Feed -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 mb-3">Recent Bookings</h2>
                    <?php if (empty($recentBookings)): ?>
                        <p class="text-xs text-slate-400">No booking activity recorded yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 divide-y divide-slate-100">
                            <?php foreach ($recentBookings as $idx => $rb): ?>
                                <div class="<?= $idx > 0 ? 'pt-3' : '' ?> text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($rb['subject_name']) ?></span>
                                        <?= UIHelper::renderBookingStatusBadge($rb['status']) ?>
                                    </div>
                                    <div class="text-slate-500 text-[11px] mt-1">
                                        Ref: <span class="font-mono"><?= htmlspecialchars($rb['booking_reference']) ?></span> • <?= UIHelper::formatDate($rb['created_at']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php UIHelper::renderFooter(); ?>
</body>
</html>

