<?php
declare(strict_types=1);

/**
 * Manager Portal: Main Operations Dashboard
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;

// Require MANAGER role
$user = AuthMiddleware::requireRole(['MANAGER']);

$db = Connection::getInstance();

// Count stats for quick operational review
$pendingCount = (int)$db->query('SELECT COUNT(*) FROM tutor_profiles WHERE approval_status = "PENDING"')->fetchColumn();
$approvedCount = (int)$db->query('SELECT COUNT(*) FROM tutor_profiles WHERE approval_status = "APPROVED"')->fetchColumn();
$usersCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
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
<body class="min-h-full flex flex-col justify-between text-slate-800">

    <header class="bg-slate-900 text-white border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/manager/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center text-white font-bold">
                        A
                    </div>
                    <span class="text-xl font-bold">Appi<span class="text-indigo-400">Tutors</span> <span class="text-xs ml-2 px-2 py-0.5 rounded bg-indigo-900 text-indigo-200 font-mono">MANAGER</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-4 text-sm font-medium text-slate-300">
                    <a href="/manager/dashboard.php" class="text-white bg-slate-800 px-3 py-1.5 rounded-lg">Overview</a>
                    <a href="/manager/tutors.php" class="hover:text-white transition px-3 py-1.5 rounded-lg flex items-center gap-2">
                        Tutor Approvals
                        <?php if ($pendingCount > 0): ?>
                            <span class="bg-amber-500 text-slate-900 text-xs px-2 py-0.5 rounded-full font-bold"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-300"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-400 hover:text-rose-300">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Operations Overview</h1>
                <p class="text-slate-600 mt-1">Platform management and tutor verification hub.</p>
            </div>
            <a href="/manager/tutors.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition">
                <span>Review Pending Tutors</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="bg-amber-400 text-slate-900 text-xs px-2 py-0.5 rounded-full"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending Tutor Applications</div>
                <div class="mt-2 text-3xl font-extrabold <?= $pendingCount > 0 ? 'text-amber-600' : 'text-slate-900' ?>">
                    <?= $pendingCount ?>
                </div>
                <p class="text-xs text-slate-500 mt-2">Requires DBS and identity verification review.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Approved Tutors</div>
                <div class="mt-2 text-3xl font-extrabold text-emerald-600"><?= $approvedCount ?></div>
                <p class="text-xs text-slate-500 mt-2">Active educators available for bookings.</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Registered Accounts</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-900"><?= $usersCount ?></div>
                <p class="text-xs text-slate-500 mt-2">Includes tutors, parents, and managers.</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Quick Management Links</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="/manager/tutors.php" class="p-4 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/30 transition flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-900">Tutor Approval Queue</div>
                        <div class="text-xs text-slate-500 mt-0.5">Approve, reject, or inspect pending tutor submissions</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-sm">Open &rarr;</span>
                </a>
                <a href="/" class="p-4 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/30 transition flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-900">AppiTutors Website</div>
                        <div class="text-xs text-slate-500 mt-0.5">Preview the public marketplace and homepage</div>
                    </div>
                    <span class="text-indigo-600 font-bold text-sm">Visit &rarr;</span>
                </a>
            </div>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Platform Management.
    </footer>
</body>
</html>
