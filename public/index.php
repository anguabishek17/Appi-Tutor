<?php
declare(strict_types=1);

/**
 * AppiTutors Public Homepage
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;
use App\Services\UIHelper;

$user = AuthService::user();
$db = Connection::getInstance();

// Fetch primary featured tutor (James Carter or top approved tutor) for Hero Card
$heroTutorStmt = $db->query('
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.hourly_rate,
        tp.experience_years,
        tp.teaching_mode,
        tp.qualifications,
        tp.dbs_verified_at
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = "APPROVED" AND u.status = "ACTIVE"
    ORDER BY tp.is_featured DESC, tp.id ASC
    LIMIT 1
');
$heroTutor = $heroTutorStmt->fetch();

$heroSubjects = [];
$heroSlots = [];
if ($heroTutor) {
    // Fetch top 3 subjects for hero tutor
    $hSubjStmt = $db->prepare('
        SELECT s.name, c.code AS curriculum_code
        FROM tutor_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN curricula c ON s.curriculum_id = c.id
        WHERE ts.tutor_profile_id = :tpid
        ORDER BY c.id ASC, s.name ASC
        LIMIT 3
    ');
    $hSubjStmt->execute([':tpid' => $heroTutor['tutor_profile_id']]);
    $heroSubjects = $hSubjStmt->fetchAll();

    // Fetch next upcoming available slot
    $hSlotStmt = $db->prepare('
        SELECT start_time, end_time, delivery_mode
        FROM tutor_availability
        WHERE tutor_profile_id = :tpid 
          AND status = "AVAILABLE" 
          AND start_time > NOW()
          AND booked_count < max_capacity
        ORDER BY start_time ASC
        LIMIT 1
    ');
    $hSlotStmt->execute([':tpid' => $heroTutor['tutor_profile_id']]);
    $heroSlots = $hSlotStmt->fetch();
}

// Fetch all featured tutors for "Meet our tutors" section
$featuredStmt = $db->query('
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.hourly_rate,
        tp.experience_years,
        tp.teaching_mode,
        tp.dbs_verified_at
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = "APPROVED" AND u.status = "ACTIVE"
    ORDER BY tp.is_featured DESC, tp.id ASC
    LIMIT 3
');
$featuredTutors = $featuredStmt->fetchAll();

// Popular Curricula / Subject list from DB
$curriculaStmt = $db->query('
    SELECT c.name, c.code, COUNT(s.id) AS subject_count 
    FROM curricula c 
    LEFT JOIN subjects s ON c.id = s.curriculum_id 
    GROUP BY c.id 
    ORDER BY c.id ASC
');
$curricula = $curriculaStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AppiTutors | Verified UK Tutoring & Academic Excellence</title>
    <meta name="description" content="Personalised 1-to-1 and group tuition from trusted UK subject specialists. DBS verified, transparent progress tracking, online and in-person.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .hero-glow {
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.08) 0%, rgba(248, 250, 252, 0) 70%);
        }
        @media (prefers-reduced-motion: reduce) {
            .transition-all, .transition { transition: none !important; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-600 selection:text-white bg-slate-50">

    <?= UIHelper::renderPublicHeader($user, 'home') ?>

    <main class="flex-grow">
        <!-- Hero Section: Premium Two-Column Desktop Layout -->
        <section class="relative overflow-hidden pt-8 pb-16 lg:pt-14 lg:pb-22 hero-glow border-b border-slate-200/70">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-center">
                    
                    <!-- Left Column: Copy & CTAs -->
                    <div class="lg:col-span-7 space-y-6 text-left">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200/80 shadow-2xs">
                            <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                            <span>UK Tutoring • DBS Verified Specialists</span>
                        </div>

                        <h1 class="text-3xl sm:text-5xl lg:text-[54px] font-extrabold text-slate-900 tracking-tight leading-[1.12]">
                            Find the right tutor.<br>
                            <span class="text-indigo-600">Build confidence.</span><br>
                            Achieve more.
                        </h1>

                        <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-xl">
                            Personalised 1-to-1 and group tuition from trusted UK subject specialists across GCSE, A-Level, and 11-Plus, online and in person.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-3.5 pt-1">
                            <a href="/tutors.php" class="px-7 py-3.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition text-center focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                Find a Tutor
                            </a>
                            <a href="/#subjects" class="px-7 py-3.5 text-sm font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 rounded-xl transition text-center shadow-xs">
                                Explore Subjects
                            </a>
                        </div>

                        <!-- Micro Trust Highlights under CTAs -->
                        <div class="pt-3 border-t border-slate-200/60 flex flex-wrap items-center gap-y-2 gap-x-5 text-xs font-semibold text-slate-600">
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>DBS checked tutors</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Flexible online & in-person lessons</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Progress you can track</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Visual Product Tutor Discovery Preview -->
                    <div class="lg:col-span-5 relative">
                        <?php if ($heroTutor): 
                            $hInitials = strtoupper(substr($heroTutor['first_name'], 0, 1) . substr($heroTutor['last_name'], 0, 1));
                            $hMode = $heroTutor['teaching_mode'] === 'ONLINE' ? 'Online' : ($heroTutor['teaching_mode'] === 'IN_PERSON' ? 'In-Person' : 'Online & In-person');
                        ?>
                            <div class="relative bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xl shadow-slate-200/60 space-y-5">
                                <!-- Top Pill -->
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Featured UK Specialist</span>
                                    <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                        <span>✓</span> Verified DBS
                                    </div>
                                </div>

                                <!-- Tutor Info -->
                                <div class="flex items-start gap-4">
                                    <?php if (!empty($heroTutor['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($heroTutor['avatar_url']) ?>" alt="<?= htmlspecialchars($heroTutor['first_name'] . ' ' . $heroTutor['last_name']) ?>" class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shrink-0">
                                    <?php else: ?>
                                        <div class="w-14 h-14 rounded-2xl bg-indigo-600 text-white font-extrabold flex items-center justify-center text-lg shrink-0 shadow-sm">
                                            <?= htmlspecialchars($hInitials) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-lg font-extrabold text-slate-900"><?= htmlspecialchars($heroTutor['first_name'] . ' ' . $heroTutor['last_name']) ?></h3>
                                        <p class="text-xs text-slate-600 font-medium leading-snug mt-0.5"><?= htmlspecialchars($heroTutor['headline'] ?: 'Academic Specialist') ?></p>
                                    </div>
                                </div>

                                <!-- Subjects Chips -->
                                <div>
                                    <div class="text-[11px] font-semibold text-slate-500 mb-1.5 uppercase tracking-wider">Tuition Specialisms</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php if (!empty($heroSubjects)): ?>
                                            <?php foreach ($heroSubjects as $hs): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                    <span class="font-bold text-[10px] text-indigo-500"><?= htmlspecialchars($hs['curriculum_code']) ?></span>
                                                    <span><?= htmlspecialchars($hs['name']) ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-600">GCSE & A-Level</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Availability & Booking Preview -->
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between text-xs">
                                    <div>
                                        <span class="text-slate-500 font-medium block">Next Available Slot</span>
                                        <?php if (!empty($heroSlots)): ?>
                                            <span class="font-bold text-slate-800"><?= UIHelper::formatDateTime($heroSlots['start_time']) ?></span>
                                        <?php else: ?>
                                            <span class="font-bold text-slate-800">Weekly Scheduled Slots</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-base font-extrabold text-indigo-600"><?= UIHelper::formatCurrency($heroTutor['hourly_rate']) ?></span>
                                        <span class="text-[11px] text-slate-500 block">/ hour</span>
                                    </div>
                                </div>

                                <!-- CTA Action -->
                                <div class="pt-1 flex items-center justify-between gap-3">
                                    <span class="text-xs text-slate-500 font-medium">📍 <?= htmlspecialchars($hMode) ?></span>
                                    <a href="/tutor.php?id=<?= (int)$heroTutor['tutor_profile_id'] ?>" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition inline-flex items-center gap-1">
                                        <span>View Tutor</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </section>

        <!-- Compact Trust Strip -->
        <section class="bg-white border-b border-slate-200 py-4.5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 text-xs font-semibold text-slate-700">
                    <span class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">Trusted Tutoring Experience</span>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="text-indigo-600 font-bold">✓</span>
                            <span>DBS Verification</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-indigo-600 font-bold">✓</span>
                            <span>Qualified Subject Specialists</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-indigo-600 font-bold">✓</span>
                            <span>Online & In-Person</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-indigo-600 font-bold">✓</span>
                            <span>Parent-Managed Learning</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-indigo-600 font-bold">✓</span>
                            <span>Lesson Progress Tracking</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section: 4 Connected Steps -->
        <section id="how-it-works" class="py-16 lg:py-22 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">How It Works</h2>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Simple from the first search to the next lesson</h3>
                    <p class="mt-2.5 text-slate-600 text-sm">Our structured platform ensures transparent booking and measurable academic progress.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">
                    <!-- Step 1 -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-2xs hover:border-indigo-200 hover:shadow-sm transition flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-sm flex items-center justify-center mb-4">
                                01
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1.5">Find a Tutor</h4>
                            <p class="text-xs text-slate-600 leading-relaxed">Explore approved tutors by subject, UK level (GCSE, A-Level) and teaching mode.</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-2xs hover:border-indigo-200 hover:shadow-sm transition flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-sm flex items-center justify-center mb-4">
                                02
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1.5">Choose a Lesson</h4>
                            <p class="text-xs text-slate-600 leading-relaxed">View real availability and choose a suitable slot directly from their calendar.</p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-2xs hover:border-indigo-200 hover:shadow-sm transition flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-sm flex items-center justify-center mb-4">
                                03
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1.5">Book With Confidence</h4>
                            <p class="text-xs text-slate-600 leading-relaxed">Select your child's profile and request the lesson with target learning goals.</p>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/90 shadow-2xs hover:border-indigo-200 hover:shadow-sm transition flex flex-col justify-between">
                        <div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold text-sm flex items-center justify-center mb-4">
                                04
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1.5">Track Progress</h4>
                            <p class="text-xs text-slate-600 leading-relaxed">Review attendance, lesson notes and learning progress after each session.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Subjects & Curricula Section -->
        <section id="subjects" class="py-16 lg:py-22 bg-white border-y border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-1.5">Curricula & Stages</h2>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Tuition for every stage of learning</h3>
                    </div>
                    <a href="/tutors.php" class="text-xs sm:text-sm font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Explore all subjects</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($curricula as $c): ?>
                        <div class="bg-slate-50/60 rounded-2xl border border-slate-200/80 p-5.5 hover:border-indigo-300 hover:bg-white hover:shadow-xs transition">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2.5 py-1 rounded-md text-[11px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    <?= htmlspecialchars($c['code']) ?>
                                </span>
                                <span class="text-xs text-slate-500 font-semibold"><?= (int)$c['subject_count'] ?> Subjects</span>
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1"><?= htmlspecialchars($c['name']) ?></h4>
                            <p class="text-xs text-slate-600 leading-relaxed">Tailored tuition covering UK national curriculum and major exam board specifications.</p>
                            <a href="/tutors.php" class="mt-3.5 inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700">
                                <span>Find tutors</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Featured Tutors Section -->
        <section class="py-16 lg:py-22 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-1.5">Approved Specialists</h2>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Meet our tutors</h3>
                        <p class="text-xs text-slate-500 mt-1">Find subject specialists who fit your child's goals, schedule and learning style.</p>
                    </div>
                    <a href="/tutors.php" class="text-xs sm:text-sm font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Browse all tutors</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php if (empty($featuredTutors)): ?>
                        <div class="col-span-full text-center py-12 text-slate-400 text-sm">No featured tutors available right now.</div>
                    <?php else: ?>
                        <?php foreach ($featuredTutors as $ft): 
                            $initials = strtoupper(substr($ft['first_name'], 0, 1) . substr($ft['last_name'], 0, 1));
                            $modeLabel = $ft['teaching_mode'] === 'ONLINE' ? 'Online' : ($ft['teaching_mode'] === 'IN_PERSON' ? 'In-Person' : 'Online & In-Person');
                        ?>
                            <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-2xs hover:border-indigo-300 hover:shadow-md transition flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start gap-3.5 mb-4">
                                        <?php if (!empty($ft['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($ft['avatar_url']) ?>" alt="<?= htmlspecialchars($ft['first_name']) ?>" class="w-13 h-13 rounded-xl object-cover border border-slate-200 shrink-0">
                                        <?php else: ?>
                                            <div class="w-13 h-13 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center text-sm shadow-sm shrink-0">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-slate-900 text-base truncate"><?= htmlspecialchars($ft['first_name'] . ' ' . $ft['last_name'][0] . '.') ?></h4>
                                            <?php if (!empty($ft['dbs_verified_at'])): ?>
                                                <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 mt-0.5">
                                                    <span>✓</span> Verified DBS
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <p class="text-xs text-slate-700 font-semibold mb-3 line-clamp-2 leading-relaxed"><?= htmlspecialchars($ft['headline'] ?: 'Academic Specialist') ?></p>
                                    
                                    <div class="flex items-center gap-2.5 text-xs text-slate-500 font-medium mb-4">
                                        <span>🎓 <?= (int)$ft['experience_years'] ?> yrs exp</span>
                                        <span>•</span>
                                        <span>📍 <?= htmlspecialchars($modeLabel) ?></span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <div>
                                        <span class="text-base font-extrabold text-indigo-600">£<?= number_format((float)$ft['hourly_rate'], 2) ?></span>
                                        <span class="text-[11px] text-slate-500 font-medium">/hr</span>
                                    </div>
                                    <a href="/tutor.php?id=<?= (int)$ft['tutor_profile_id'] ?>" class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition">
                                        View Profile &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Parent Value Section: More Than A Lesson -->
        <section class="py-16 lg:py-22 bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-1.5">For Parents</h2>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">More than a lesson</h3>
                    <p class="text-xs sm:text-sm text-slate-600 mt-2">Tools designed to keep parents informed, organized, and confident in their child's academic journey.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base mb-3.5">
                            👨‍👩‍👧
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm mb-1.5">Child Profiles</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Manage individual learning profiles, year groups, and target subjects for each child in one family account.</p>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base mb-3.5">
                            📅
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm mb-1.5">Flexible Availability</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">View real-time calendar slots and select tuition hours that match your family's weekly schedule.</p>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base mb-3.5">
                            ⚡
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm mb-1.5">Booking Management</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Easily confirm upcoming lessons, propose reschedule times, or cancel with clear 24-hour policies.</p>
                    </div>

                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base mb-3.5">
                            📝
                        </div>
                        <h4 class="font-bold text-slate-900 text-sm mb-1.5">Lesson Notes & Progress</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Access structured lesson notes, topics mastered, homework, and tutor feedback after every session.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tutor CTA Section -->
        <section class="py-16 bg-gradient-to-r from-indigo-900 to-slate-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                    <div class="max-w-xl space-y-2 text-left">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Join Our Teaching Network</span>
                        <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Ready to teach on your terms?</h3>
                        <p class="text-slate-300 text-xs sm:text-sm leading-relaxed">
                            Share your expertise, set your own hourly rates, manage your availability calendar, and connect with motivated UK learners.
                        </p>
                    </div>
                    <div>
                        <a href="/register.php" class="px-7 py-3.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm rounded-xl shadow-lg transition inline-flex items-center gap-2">
                            <span>Become a Tutor</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA Banner -->
        <section class="py-16 sm:py-20 bg-slate-50 text-center">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <h3 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Find the right support for the next step.</h3>
                <p class="text-slate-600 text-sm sm:text-base max-w-xl mx-auto">
                    Connect with vetted subject specialists across Primary, 11+, GCSE, and A-Level curricula today.
                </p>
                <div class="flex flex-col sm:flex-row gap-3.5 justify-center pt-2">
                    <a href="/tutors.php" class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md shadow-indigo-600/20 transition">
                        Find a Tutor
                    </a>
                    <a href="/register.php" class="px-8 py-3.5 bg-white hover:bg-slate-50 text-slate-700 font-bold text-sm rounded-xl border border-slate-200/90 shadow-xs transition">
                        Get Started
                    </a>
                </div>
            </div>
        </section>
    </main>

    <?= UIHelper::renderPublicFooter() ?>

</body>
</html>

