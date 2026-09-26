<?php
declare(strict_types=1);

/**
 * Manager Portal: Main Operations Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\UIHelper;

// Require MANAGER role
$user = AuthMiddleware::requireRole(['MANAGER']);

$db = Connection::getInstance();

// 4 Required Metrics:
// 1. Pending Tutor Approvals
$pendingCount = (int)$db->query('SELECT COUNT(*) FROM tutor_profiles WHERE approval_status = "PENDING"')->fetchColumn();
// 2. Approved Tutors
$approvedCount = (int)$db->query('SELECT COUNT(*) FROM tutor_profiles WHERE approval_status = "APPROVED"')->fetchColumn();
// 3. Total Parents
$parentsCount = (int)$db->query('SELECT COUNT(*) FROM users WHERE role = "STUDENT_PARENT"')->fetchColumn();
// 4. Upcoming Bookings
$upcomingBookingsCount = (int)$db->query('SELECT COUNT(*) FROM bookings WHERE status IN ("ACCEPTED", "RESCHEDULE_PROPOSED") AND scheduled_start >= NOW()')->fetchColumn();

// Fetch 3 pending tutors needing approval
$pendingTutorsStmt = $db->query('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.headline,
        tp.experience_years,
        tp.hourly_rate,
        tp.created_at,
        u.first_name,
        u.last_name,
        u.email
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = "PENDING"
    ORDER BY tp.created_at ASC
    LIMIT 3
');
$pendingTutors = $pendingTutorsStmt->fetchAll();

// Fetch recent platform booking activity
$recentBookingsStmt = $db->query('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.scheduled_start,
        b.status,
        b.created_at,
        s.name AS subject_name,
        u_tutor.first_name AS tutor_first_name,
        u_tutor.last_name AS tutor_last_name,
        u_parent.first_name AS parent_first_name,
        u_parent.last_name AS parent_last_name
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    JOIN users u_parent ON b.parent_user_id = u_parent.id
    JOIN subjects s ON b.subject_id = s.id
    ORDER BY b.created_at DESC
    LIMIT 4
');
$recentBookings = $recentBookingsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Operations Dashboard | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800 bg-slate-50 antialiased">

    <?php UIHelper::renderHeader($user, 'dashboard'); ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Page Title Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    Platform Operations Overview
                </h1>
                <p class="text-slate-600 mt-1 text-sm">
                    Tutor verification, booking health, and platform governance.
                </p>
            </div>
            <a href="/manager/tutors.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                <span>Review Tutor Approvals</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="bg-amber-400 text-slate-900 text-[10px] px-2 py-0.5 rounded-full font-extrabold"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- 4 Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Tutor Approvals</div>
                    <div class="mt-2 text-2xl font-extrabold <?= $pendingCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                        <?= $pendingCount ?> Applications
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">DBS & ID checks</span>
                    <a href="/manager/tutors.php" class="text-indigo-600 font-bold hover:underline">Review &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Approved Tutors</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600"><?= $approvedCount ?> Active</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Marketplace visible</span>
                    <a href="/tutors.php" class="text-indigo-600 font-bold hover:underline">Browse &rarr;</a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Parents</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600"><?= $parentsCount ?> Families</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Registered users</span>
                    <span class="text-slate-400 font-medium text-[11px]">UK Network</span>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Upcoming Bookings</div>
                    <div class="mt-2 text-2xl font-extrabold text-indigo-600"><?= $upcomingBookingsCount ?> Sessions</div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Confirmed & active</span>
                    <span class="text-emerald-700 font-medium text-[11px]">On Schedule</span>
                </div>
            </div>
        </div>

        <!-- Section: Pending Applications & Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Left 2 Cols: Pending Tutor Approvals Queue -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Pending Tutor Approvals</h2>
                    <a href="/manager/tutors.php" class="text-xs font-semibold text-indigo-600 hover:underline">Full Review Queue &rarr;</a>
                </div>

                <?php if (empty($pendingTutors)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center space-y-2 shadow-xs">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto text-lg font-bold">
                            ✓
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">All caught up!</h3>
                        <p class="text-xs text-slate-500">No pending tutor applications awaiting verification at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($pendingTutors as $pt): ?>
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?></span>
                                        <?= UIHelper::renderTutorApprovalBadge('PENDING') ?>
                                    </div>
                                    <div class="text-xs text-slate-600 mt-1">
                                        <?= htmlspecialchars($pt['headline'] ?: 'Subject Specialist') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        Applied on <?= UIHelper::formatDate($pt['created_at']) ?> • <?= (int)$pt['experience_years'] ?> yrs experience • Rate: <?= UIHelper::formatCurrency($pt['hourly_rate']) ?>/hr
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <a href="/manager/tutors.php" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition inline-block text-center">
                                        Inspect & Verify &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right 1 Col: Quick Actions Hub & Recent Platform Activity -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 mb-4">Manager Actions</h2>
                    <div class="space-y-3">
                        <a href="/manager/tutors.php" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">🛡️</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">Tutor Approval Hub</div>
                                <div class="text-[11px] text-slate-500">Review DBS & verify credentials</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-amber-600 text-xs font-bold">&rarr;</span>
                        </a>

                        <a href="/tutors.php" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">🔍</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">View Public Tutors</div>
                                <div class="text-[11px] text-slate-500">Preview live public catalog</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-indigo-600 text-xs font-bold">&rarr;</span>
                        </a>

                        <a href="/" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:bg-indigo-50/40 hover:border-indigo-200 transition group">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold group-hover:scale-105 transition">🌐</div>
                            <div class="flex-1">
                                <div class="text-xs font-bold text-slate-900">Homepage & Landing</div>
                                <div class="text-[11px] text-slate-500">Inspect visitor experience</div>
                            </div>
                            <span class="text-slate-400 group-hover:text-emerald-600 text-xs font-bold">&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Platform Bookings Activity -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 mb-3">Recent Bookings Activity</h2>
                    <?php if (empty($recentBookings)): ?>
                        <p class="text-xs text-slate-400">No platform booking activity yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 divide-y divide-slate-100">
                            <?php foreach ($recentBookings as $idx => $rb): ?>
                                <div class="<?= $idx > 0 ? 'pt-3' : '' ?> text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($rb['subject_name']) ?></span>
                                        <?= UIHelper::renderBookingStatusBadge($rb['status']) ?>
                                    </div>
                                    <div class="text-slate-500 text-[11px] mt-1">
                                        <?= htmlspecialchars($rb['tutor_first_name'] . ' ' . $rb['tutor_last_name']) ?> ↔ <?= htmlspecialchars($rb['parent_first_name'] . ' ' . $rb['parent_last_name']) ?>
                                    </div>
                                    <div class="text-slate-400 text-[10px] mt-0.5">
                                        Ref: <?= htmlspecialchars($rb['booking_reference']) ?> • <?= UIHelper::formatDate($rb['created_at']) ?>
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

