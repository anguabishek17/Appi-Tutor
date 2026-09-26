<?php
declare(strict_types=1);

/**
 * AppiTutors Public Front Controller / Router
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;

$user = AuthService::user();

use App\Services\UIHelper;

$user = AuthService::user();
$db = Connection::getInstance();

// Fetch featured tutors (Approved only)
$featuredStmt = $db->query('
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.hourly_rate,
        tp.experience_years,
        tp.teaching_mode
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
    <meta name="description" content="Find the right tutor. Build confidence. Achieve more. Connect with verified, DBS-checked UK tutors across Primary, 11+, GCSE, and A-Level.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <?= UIHelper::renderPublicHeader($user, 'home') ?>

    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-28 bg-gradient-to-b from-indigo-50/60 via-white to-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 mb-6 border border-indigo-200 shadow-2xs">
                        <span>🇬🇧</span>
                        <span>UK Curricula Specialists • DBS Verified</span>
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-extrabold text-slate-900 tracking-tight leading-tight">
                        Find the right tutor. <br class="hidden sm:inline" />
                        <span class="text-indigo-600">Build confidence.</span> Achieve more.
                    </h1>
                    <p class="mt-6 text-lg sm:text-xl text-slate-600 leading-relaxed max-w-2xl mx-auto">
                        Personalised 1-to-1 and group tuition across GCSE, A-Level, and 11-Plus. Connect with manually vetted subject specialists for online and in-person sessions.
                    </p>
                    
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/tutors.php" class="px-8 py-3.5 text-base font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-lg shadow-indigo-200 transition text-center focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Find a Tutor
                        </a>
                        <a href="/register.php" class="px-8 py-3.5 text-base font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl transition text-center shadow-xs">
                            Become a Tutor
                        </a>
                    </div>

                    <!-- Safeguarding & Trust Pill -->
                    <div class="mt-12 flex flex-wrap items-center justify-center gap-6 text-xs font-semibold text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600 text-sm">✓</span>
                            <span>Enhanced DBS Verified</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600 text-sm">✓</span>
                            <span>Degree & Credential Checks</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-600 text-sm">✓</span>
                            <span>Transparent Lesson Progress Notes</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-16 sm:py-24 bg-white border-y border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">How AppiTutors Works</h2>
                    <h3 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Structured tuition in 4 simple steps</h3>
                    <p class="mt-3 text-slate-600 text-sm">Our platform ensures transparent booking and measurable academic progress.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Step 1 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 text-center relative group hover:border-indigo-300 transition">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white font-extrabold text-lg flex items-center justify-center mx-auto mb-4 shadow-md shadow-indigo-100">
                            1
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-1">Find a Tutor</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Search by subject, UK curriculum (GCSE, A-Level), delivery mode, and hourly rate.</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 text-center relative group hover:border-indigo-300 transition">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white font-extrabold text-lg flex items-center justify-center mx-auto mb-4 shadow-md shadow-indigo-100">
                            2
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-1">Choose a Lesson</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Review tutor qualifications, bio, and choose an available teaching slot from their calendar.</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 text-center relative group hover:border-indigo-300 transition">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white font-extrabold text-lg flex items-center justify-center mx-auto mb-4 shadow-md shadow-indigo-100">
                            3
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-1">Book a Session</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Link the session directly to your child's profile and provide specific target topics.</p>
                    </div>

                    <!-- Step 4 -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200 text-center relative group hover:border-indigo-300 transition">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white font-extrabold text-lg flex items-center justify-center mx-auto mb-4 shadow-md shadow-indigo-100">
                            4
                        </div>
                        <h4 class="text-base font-bold text-slate-900 mb-1">Track Progress</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Access comprehensive lesson notes, homework tasks, and progress ratings after each session.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Subjects & Curricula Section -->
        <section class="py-16 sm:py-24 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">UK Curricula</h2>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Specialised Subject Tuition</h3>
                    </div>
                    <a href="/tutors.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Explore All Subjects</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($curricula as $c): ?>
                        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs hover:shadow-md transition">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2.5 py-1 rounded-md text-[11px] font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    <?= htmlspecialchars($c['code']) ?>
                                </span>
                                <span class="text-xs text-slate-500 font-semibold"><?= (int)$c['subject_count'] ?> Subjects</span>
                            </div>
                            <h4 class="text-lg font-bold text-slate-900 mb-1"><?= htmlspecialchars($c['name']) ?></h4>
                            <p class="text-xs text-slate-600 mt-2">Specialist tutoring tailored to exam board specifications including Edexcel, AQA, and OCR.</p>
                            <a href="/tutors.php" class="mt-4 inline-block text-xs font-bold text-indigo-600 hover:underline">Find Tutors in <?= htmlspecialchars($c['code']) ?> &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Featured Tutors Section -->
        <section class="py-16 sm:py-24 bg-white border-y border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">Verified Educators</h2>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Meet Our Approved Tutors</h3>
                    </div>
                    <a href="/tutors.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Browse All Tutors</span>
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
                            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs hover:shadow-md transition flex flex-col justify-between">
                                <div>
                                    <div class="flex items-start gap-4 mb-4">
                                        <?php if (!empty($ft['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($ft['avatar_url']) ?>" alt="<?= htmlspecialchars($ft['first_name']) ?>" class="w-14 h-14 rounded-xl object-cover border border-slate-200">
                                        <?php else: ?>
                                            <div class="w-14 h-14 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center text-base shadow-sm">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h4 class="font-bold text-slate-900 text-base"><?= htmlspecialchars($ft['first_name'] . ' ' . $ft['last_name'][0] . '.') ?></h4>
                                            <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 mt-1">
                                                <span>✓</span> Verified DBS
                                            </div>
                                        </div>
                                    </div>

                                    <p class="text-xs text-slate-700 font-semibold mb-2 line-clamp-2"><?= htmlspecialchars($ft['headline'] ?: 'Academic Specialist') ?></p>
                                    
                                    <div class="flex items-center gap-3 text-xs text-slate-500 font-medium my-3">
                                        <span>🎓 <?= (int)$ft['experience_years'] ?> yrs exp</span>
                                        <span>•</span>
                                        <span>📍 <?= htmlspecialchars($modeLabel) ?></span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-slate-100 flex items-center justify-between mt-2">
                                    <div>
                                        <span class="text-lg font-extrabold text-indigo-600">£<?= number_format((float)$ft['hourly_rate'], 2) ?></span>
                                        <span class="text-[11px] text-slate-500 font-medium">/hr</span>
                                    </div>
                                    <a href="/tutor.php?id=<?= (int)$ft['tutor_profile_id'] ?>" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl transition">
                                        View Profile &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Why AppiTutors Section -->
        <section class="py-16 sm:py-24 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">Platform Standards</h2>
                    <h3 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Built for trust, safety, and results</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg mb-4">🛡️</div>
                        <h4 class="font-bold text-slate-900 text-base mb-2">Manager-Vetted Approvals</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Every tutor must submit identification and DBS credentials reviewed by our UK operations team before being permitted to teach.</p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg mb-4">👨‍👩‍👧</div>
                        <h4 class="font-bold text-slate-900 text-base mb-2">Child Profile Management</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Parents manage distinct profiles for each child, allowing personalised curriculum goals and lesson history tracking in one account.</p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg mb-4">📝</div>
                        <h4 class="font-bold text-slate-900 text-base mb-2">Lesson Progress Notes</h4>
                        <p class="text-xs text-slate-600 leading-relaxed">Tutors provide detailed feedback, topics covered, and homework tasks following each confirmed lesson.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA Banner -->
        <section class="py-16 sm:py-20 bg-indigo-600 text-white">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
                <h3 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Ready to find the right tutor?</h3>
                <p class="text-indigo-100 text-base max-w-xl mx-auto">Discover qualified UK tutors across GCSE, A-Level, and 11-Plus subjects today.</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center pt-2">
                    <a href="/tutors.php" class="px-8 py-3.5 bg-white text-indigo-600 hover:bg-indigo-50 font-bold text-base rounded-xl shadow-lg transition">
                        Find a Tutor
                    </a>
                    <a href="/register.php" class="px-8 py-3.5 bg-indigo-700 hover:bg-indigo-800 text-white font-bold text-base rounded-xl border border-indigo-500 transition">
                        Get Started
                    </a>
                </div>
            </div>
        </section>
    </main>

    <?= UIHelper::renderPublicFooter() ?>

</body>
</html>
