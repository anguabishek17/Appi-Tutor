<?php
declare(strict_types=1);

/**
 * Manager Portal: Tutor Approvals Queue & Management
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

// Require MANAGER role
$user = AuthMiddleware::requireRole(['MANAGER']);

$db = Connection::getInstance();
$csrfToken = CsrfService::getToken();

// Fetch Pending Tutors
$pendingStmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.user_id,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.approval_status,
        tp.created_at,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.avatar_url
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status = "PENDING"
    ORDER BY tp.created_at DESC
');
$pendingStmt->execute();
$pendingTutors = $pendingStmt->fetchAll();

// Fetch Processed Tutors (Approved / Rejected)
$processedStmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.user_id,
        tp.hourly_rate,
        tp.approval_status,
        tp.approval_notes,
        tp.updated_at,
        u.first_name,
        u.last_name,
        u.email
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.approval_status IN ("APPROVED", "REJECTED")
    ORDER BY tp.updated_at DESC
    LIMIT 20
');
$processedStmt->execute();
$processedTutors = $processedStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Approvals | Manager Portal</title>
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
                    <a href="/manager/dashboard.php" class="hover:text-white transition px-3 py-1.5 rounded-lg">Overview</a>
                    <a href="/manager/tutors.php" class="text-white bg-slate-800 px-3 py-1.5 rounded-lg flex items-center gap-2">
                        Tutor Approvals
                        <span id="pendingBadge" class="bg-amber-500 text-slate-900 text-xs px-2 py-0.5 rounded-full font-bold <?= count($pendingTutors) === 0 ? 'hidden' : '' ?>">
                            <?= count($pendingTutors) ?>
                        </span>
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
        <div class="mb-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Tutor Verification & Approval Queue</h1>
            <p class="text-slate-600 mt-1">Review tutor credentials, verify DBS status, and grant platform access.</p>
        </div>

        <div id="toastNotification" class="hidden mb-6 p-4 rounded-xl text-sm"></div>

        <!-- Pending Applications Section -->
        <section class="mb-12">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    Pending Applications
                    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-2.5 py-0.5 rounded-full">
                        <?= count($pendingTutors) ?> awaiting review
                    </span>
                </h2>
            </div>

            <?php if (empty($pendingTutors)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">All caught up!</h3>
                    <p class="text-sm text-slate-500 mt-1">There are no pending tutor applications awaiting review.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6">
                    <?php foreach ($pendingTutors as $tutor): ?>
                        <div id="tutor-card-<?= (int)$tutor['tutor_profile_id'] ?>" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-sm">
                                        <?= strtoupper(substr($tutor['first_name'], 0, 1) . substr($tutor['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">
                                            <?= htmlspecialchars($tutor['first_name'] . ' ' . $tutor['last_name']) ?>
                                        </h3>
                                        <div class="text-xs text-slate-500 flex items-center gap-3">
                                            <span><?= htmlspecialchars($tutor['email']) ?></span>
                                            <span>•</span>
                                            <span>Registered: <?= date('d M Y, H:i', strtotime($tutor['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm text-slate-600">
                                    <span class="font-medium text-slate-700">Initial Rate:</span> £<?= number_format((float)$tutor['hourly_rate'], 2) ?>/hr
                                    <?php if (!empty($tutor['experience_years'])): ?>
                                        <span class="ml-3">• <?= (int)$tutor['experience_years'] ?> yrs experience</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 self-end md:self-center">
                                <button onclick="rejectTutor(<?= (int)$tutor['tutor_profile_id'] ?>)"
                                        class="px-4 py-2 border border-rose-200 text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-xl font-semibold text-sm transition">
                                    Reject
                                </button>
                                <button onclick="approveTutor(<?= (int)$tutor['tutor_profile_id'] ?>)"
                                        class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm shadow-sm transition">
                                    Approve Tutor
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Processed Tutors History -->
        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-4">Recent Review History</h2>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider text-left">
                            <tr>
                                <th class="py-3.5 px-6 font-semibold">Tutor</th>
                                <th class="py-3.5 px-6 font-semibold">Status</th>
                                <th class="py-3.5 px-6 font-semibold">Processed Date</th>
                                <th class="py-3.5 px-6 font-semibold">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <?php if (empty($processedTutors)): ?>
                                <tr>
                                    <td colspan="4" class="py-6 px-6 text-center text-slate-400 text-xs">No historical reviews recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($processedTutors as $p): ?>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-slate-900">
                                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                                            <div class="text-xs text-slate-400 font-normal"><?= htmlspecialchars($p['email']) ?></div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php if ($p['approval_status'] === 'APPROVED'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                    APPROVED
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                                                    REJECTED
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-slate-500 text-xs">
                                            <?= date('d M Y, H:i', strtotime($p['updated_at'])) ?>
                                        </td>
                                        <td class="py-4 px-6 text-slate-500 text-xs">
                                            <?= htmlspecialchars($p['approval_notes'] ?? '—') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Platform Management.
    </footer>

    <script>
        const csrfToken = "<?= htmlspecialchars($csrfToken) ?>";
        const toast = document.getElementById('toastNotification');

        function showToast(msg, isSuccess = true) {
            toast.className = isSuccess 
                ? 'mb-6 p-4 rounded-xl text-sm bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-sm bg-rose-50 text-rose-800 border border-rose-200';
            toast.innerHTML = msg;
            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.add('hidden'); }, 4000);
        }

        async function approveTutor(id) {
            if (!confirm('Are you sure you want to approve this tutor?')) return;
            try {
                const res = await fetch(`/api/manager/tutors/approve.php?id=${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Tutor approved successfully!');
                    const card = document.getElementById(`tutor-card-${id}`);
                    if (card) card.remove();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Approval failed', false);
                }
            } catch (err) {
                showToast(err.message, false);
            }
        }

        async function rejectTutor(id) {
            const reason = prompt('Please enter a reason for rejection (optional):', 'Qualifications could not be verified');
            if (reason === null) return;

            try {
                const res = await fetch(`/api/manager/tutors/reject.php?id=${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ reason: reason })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Tutor rejected.');
                    const card = document.getElementById(`tutor-card-${id}`);
                    if (card) card.remove();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Rejection failed', false);
                }
            } catch (err) {
                showToast(err.message, false);
            }
        }
    </script>
</body>
</html>
