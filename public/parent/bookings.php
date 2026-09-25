<?php declare(strict_types=1);

/**
 * Parent Portal: My Bookings
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

// Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT']);
$db = Connection::getInstance();
$parentUserId = (int)$user['id'];

// Fetch parent's bookings
$stmt = $db->prepare('
    SELECT 
        b.id AS booking_id,
        b.booking_reference,
        b.scheduled_start,
        b.scheduled_end,
        b.status,
        b.hourly_rate,
        b.total_amount,
        b.student_notes,
        b.rejection_reason,
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
        sc.year_group AS child_year_group
    FROM bookings b
    JOIN tutor_profiles tp ON b.tutor_profile_id = tp.id
    JOIN users u_tutor ON tp.user_id = u_tutor.id
    JOIN subjects s ON b.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    LEFT JOIN students_children sc ON b.student_child_id = sc.id
    WHERE b.parent_user_id = :parent_id
    ORDER BY b.scheduled_start ASC
');
$stmt->execute([':parent_id' => $parentUserId]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | AppiTutors</title>
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
                <h1 class="text-2xl font-extrabold text-slate-900">My Lesson Bookings</h1>
                <p class="text-slate-600 text-sm mt-1">Track pending booking requests and confirmed tutoring lessons.</p>
            </div>
            <a href="/tutors.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition flex items-center gap-2">
                <span>+ Book Another Lesson</span>
            </a>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
                <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">📅</div>
                <h3 class="font-extrabold text-slate-800 text-lg">No Lesson Bookings Yet</h3>
                <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">Browse our vetted UK tutors, pick a suitable time slot, and request your first session.</p>
                <a href="/tutors.php" class="mt-4 inline-block px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md">Browse Tutors Now</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $b): 
                    $start = new DateTime($b['scheduled_start']);
                    $end = new DateTime($b['scheduled_end']);
                    
                    $statusBadge = match($b['status']) {
                        'PENDING' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'ACCEPTED' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'REJECTED' => 'bg-rose-100 text-rose-800 border-rose-200',
                        'CANCELLED' => 'bg-slate-100 text-slate-700 border-slate-200',
                        default => 'bg-indigo-100 text-indigo-800 border-indigo-200'
                    };

                    $statusNote = match($b['status']) {
                        'PENDING' => '⏳ Waiting for tutor response',
                        'ACCEPTED' => '✅ Booking accepted',
                        'REJECTED' => '❌ Booking rejected' . (!empty($b['rejection_reason']) ? ': ' . $b['rejection_reason'] : ''),
                        default => $b['status']
                    };
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-lg">
                                🎓
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3 class="font-extrabold text-slate-900 text-base">
                                        <?= htmlspecialchars($b['subject_name']) ?>
                                    </h3>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold border <?= $statusBadge ?>">
                                        <?= htmlspecialchars($b['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Tutor: <strong><?= htmlspecialchars($b['tutor_first_name'] . ' ' . $b['tutor_last_name'][0] . '.') ?></strong> • 
                                    Student: <strong><?= htmlspecialchars(($b['child_first_name'] ?? 'Self') . ' ' . ($b['child_last_name'] ?? '')) ?></strong>
                                </p>
                                <div class="text-xs text-slate-600 mt-2 flex flex-wrap items-center gap-3">
                                    <span>🗓️ <?= $start->format('D, d M Y') ?></span>
                                    <span>•</span>
                                    <span>🕒 <?= $start->format('H:i') ?> – <?= $end->format('H:i') ?> (UK)</span>
                                    <span>•</span>
                                    <span class="font-mono text-[11px] text-slate-400">Ref: <?= htmlspecialchars($b['booking_reference']) ?></span>
                                </div>
                                <div class="mt-2 text-xs font-semibold <?= $b['status'] === 'ACCEPTED' ? 'text-emerald-700' : ($b['status'] === 'REJECTED' ? 'text-rose-700' : 'text-amber-700') ?>">
                                    <?= htmlspecialchars($statusNote) ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 pt-4 md:pt-0 border-t md:border-t-0 border-slate-100">
                            <div class="text-left md:text-right">
                                <div class="text-lg font-extrabold text-indigo-600">£<?= number_format((float)$b['total_amount'], 2) ?></div>
                                <div class="text-[11px] text-slate-400">Rate: £<?= number_format((float)$b['hourly_rate'], 2) ?>/hr</div>
                            </div>
                            <a href="/tutor.php?id=<?= (int)$b['tutor_profile_id'] ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition">
                                Tutor Profile &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>
</body>
</html>
