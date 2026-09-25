<?php declare(strict_types=1);

/**
 * Parent Portal: My Bookings & Lesson History
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

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
<body class="min-h-full flex flex-col justify-between text-slate-800">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/parent/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-2 text-sm font-semibold">
                    <a href="/parent/dashboard.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Overview</a>
                    <a href="/parent/children.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Children</a>
                    <a href="/parent/bookings.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">My Bookings</a>
                    <a href="/tutors.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Find a Tutor</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">My Lesson Bookings & History</h1>
                <p class="text-slate-600 text-sm mt-1">Track upcoming sessions, pending requests, and view detailed lesson notes & homework.</p>
            </div>
            <a href="/tutors.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition flex items-center gap-2">
                <span>+ Book Another Lesson</span>
            </a>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-sm font-medium"></div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-3">
            <button type="button" onclick="switchTab('upcoming')" id="tab_btn_upcoming" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-sm flex items-center gap-2">
                <span>Upcoming Lessons</span>
                <span class="px-2 py-0.5 rounded-full bg-indigo-700 text-white text-[11px]"><?= count($upcomingBookings) ?></span>
            </button>
            <button type="button" onclick="switchTab('pending')" id="tab_btn_pending" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2">
                <span>Pending Requests</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px]"><?= count($pendingBookings) ?></span>
            </button>
            <button type="button" onclick="switchTab('history')" id="tab_btn_history" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2">
                <span>Lesson History</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px]"><?= count($historyBookings) ?></span>
            </button>
        </div>

        <!-- TAB 1: UPCOMING -->
        <div id="tab_content_upcoming" class="space-y-4">
            <?php if (empty($upcomingBookings)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
                    <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">📅</div>
                    <h3 class="font-extrabold text-slate-800 text-base">No Upcoming Lessons</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">You have no confirmed lessons scheduled in the future.</p>
                </div>
            <?php else: ?>
                <?php renderBookingList($upcomingBookings, $minCancelThreshold); ?>
            <?php endif; ?>
        </div>

        <!-- TAB 2: PENDING -->
        <div id="tab_content_pending" class="space-y-4 hidden">
            <?php if (empty($pendingBookings)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
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
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
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
    <div id="summaryModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 space-y-4 my-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Lesson Summary & Feedback</h3>
                    <p class="text-xs text-slate-500" id="modal_ref_text">Lesson details and tutor recommendations</p>
                </div>
                <button type="button" onclick="closeSummaryModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div id="summaryContent" class="space-y-3.5 text-xs text-slate-700">
                <div class="text-center py-8 text-slate-400">Loading lesson summary...</div>
            </div>

            <div class="flex justify-end pt-3 border-t border-slate-100">
                <button type="button" onclick="closeSummaryModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-xl transition">
                    Close Summary
                </button>
            </div>
        </div>
    </div>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white mt-16">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>

    <?php
    function renderBookingList(array $list, DateTimeImmutable $minCancelThreshold) {
        foreach ($list as $b):
            $start = new DateTime($b['scheduled_start']);
            $end = new DateTime($b['scheduled_end']);
            
            $isRescheduleProposed = ($b['status'] === 'RESCHEDULE_PROPOSED');
            $isPending = ($b['status'] === 'PENDING');
            $isAccepted = ($b['status'] === 'ACCEPTED');
            $isCompleted = ($b['status'] === 'COMPLETED');
            $isCancelled = ($b['status'] === 'CANCELLED');
            $isSystemCancelled = ($b['status'] === 'SYSTEM_CANCELLED');
            $isRejected = ($b['status'] === 'REJECTED');

            $canCancel = in_array($b['status'], ['PENDING', 'ACCEPTED', 'RESCHEDULE_PROPOSED'], true) && ($start >= $minCancelThreshold);

            $statusBadge = match($b['status']) {
                'PENDING' => 'bg-amber-100 text-amber-800 border-amber-200',
                'ACCEPTED' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'COMPLETED' => 'bg-blue-100 text-blue-800 border-blue-200',
                'RESCHEDULE_PROPOSED' => 'bg-purple-100 text-purple-800 border-purple-200',
                'REJECTED' => 'bg-rose-100 text-rose-800 border-rose-200',
                'CANCELLED' => 'bg-slate-100 text-slate-700 border-slate-200',
                'SYSTEM_CANCELLED' => 'bg-slate-100 text-slate-700 border-slate-200',
                default => 'bg-indigo-100 text-indigo-800 border-indigo-200'
            };

            $statusLabel = match($b['status']) {
                'RESCHEDULE_PROPOSED' => 'Reschedule Proposed',
                'PENDING' => 'Pending Review',
                'ACCEPTED' => 'Confirmed',
                'COMPLETED' => 'Completed ✅',
                'REJECTED' => 'Rejected',
                'CANCELLED' => 'Cancelled',
                'SYSTEM_CANCELLED' => 'System Cancelled',
                default => $b['status']
            };

            $attendanceBadge = '';
            if (!empty($b['attendance_status']) && $b['attendance_status'] !== 'NOT_RECORDED') {
                if ($b['attendance_status'] === 'ATTENDED') {
                    $attendanceBadge = '<span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Attended</span>';
                } elseif ($b['attendance_status'] === 'PARTIAL') {
                    $attendanceBadge = '<span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Partial Attendance</span>';
                } elseif ($b['attendance_status'] === 'ABSENT') {
                    $attendanceBadge = '<span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Absent</span>';
                }
            }
        ?>
            <div class="bg-white rounded-2xl border <?= $isRescheduleProposed ? 'border-purple-300 ring-2 ring-purple-100' : ($isCompleted ? 'border-blue-200' : 'border-slate-200') ?> shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl <?= $isCompleted ? 'bg-blue-50 text-blue-700' : ($isRescheduleProposed ? 'bg-purple-50 text-purple-700' : 'bg-indigo-50 text-indigo-700') ?> flex items-center justify-center font-bold text-lg">
                        <?= $isCompleted ? '🎓' : '📅' ?>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-3">
                            <h3 class="font-extrabold text-slate-900 text-base">
                                <?= htmlspecialchars($b['subject_name']) ?>
                            </h3>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-bold border <?= $statusBadge ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                            <?= $attendanceBadge ?>
                        </div>
                        <p class="text-xs text-slate-500">
                            Tutor: <strong><?= htmlspecialchars($b['tutor_first_name'] . ' ' . $b['tutor_last_name'][0] . '.') ?></strong> • 
                            Student: <strong><?= htmlspecialchars(($b['child_first_name'] ?? 'Self') . ' ' . ($b['child_last_name'] ?? '')) ?></strong>
                            <?= !empty($b['child_year_group']) ? ' (' . htmlspecialchars($b['child_year_group']) . ')' : '' ?>
                        </p>

                        <!-- Schedule details -->
                        <div class="text-xs text-slate-600 flex flex-wrap items-center gap-2 pt-1">
                            <span>🗓️ <?= $start->format('D, d M Y') ?></span>
                            <span>•</span>
                            <span>🕒 <?= $start->format('H:i') ?> – <?= $end->format('H:i') ?> (UK)</span>
                            <span>•</span>
                            <span class="font-mono text-[11px] text-slate-400">Ref: <?= htmlspecialchars($b['booking_reference']) ?></span>
                        </div>

                        <!-- Reschedule details if active -->
                        <?php if ($isRescheduleProposed && !empty($b['proposed_reschedule_start'])): 
                            $pStart = new DateTime($b['proposed_reschedule_start']);
                            $pEnd = new DateTime($b['proposed_reschedule_end']);
                        ?>
                            <div class="mt-3 p-3.5 bg-purple-50 rounded-xl border border-purple-200 text-xs text-purple-900 space-y-2">
                                <div class="font-bold flex items-center gap-2 text-purple-800">
                                    <span>🔄 Reschedule Proposed by Tutor:</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <div class="bg-white/80 p-2.5 rounded-lg border border-purple-100">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Current Time</div>
                                        <div class="font-semibold text-slate-800 mt-0.5"><?= $start->format('D, d M Y') ?></div>
                                        <div class="text-slate-600"><?= $start->format('H:i') ?> – <?= $end->format('H:i') ?> (UK)</div>
                                    </div>
                                    <div class="bg-white/80 p-2.5 rounded-lg border border-purple-200 ring-1 ring-purple-300">
                                        <div class="text-[11px] font-bold text-purple-700 uppercase tracking-wider">Proposed New Time</div>
                                        <div class="font-semibold text-purple-950 mt-0.5"><?= $pStart->format('D, d M Y') ?></div>
                                        <div class="text-purple-700"><?= $pStart->format('H:i') ?> – <?= $pEnd->format('H:i') ?> (UK)</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-2">
                                    <button type="button" onclick="acceptReschedule(<?= (int)$b['booking_id'] ?>)" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-lg shadow-sm transition">
                                        Accept New Time
                                    </button>
                                    <button type="button" onclick="declineReschedule(<?= (int)$b['booking_id'] ?>)" class="px-4 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-lg transition">
                                        Decline
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($isRejected && !empty($b['rejection_reason'])): ?>
                            <div class="text-xs text-rose-700 bg-rose-50 p-2 rounded-lg border border-rose-200 mt-2">
                                ❌ Rejection reason: <?= htmlspecialchars($b['rejection_reason']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (($isCancelled || $isSystemCancelled) && !empty($b['cancellation_reason'])): ?>
                            <div class="text-xs text-slate-700 bg-slate-100 p-2 rounded-lg border border-slate-200 mt-2">
                                🚫 Cancellation note: <?= htmlspecialchars($b['cancellation_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row md:flex-col items-end justify-between gap-4 self-stretch md:self-center border-t md:border-t-0 pt-4 md:pt-0 border-slate-100">
                    <div class="text-left sm:text-right">
                        <div class="text-lg font-extrabold text-indigo-600">£<?= number_format((float)$b['total_amount'], 2) ?></div>
                        <div class="text-[11px] text-slate-400">Rate: £<?= number_format((float)$b['hourly_rate'], 2) ?>/hr</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($isCompleted || !empty($b['lesson_note_id'])): ?>
                            <button type="button" onclick="viewLessonSummary(<?= (int)$b['booking_id'] ?>)" class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-bold transition border border-blue-200">
                                📄 View Lesson Summary
                            </button>
                        <?php endif; ?>

                        <a href="/tutor.php?id=<?= (int)$b['tutor_profile_id'] ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition">
                            Tutor Profile
                        </a>

                        <?php if ($canCancel): ?>
                            <button type="button" onclick="cancelBooking(<?= (int)$b['booking_id'] ?>)" class="px-3 py-1.5 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold transition">
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

        function switchTab(tab) {
            ['upcoming', 'pending', 'history'].forEach(t => {
                const btn = document.getElementById('tab_btn_' + t);
                const content = document.getElementById('tab_content_' + t);
                if (t === tab) {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-sm flex items-center gap-2';
                    content.classList.remove('hidden');
                } else {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2';
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
                    summaryContent.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 rounded-xl">${json.message || 'Failed to load lesson summary'}</div>`;
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
                    <div class="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Subject</span><strong>${escapeHtml(d.subject_name)}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Student</span><strong>${escapeHtml(d.child_name)}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Date & Time</span><strong>${d.scheduled_start}</strong></div>
                        <div><span class="text-slate-400 block text-[10px] uppercase font-bold">Attendance</span>${attBadge}</div>
                    </div>

                    ${d.lesson_summary ? `
                        <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100">
                            <span class="text-indigo-900 font-bold block mb-1">📝 Lesson Summary</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.lesson_summary)}</p>
                        </div>
                    ` : ''}

                    ${d.topics_covered ? `
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-slate-800 font-bold block mb-1">📚 Topics Covered</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.topics_covered)}</p>
                        </div>
                    ` : ''}

                    ${(d.student_progress || stars) ? `
                        <div class="p-3 bg-emerald-50/50 rounded-xl border border-emerald-100">
                            <span class="text-emerald-900 font-bold block mb-1">📈 Student Progress ${stars ? '• ' + stars : ''}</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.student_progress || 'Feedback recorded.')}</p>
                        </div>
                    ` : ''}

                    ${d.homework_assigned ? `
                        <div class="p-3 bg-amber-50/50 rounded-xl border border-amber-100">
                            <span class="text-amber-900 font-bold block mb-1">✏️ Homework & Recommended Practice</span>
                            <p class="text-slate-700 whitespace-pre-wrap">${escapeHtml(d.homework_assigned)}</p>
                        </div>
                    ` : ''}

                    ${d.next_lesson_focus ? `
                        <div class="p-3 bg-purple-50/50 rounded-xl border border-purple-100">
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
                ? 'mb-6 p-4 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        async function acceptReschedule(bookingId) {
            if (!confirm('Accept this proposed reschedule time? This will update your confirmed lesson schedule.')) return;

            try {
                const res = await fetch('/api/bookings/reschedule-accept.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookingId, csrf_token: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Reschedule accepted successfully! Page will refresh...', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Failed to accept reschedule', 'error');
                }
            } catch (err) {
                showAlert('Network error while accepting reschedule.', 'error');
            }
        }

        async function declineReschedule(bookingId) {
            if (!confirm('Decline this proposed reschedule? The original booking time will remain active.')) return;

            try {
                const res = await fetch('/api/bookings/reschedule-decline.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookingId, csrf_token: csrfToken })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Reschedule proposal declined. Page will refresh...', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Failed to decline reschedule', 'error');
                }
            } catch (err) {
                showAlert('Network error while declining reschedule.', 'error');
            }
        }

        async function cancelBooking(bookingId) {
            const reason = prompt('Are you sure you want to cancel this booking? Enter an optional cancellation reason:');
            if (reason === null) return;

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
                if (data.success) {
                    showAlert('Booking cancelled successfully. Page will refresh...', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Failed to cancel booking', 'error');
                }
            } catch (err) {
                showAlert('Network error while cancelling booking.', 'error');
            }
        }
    </script>
</body>
</html>
