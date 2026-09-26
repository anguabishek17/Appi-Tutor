<?php declare(strict_types=1);

/**
 * Parent Portal: My Bookings & Lesson History
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;
use App\Services\UIHelper;

// Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT']);
$csrfToken = CsrfService::getToken();
$db = Connection::getInstance();
$parentUserId = (int)$user['id'];

// Fetch parent's bookings with proposed reschedule & notes details
$stmt = $db->prepare('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.scheduled_start,
        b.scheduled_end,
        b.status,
        b.attendance_status,
        b.hourly_rate,
        b.total_amount,
        b.student_notes,
        b.rejection_reason,
        b.cancellation_reason,
        b.proposed_availability_slot_id,
        b.proposed_reschedule_start,
        b.proposed_reschedule_end,
        b.reschedule_proposed_by,
        b.created_at,
        u_tutor.first_name AS tutor_first_name,
        u_tutor.last_name AS tutor_last_name,
        u_tutor.avatar_url AS tutor_avatar,
        tp.id AS tutor_profile_id,
        tp.teaching_mode,
        s.name AS subject_name,
        c.name AS curriculum_name,
        sc.first_name AS child_first_name,
        sc.last_name AS child_last_name,
        sc.year_group AS child_year_group,
        ln.id AS lesson_note_id,
        ln.lesson_summary,
        ln.topics_covered,
        ln.homework_assigned,
        ln.student_progress,
        ln.student_progress_rating,
        ln.parent_feedback_notes,
        ln.next_lesson_focus
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    JOIN subjects s ON b.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    LEFT JOIN lesson_notes ln ON b.id = ln.booking_id
    WHERE b.parent_user_id = :parent_id
    ORDER BY b.scheduled_start DESC
');
$stmt->execute([':parent_id' => $parentUserId]);
$bookings = $stmt->fetchAll();

$nowLondon = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));
$minCancelThreshold = $nowLondon->modify('+24 hours');

// Group bookings into tabs
$upcomingBookings = [];
$pendingBookings = [];
$historyBookings = [];

foreach ($bookings as $b) {
    if ($b['status'] === 'PENDING') {
        $pendingBookings[] = $b;
    } elseif (in_array($b['status'], ['ACCEPTED', 'RESCHEDULE_PROPOSED'], true)) {
        $upcomingBookings[] = $b;
    } else { // COMPLETED, CANCELLED, REJECTED, SYSTEM_CANCELLED
        $historyBookings[] = $b;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings & Lesson History | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800 bg-slate-50 antialiased">

    <?php UIHelper::renderHeader($user, 'bookings'); ?>

    <main class="flex-grow max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">My Lesson Bookings & History</h1>
                <p class="text-slate-600 text-sm mt-1">Track confirmed sessions, pending requests, and review teacher feedback & homework.</p>
            </div>
            <a href="/tutors.php" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center gap-2 self-start sm:self-auto">
                <span>+ Book Another Lesson</span>
            </a>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-xs font-semibold" role="alert"></div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 sm:gap-3 mb-6 border-b border-slate-200 pb-3 overflow-x-auto">
            <button type="button" onclick="switchTab('upcoming')" id="tab_btn_upcoming" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-xs flex items-center gap-2 shrink-0">
                <span>Upcoming Lessons</span>
                <span class="px-2 py-0.5 rounded-full bg-indigo-700 text-white text-[10px]"><?= count($upcomingBookings) ?></span>
            </button>
            <button type="button" onclick="switchTab('pending')" id="tab_btn_pending" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 flex items-center gap-2 shrink-0">
                <span>Pending Requests</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px]"><?= count($pendingBookings) ?></span>
            </button>
            <button type="button" onclick="switchTab('history')" id="tab_btn_history" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 flex items-center gap-2 shrink-0">
                <span>Lesson History</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px]"><?= count($historyBookings) ?></span>
            </button>
        </div>

        <!-- TAB 1: UPCOMING -->
        <div id="tab_content_upcoming" class="space-y-4">
            <?php if (empty($upcomingBookings)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-xs">
                    <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">📅</div>
                    <h3 class="font-extrabold text-slate-800 text-base">No Upcoming Lessons</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">You have no confirmed lessons scheduled in the future.</p>
                    <a href="/tutors.php" class="mt-4 inline-block px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">
                        Find a UK Tutor
                    </a>
                </div>
            <?php else: ?>
                <?php renderBookingList($upcomingBookings, $minCancelThreshold); ?>
            <?php endif; ?>
        </div>

        <!-- TAB 2: PENDING -->
        <div id="tab_content_pending" class="space-y-4 hidden">
            <?php if (empty($pendingBookings)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-xs">
                    <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3 text-xl font-bold">⏳</div>
                    <h3 class="font-extrabold text-slate-800 text-base">No Pending Requests</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">You do not have any pending booking requests awaiting tutor response.</p>
                </div>
            <?php else: ?>
                <?php renderBookingList($pendingBookings, $minCancelThreshold); ?>
            <?php endif; ?>
        </div>

        <!-- TAB 3: HISTORY -->
        <div id="tab_content_history" class="space-y-4 hidden">
            <?php if (empty($historyBookings)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-xs">
                    <div class="w-12 h-12 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-3 text-xl font-bold">📜</div>
                    <h3 class="font-extrabold text-slate-800 text-base">No Past Lesson History</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Completed, cancelled, or rejected sessions will appear here.</p>
                </div>
            <?php else: ?>
                <?php renderBookingList($historyBookings, $minCancelThreshold); ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Lesson Summary Modal -->
    <div id="summaryModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="summaryModalTitle">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 id="summaryModalTitle" class="text-base font-bold text-slate-900">Lesson Summary & Teacher Feedback</h3>
                    <p class="text-xs text-slate-500" id="modal_ref_text">Lesson details and tutor recommendations</p>
                </div>
                <button type="button" onclick="closeSummaryModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Close summary modal">&times;</button>
            </div>

            <div id="summaryContent" class="space-y-3.5 text-xs text-slate-700">
                <div class="text-center py-8 text-slate-400">Loading lesson summary...</div>
            </div>

            <div class="flex justify-end pt-3 border-t border-slate-100">
                <button type="button" onclick="closeSummaryModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl transition focus:outline-none focus:ring-2 focus:ring-slate-400">
                    Close Summary
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div id="cancelBookingModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 id="cancelModalTitle" class="text-base font-bold text-slate-900">Cancel Lesson Booking</h3>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                        Cancellations must be made at least 24 hours before the lesson start time. The tutor will be notified.
                    </p>
                </div>
            </div>

            <form id="cancelBookingForm" class="space-y-3">
                <input type="hidden" id="cancel_booking_id" value="">
                <div>
                    <label for="cancel_reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reason for Cancellation (Optional)</label>
                    <textarea id="cancel_reason" rows="3" placeholder="e.g. Schedule conflict, illness..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeCancelModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400">Keep Booking</button>
                    <button type="submit" id="confirmCancelBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition focus:outline-none focus:ring-2 focus:ring-rose-500">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <?php UIHelper::renderConfirmationModal(); ?>
    <?php UIHelper::renderFooter(); ?>

    <?php
    function renderBookingList(array $list, DateTimeImmutable $minCancelThreshold) {
        foreach ($list as $b):
            $tz = new DateTimeZone('Europe/London');
            $start = new DateTimeImmutable($b['scheduled_start'], $tz);
            $end = new DateTimeImmutable($b['scheduled_end'], $tz);
            
            $isRescheduleProposed = ($b['status'] === 'RESCHEDULE_PROPOSED');
            $isPending = ($b['status'] === 'PENDING');
            $isAccepted = ($b['status'] === 'ACCEPTED');
            $isCompleted = ($b['status'] === 'COMPLETED');
            $isCancelled = ($b['status'] === 'CANCELLED');
            $isSystemCancelled = ($b['status'] === 'SYSTEM_CANCELLED');
            $isRejected = ($b['status'] === 'REJECTED');

            $canCancel = in_array($b['status'], ['PENDING', 'ACCEPTED', 'RESCHEDULE_PROPOSED'], true) && ($start >= $minCancelThreshold);
        ?>
            <div class="bg-white rounded-2xl border <?= $isRescheduleProposed ? 'border-indigo-300 ring-2 ring-indigo-100' : ($isCompleted ? 'border-blue-200' : 'border-slate-200/90') ?> shadow-xs p-5 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:border-slate-300 transition">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl <?= $isCompleted ? 'bg-blue-50 text-blue-700' : ($isRescheduleProposed ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700') ?> flex items-center justify-center font-bold text-base shrink-0 mt-0.5">
                        <?= $isCompleted ? '🎓' : ($isRescheduleProposed ? '🔄' : '📅') ?>
                    </div>
                    <div class="space-y-1.5 flex-1">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h3 class="font-bold text-slate-900 text-sm sm:text-base">
                                <?= htmlspecialchars($b['subject_name']) ?>
                            </h3>
                            <?= UIHelper::renderBookingStatusBadge($b['status']) ?>
                            <?php if (!empty($b['attendance_status']) && $b['attendance_status'] !== 'NOT_RECORDED'): ?>
                                <?= UIHelper::renderAttendanceBadge($b['attendance_status']) ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-600">
                            Tutor: <strong><?= htmlspecialchars($b['tutor_first_name'] . ' ' . $b['tutor_last_name']) ?></strong> • 
                            Student: <strong><?= htmlspecialchars(($b['child_first_name'] ?? 'Self') . ' ' . ($b['child_last_name'] ?? '')) ?></strong>
                            <?= !empty($b['child_year_group']) ? ' (' . htmlspecialchars($b['child_year_group']) . ')' : '' ?>
                        </p>

                        <!-- Schedule details -->
                        <div class="text-xs text-slate-600 flex flex-wrap items-center gap-2 pt-0.5">
                            <span class="font-semibold text-slate-800">🗓️ <?= UIHelper::formatDate($b['scheduled_start'], 'D, d M Y') ?></span>
                            <span>•</span>
                            <span class="font-semibold text-indigo-700">🕒 <?= UIHelper::formatTimeRange($b['scheduled_start'], $b['scheduled_end']) ?> (UK)</span>
                            <span>•</span>
                            <span class="font-mono text-[11px] text-slate-400">Ref: <?= htmlspecialchars($b['booking_reference']) ?></span>
                        </div>

                        <!-- Reschedule details if active -->
                        <?php if ($isRescheduleProposed && !empty($b['proposed_reschedule_start'])): 
                            $pStart = new DateTimeImmutable($b['proposed_reschedule_start'], $tz);
                            $pEnd = new DateTimeImmutable($b['proposed_reschedule_end'], $tz);
                        ?>
                            <div class="mt-3 p-3.5 bg-indigo-50/70 rounded-xl border border-indigo-200 text-xs text-indigo-950 space-y-2">
                                <div class="font-bold flex items-center gap-1.5 text-indigo-900">
                                    <span>🔄 Reschedule Proposed by Tutor</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Current Time</div>
                                        <div class="font-semibold text-slate-800 mt-0.5"><?= $start->format('D, d M Y') ?></div>
                                        <div class="text-slate-600"><?= UIHelper::formatTimeRange($b['scheduled_start'], $b['scheduled_end']) ?></div>
                                    </div>
                                    <div class="bg-white p-2.5 rounded-lg border border-indigo-300 ring-1 ring-indigo-300">
                                        <div class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">Proposed New Time</div>
                                        <div class="font-bold text-indigo-950 mt-0.5"><?= $pStart->format('D, d M Y') ?></div>
                                        <div class="text-indigo-700 font-semibold"><?= UIHelper::formatTimeRange($b['proposed_reschedule_start'], $b['proposed_reschedule_end']) ?> (UK)</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <button type="button" onclick="acceptReschedule(<?= (int)$b['booking_id'] ?>)" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg shadow-xs transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        Accept New Time
                                    </button>
                                    <button type="button" onclick="declineReschedule(<?= (int)$b['booking_id'] ?>)" class="px-4 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-lg transition focus:outline-none focus:ring-2 focus:ring-slate-400">
                                        Decline
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isRejected && !empty($b['rejection_reason'])): ?>
                            <div class="text-xs text-rose-700 bg-rose-50 p-2.5 rounded-xl border border-rose-200 mt-2">
                                ❌ Reason: <?= htmlspecialchars($b['rejection_reason']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (($isCancelled || $isSystemCancelled) && !empty($b['cancellation_reason'])): ?>
                            <div class="text-xs text-slate-700 bg-slate-100 p-2.5 rounded-xl border border-slate-200 mt-2">
                                🚫 Cancellation note: <?= htmlspecialchars($b['cancellation_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row md:flex-col items-end justify-between gap-4 self-stretch md:self-center border-t md:border-t-0 pt-4 md:pt-0 border-slate-100 shrink-0">
                    <div class="text-left sm:text-right">
                        <div class="text-lg font-extrabold text-indigo-600"><?= UIHelper::formatCurrency($b['total_amount']) ?></div>
                        <div class="text-[11px] text-slate-400">Rate: <?= UIHelper::formatCurrency($b['hourly_rate']) ?>/hr</div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php if ($isCompleted || !empty($b['lesson_note_id'])): ?>
                            <button type="button" onclick="viewLessonSummary(<?= (int)$b['booking_id'] ?>)" class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-bold transition border border-blue-200">
                                📄 Lesson Notes
                            </button>
                        <?php endif; ?>

                        <a href="/tutor.php?id=<?= (int)$b['tutor_profile_id'] ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition">
                            Tutor Profile
                        </a>

                        <?php if ($canCancel): ?>
                            <button type="button" onclick="openCancelModal(<?= (int)$b['booking_id'] ?>)" class="px-3 py-1.5 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-semibold transition">
                                Cancel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach;
    }
    ?>

    <script>
        const csrfToken = '<?= $csrfToken ?>';
        const alertBox = document.getElementById('alertBox');
        const summaryModal = document.getElementById('summaryModal');
        const summaryContent = document.getElementById('summaryContent');
        const cancelModal = document.getElementById('cancelBookingModal');
        const cancelForm = document.getElementById('cancelBookingForm');

        function switchTab(tab) {
            ['upcoming', 'pending', 'history'].forEach(t => {
                const btn = document.getElementById('tab_btn_' + t);
                const content = document.getElementById('tab_content_' + t);
                if (t === tab) {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-xs flex items-center gap-2 shrink-0';
                    content.classList.remove('hidden');
                } else {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 flex items-center gap-2 shrink-0';
                    content.classList.add('hidden');
                }
            });
        }

        async function viewLessonSummary(bookingId) {
            summaryContent.innerHTML = '<div class="text-center py-8 text-slate-400">Loading lesson summary...</div>';
            summaryModal.classList.remove('hidden');

            try {
                const res = await fetch('/api/parent/lesson.php?booking_id=' + bookingId);
                const json = await res.json();
                if (!json.success) {
                    summaryContent.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 rounded-xl">${escapeHtml(json.message || 'Failed to load lesson summary')}</div>`;
                    return;
                }

                const d = json.data;
                document.getElementById('modal_ref_text').textContent = `Booking Ref: ${d.booking_reference} • Tutor: ${d.tutor_name}`;

                let attBadge = '<span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700">Not Recorded</span>';
                if (d.attendance_status === 'ATTENDED') attBadge = '<span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">Attended ✅</span>';
                if (d.attendance_status === 'PARTIAL') attBadge = '<span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">Partial ⚠️</span>';
                if (d.attendance_status === 'ABSENT') attBadge = '<span class="px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800">Absent ❌</span>';

                let stars = '';
                if (d.student_progress_rating) {
                    stars = '⭐'.repeat(d.student_progress_rating) + ` (${d.student_progress_rating}/5)`;
                }

                summaryContent.innerHTML = `
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Subject</span><strong class="text-slate-900">${escapeHtml(d.subject_name)}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Student</span><strong class="text-slate-900">${escapeHtml(d.child_name)}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Scheduled Time</span><strong class="text-slate-900">${escapeHtml(d.scheduled_start)}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Attendance</span>${attBadge}</div>
                    </div>

                    ${d.lesson_summary ? `
                        <div class="p-3.5 bg-indigo-50/50 rounded-xl border border-indigo-100">
                            <span class="text-indigo-900 font-bold block mb-1">📝 Lesson Summary</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.lesson_summary)}</p>
                        </div>
                    ` : ''}

                    ${d.topics_covered ? `
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-slate-800 font-bold block mb-1">📚 Topics Covered</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.topics_covered)}</p>
                        </div>
                    ` : ''}

                    ${(d.student_progress || stars) ? `
                        <div class="p-3.5 bg-emerald-50/50 rounded-xl border border-emerald-100">
                            <span class="text-emerald-900 font-bold block mb-1">📈 Student Progress ${stars ? '• ' + stars : ''}</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.student_progress || 'Feedback recorded.')}</p>
                        </div>
                    ` : ''}

                    ${d.homework_assigned ? `
                        <div class="p-3.5 bg-amber-50/50 rounded-xl border border-amber-100">
                            <span class="text-amber-900 font-bold block mb-1">✏️ Homework & Recommended Practice</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.homework_assigned)}</p>
                        </div>
                    ` : ''}

                    ${d.next_lesson_focus ? `
                        <div class="p-3.5 bg-purple-50/50 rounded-xl border border-purple-100">
                            <span class="text-purple-900 font-bold block mb-1">🎯 Next Lesson Focus</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.next_lesson_focus)}</p>
                        </div>
                    ` : ''}
                `;
            } catch (err) {
                summaryContent.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 rounded-xl">Network error fetching lesson summary.</div>`;
            }
        }

        function closeSummaryModal() {
            summaryModal.classList.add('hidden');
        }

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = type === 'success'
                ? 'mb-6 p-4 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            alertBox.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        async function acceptReschedule(bookingId) {
            const confirmed = await window.AppiConfirm.show({
                title: 'Accept Proposed Reschedule',
                message: 'Are you sure you want to accept this proposed time? Your lesson schedule will be updated.',
                confirmText: 'Accept Reschedule',
                isDestructive: false
            });

            if (!confirmed) return;

            try {
                const res = await fetch('/api/bookings/reschedule-accept.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookingId, csrf_token: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Reschedule accepted successfully! Refreshing...', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message || 'Failed to accept reschedule', 'error');
                }
            } catch (err) {
                showAlert('Network error while accepting reschedule.', 'error');
            }
        }

        async function declineReschedule(bookingId) {
            const confirmed = await window.AppiConfirm.show({
                title: 'Decline Proposed Reschedule',
                message: 'Decline this proposed reschedule time? The original booking time will remain in place.',
                confirmText: 'Decline Proposal',
                isDestructive: true
            });

            if (!confirmed) return;

            try {
                const res = await fetch('/api/bookings/reschedule-decline.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookingId, csrf_token: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Reschedule proposal declined. Refreshing...', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message || 'Failed to decline reschedule', 'error');
                }
            } catch (err) {
                showAlert('Network error while declining reschedule.', 'error');
            }
        }

        function openCancelModal(bookingId) {
            document.getElementById('cancel_booking_id').value = bookingId;
            document.getElementById('cancel_reason').value = '';
            cancelModal.classList.remove('hidden');
        }

        function closeCancelModal() {
            cancelModal.classList.add('hidden');
        }

        cancelForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const bookingId = document.getElementById('cancel_booking_id').value;
            const reason = document.getElementById('cancel_reason').value.trim();
            const btn = document.getElementById('confirmCancelBtn');

            btn.disabled = true;
            btn.textContent = 'Cancelling...';

            try {
                const res = await fetch('/api/bookings/cancel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: bookingId,
                        cancellation_reason: reason,
                        csrf_token: csrfToken
                    })
                });
                const data = await res.json();
                closeCancelModal();

                if (data.success) {
                    showAlert('Booking cancelled successfully. Refreshing...', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message || 'Failed to cancel booking', 'error');
                }
            } catch (err) {
                showAlert('Network error while cancelling booking.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Confirm Cancellation';
            }
        });
    </script>
</body>
</html>

