<?php declare(strict_types=1);

/**
 * Public Tutor Detail Page
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;

$user = AuthService::user();
$db = Connection::getInstance();

$tutorProfileId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$tutorProfileId || $tutorProfileId <= 0) {
    http_response_code(404);
    echo "<h1>404 Tutor Not Found</h1><p><a href='/tutors.php'>Return to Tutor Search</a></p>";
    exit;
}

// Fetch tutor details (Approved only)
$stmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.teaching_mode,
        tp.is_featured
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.id = :id 
      AND tp.approval_status = "APPROVED" 
      AND u.status = "ACTIVE"
    LIMIT 1
');
$stmt->execute([':id' => $tutorProfileId]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><title>Tutor Not Found</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-50 flex items-center justify-center min-h-screen'><div class='text-center p-8 bg-white rounded-2xl shadow border'><h1 class='text-2xl font-bold text-slate-800'>Tutor Profile Unavailable</h1><p class='text-slate-500 mt-2'>This tutor is either pending review or not publicly available.</p><a href='/tutors.php' class='mt-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold text-sm'>Browse Tutors</a></div></body></html>";
    exit;
}

// Fetch subjects
$subjStmt = $db->prepare('
    SELECT 
        s.id AS subject_id,
        s.name AS subject_name,
        s.slug AS subject_slug,
        c.name AS curriculum_name,
        c.code AS curriculum_code
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    JOIN curricula c ON s.curriculum_id = c.id
    WHERE ts.tutor_profile_id = :tpid
    ORDER BY c.id ASC, s.name ASC
');
$subjStmt->execute([':tpid' => $tutorProfileId]);
$subjects = $subjStmt->fetchAll();

// Group subjects by curriculum
$subjectsByCurriculum = [];
foreach ($subjects as $s) {
    $cName = $s['curriculum_name'];
    if (!isset($subjectsByCurriculum[$cName])) {
        $subjectsByCurriculum[$cName] = [];
    }
    $subjectsByCurriculum[$cName][] = $s['subject_name'];
}

// Fetch upcoming available slots (Strictly non-blocked and future)
$slotStmt = $db->prepare('
    SELECT 
        id AS slot_id,
        start_time,
        end_time,
        session_type,
        delivery_mode,
        max_capacity,
        booked_count,
        (max_capacity - booked_count) AS spaces_left
    FROM availability_slots
    WHERE tutor_profile_id = :tpid
      AND is_blocked = 0
      AND start_time > NOW()
      AND booked_count < max_capacity
    ORDER BY start_time ASC
');
$slotStmt->execute([':tpid' => $tutorProfileId]);
$slots = $slotStmt->fetchAll();

$initials = ($tutor['first_name'][0] ?? '') . ($tutor['last_name'][0] ?? '');
$teachingModeBadge = $tutor['teaching_mode'] === 'ONLINE' ? 'Online Tutoring Only' : ($tutor['teaching_mode'] === 'IN_PERSON' ? 'In-Person Tutoring Only' : 'Available Online & In-Person');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tutor['first_name'] . ' ' . $tutor['last_name'][0] . '.') ?> | Verified UK Tutor | AppiTutors</title>
    <meta name="description" content="<?= htmlspecialchars($tutor['headline'] ?: 'Expert tutoring in UK subjects') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col justify-between text-slate-800">

    <!-- Header Navigation -->
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-xl shadow-md shadow-indigo-200">A</div>
                <span class="text-2xl font-extrabold tracking-tight text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
            </a>

            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                <a href="/tutors.php" class="hover:text-indigo-600 transition">&larr; Back to All Tutors</a>
            </nav>

            <div class="flex items-center gap-4">
                <?php if ($user): ?>
                    <a href="/dashboard.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition">
                        Dashboard (<?= htmlspecialchars($user['first_name']) ?>)
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="text-sm font-semibold text-slate-700 hover:text-indigo-600 transition px-3 py-2">Log In</a>
                    <a href="/register.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Tutor Profile Top Header Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-start sm:items-center gap-5">
                    <?php if (!empty($tutor['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($tutor['avatar_url']) ?>" alt="<?= htmlspecialchars($tutor['first_name']) ?>" 
                             class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl object-cover border-2 border-indigo-100 shadow-md">
                    <?php else: ?>
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-extrabold text-2xl shadow-md shadow-indigo-100">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900"><?= htmlspecialchars($tutor['first_name'] . ' ' . $tutor['last_name'][0] . '.') ?></h1>
                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                <span>✓</span> DBS Checked & Approved
                            </span>
                        </div>
                        <p class="text-slate-700 font-semibold text-base mt-1"><?= htmlspecialchars($tutor['headline'] ?: 'Academic Tutor') ?></p>
                        
                        <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-slate-500 mt-3">
                            <span>🎓 <?= (int)$tutor['experience_years'] ?> Year<?= (int)$tutor['experience_years'] === 1 ? '' : 's' ?> Tutoring Experience</span>
                            <span>•</span>
                            <span>📍 <?= htmlspecialchars($teachingModeBadge) ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 flex flex-row md:flex-col items-center justify-between md:text-right gap-4 min-w-[200px]">
                    <div>
                        <div class="text-3xl font-extrabold text-indigo-600">£<?= number_format((float)$tutor['hourly_rate'], 2) ?></div>
                        <div class="text-xs text-slate-500 font-semibold">Standard Hourly Rate</div>
                    </div>
                    <a href="#available-slots" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition text-center">
                        View Schedule
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Bio, Qualifications, Subjects -->
            <div class="lg:col-span-2 space-y-8">
                <!-- About / Bio -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-lg font-extrabold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        About My Tutoring
                    </h2>
                    <div class="text-slate-700 text-sm leading-relaxed whitespace-pre-line">
                        <?= htmlspecialchars($tutor['bio'] ?: 'No biography details provided yet.') ?>
                    </div>
                </div>

                <!-- Academic Qualifications -->
                <?php if (!empty($tutor['qualifications'])): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                        <h2 class="text-lg font-extrabold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                            Degrees & Qualifications
                        </h2>
                        <div class="text-slate-700 text-sm leading-relaxed whitespace-pre-line bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <?= htmlspecialchars($tutor['qualifications']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Subjects & Curricula -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-lg font-extrabold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        Subjects & Curricula Taught
                    </h2>

                    <?php if (empty($subjectsByCurriculum)): ?>
                        <p class="text-xs text-slate-500">No subjects currently specified.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($subjectsByCurriculum as $curriculumName => $subjList): ?>
                                <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-700 mb-2"><?= htmlspecialchars($curriculumName) ?></h3>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($subjList as $sName): ?>
                                            <span class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-800 shadow-2xs">
                                                <?= htmlspecialchars($sName) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 1 Col: Available Slots -->
            <div class="lg:col-span-1" id="available-slots">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sticky top-24">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-extrabold text-slate-900">Available Slots</h2>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800"><?= count($slots) ?> open</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Select an upcoming session slot to book with <?= htmlspecialchars($tutor['first_name']) ?>. (Booking will be available in Phase 4).</p>

                    <?php if (empty($slots)): ?>
                        <div class="text-center py-8 border-2 border-dashed border-slate-200 rounded-xl">
                            <div class="text-slate-400 text-2xl mb-1">📅</div>
                            <p class="text-xs font-semibold text-slate-700">No available slots open right now.</p>
                            <p class="text-[11px] text-slate-400 mt-1">Please check back soon or message the tutor.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                            <?php foreach ($slots as $slot): 
                                $start = new DateTime($slot['start_time']);
                                $end = new DateTime($slot['end_time']);
                            ?>
                                <div class="p-3.5 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/20 transition">
                                    <div class="flex items-center justify-between">
                                        <div class="font-extrabold text-slate-900 text-sm">
                                            <?= $start->format('D, d M Y') ?>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded <?= $slot['session_type'] === 'ONE_TO_ONE' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800' ?>">
                                            <?= $slot['session_type'] === 'ONE_TO_ONE' ? '1-to-1' : 'Group' ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-slate-600 mt-1.5">
                                        <div class="font-semibold">
                                            🕒 <?= $start->format('H:i') ?> – <?= $end->format('H:i') ?> (UK)
                                        </div>
                                        <div class="text-[11px] font-medium text-slate-500">
                                            <?= $slot['delivery_mode'] === 'ONLINE' ? '🌐 Online' : '📍 In-Person' ?>
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                        <span class="text-emerald-700 font-bold">✓ <?= (int)$slot['spaces_left'] ?> space<?= (int)$slot['spaces_left'] === 1 ? '' : 's' ?> left</span>
                                        <button type="button" onclick="alert('Lesson booking will be activated in Phase 4!');" class="px-3 py-1 bg-slate-900 hover:bg-indigo-600 text-white font-bold rounded-lg transition text-[11px]">
                                            Book Slot &rarr;
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold text-lg">A</div>
                <span class="text-xl font-bold text-white">Appi<span class="text-indigo-400">Tutors</span></span>
            </div>
            <p class="text-sm">© <?= date('Y') ?> AppiTutors Ltd. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
