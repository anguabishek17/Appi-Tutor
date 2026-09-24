<?php
declare(strict_types=1);

/**
 * Tutor Portal: Pending Approval Notice
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Auth\AuthService;

$user = AuthMiddleware::requireRole(['TUTOR']);

// If already approved, redirect to active dashboard
$refreshedUser = AuthService::refreshSession() ?? $user;
if (($refreshedUser['tutor_approval_status'] ?? '') === 'APPROVED') {
    header('Location: /tutor/dashboard.php');
    exit;
}

$isRejected = ($refreshedUser['tutor_approval_status'] ?? '') === 'REJECTED';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status | AppiTutors Tutor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">
                    A
                </div>
                <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span> <span class="text-xs ml-2 px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Tutor Portal</span></span>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-red-600 hover:text-red-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200/80 text-center">

            <?php if ($isRejected): ?>
                <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-900">Application Not Approved</h2>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    Thank you for your interest in AppiTutors. Unfortunately, our management team could not approve your tutor application at this time.
                </p>
                <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 text-left">
                    If you believe this is a misunderstanding, please reach out to <strong class="text-indigo-600">managers@appitutors.co.uk</strong>.
                </div>
            <?php else: ?>
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-900">Application Under Review</h2>
                <div class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                    Status: PENDING_APPROVAL
                </div>
                <p class="mt-4 text-sm text-slate-600 leading-relaxed">
                    Welcome to AppiTutors, <strong><?= htmlspecialchars($user['first_name']) ?></strong>! Your tutor profile is currently under review by our operations team.
                </p>
                <div class="mt-6 p-4 rounded-xl bg-indigo-50/50 border border-indigo-100 text-xs text-indigo-900 text-left space-y-2">
                    <p class="font-semibold">Next steps:</p>
                    <ul class="list-disc list-inside space-y-1 text-slate-600">
                        <li>Our team reviews your qualifications and DBS credentials.</li>
                        <li>You'll receive an email notification once approved.</li>
                        <li>Upon approval, your calendar, subject management, and student bookings will unlock.</li>
                    </ul>
                </div>
                <div class="mt-6">
                    <a href="/tutor/pending.php" class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                        <svg class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Refresh status
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500">
        © <?= date('Y') ?> AppiTutors Ltd. All rights reserved.
    </footer>
</body>
</html>
