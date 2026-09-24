<?php
declare(strict_types=1);

/**
 * AppiTutors Public Front Controller / Router
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;

$user = AuthService::user();

// Render landing page
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AppiTutors | Premium UK Tutoring & Academic Excellence</title>
    <meta name="description" content="Connect with top-rated, DBS-checked UK tutors for KS1-KS3, GCSE, 11-Plus, and A-Level success.">
    <!-- Tailwind CSS CDN for instant styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between text-slate-800">

    <!-- Navigation Header -->
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-xl shadow-md shadow-indigo-200">
                    A
                </div>
                <span class="text-2xl font-extrabold tracking-tight text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
            </a>

            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                <a href="/tutors.php" class="hover:text-indigo-600 transition">Find a Tutor</a>
                <a href="/subjects.php" class="hover:text-indigo-600 transition">Subjects</a>
                <a href="/how-it-works.php" class="hover:text-indigo-600 transition">How It Works</a>
                <a href="/become-a-tutor.php" class="hover:text-indigo-600 transition">Become a Tutor</a>
                <a href="/blog.php" class="hover:text-indigo-600 transition">Study Blog</a>
            </nav>

            <div class="flex items-center gap-4">
                <?php if ($user): ?>
                    <a href="/dashboard.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition">
                        Dashboard (<?= htmlspecialchars($user['first_name']) ?>)
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="text-sm font-semibold text-slate-700 hover:text-indigo-600 transition px-3 py-2">Log In</a>
                    <a href="/register.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition">
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="flex-grow">
        <section class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-28 bg-gradient-to-b from-indigo-50/50 via-white to-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 mb-6">
                        🇬🇧 Trusted by parents & students across the UK
                    </span>
                    <h1 class="text-4xl sm:text-6xl font-extrabold text-slate-900 tracking-tight leading-tight">
                        Expert UK Tutoring tailored to your child's success.
                    </h1>
                    <p class="mt-6 text-lg sm:text-xl text-slate-600 leading-relaxed">
                        Verified, DBS-checked tutors for Primary, 11-Plus, GCSE, and A-Level. Discover subject specialists, book lessons, and track progress effortlessly.
                    </p>
                    
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/tutors.php" class="px-8 py-3.5 text-base font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-lg shadow-indigo-200 transition text-center">
                            Find Your Tutor
                        </a>
                        <a href="/become-a-tutor.php" class="px-8 py-3.5 text-base font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 rounded-xl transition text-center">
                            Apply as a Tutor
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold text-lg">
                    A
                </div>
                <span class="text-xl font-bold text-white">Appi<span class="text-indigo-400">Tutors</span></span>
            </div>
            <p class="text-sm">© <?= date('Y') ?> AppiTutors Ltd. All rights reserved. Registered UK Tutoring Platform.</p>
        </div>
    </footer>

</body>
</html>
