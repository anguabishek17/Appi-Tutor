<?php
declare(strict_types=1);

/**
 * Manager Portal: Tutor Approvals Queue & Management
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

use App\Services\UIHelper;

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
<body class="min-h-full flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <?= UIHelper::renderHeader($user, 'Tutor Approvals') ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Tutor Verification & Approval Queue</h1>
            <p class="text-slate-600 mt-1 text-sm">Review tutor credentials, verify qualifications and DBS status, and grant teaching access.</p>
        </div>

        <div id="toastNotification" class="hidden mb-6 p-4 rounded-2xl text-sm font-medium"></div>

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
                                            <?= UIHelper::renderTutorApprovalBadge($p['approval_status']) ?>
                                        </td>
                                        <td class="py-4 px-6 text-slate-500 text-xs font-medium">
                                            <?= UIHelper::formatDateTime($p['updated_at']) ?>
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

    <!-- Reject Modal with Reason Input -->
    <div id="rejectTutorModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in duration-150">
            <h3 class="text-lg font-bold text-slate-900 mb-1">Reject Tutor Application</h3>
            <p class="text-xs text-slate-500 mb-4">Please specify a reason or feedback for rejecting this tutor application.</p>
            <input type="hidden" id="rejectTutorId">
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reason for Rejection *</label>
                <textarea id="rejectReasonInput" rows="3" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none" placeholder="e.g. Qualifications could not be verified with university records."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">Cancel</button>
                <button type="button" onclick="submitRejectTutor()" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-md">Confirm Rejection</button>
            </div>
        </div>
    </div>

    <?= UIHelper::renderFooter() ?>
    <?= UIHelper::renderConfirmationModal() ?>

    <script>
        const csrfToken = "<?= htmlspecialchars($csrfToken) ?>";
        const toast = document.getElementById('toastNotification');
        const rejectModal = document.getElementById('rejectTutorModal');
        const rejectIdInput = document.getElementById('rejectTutorId');
        const rejectReasonInput = document.getElementById('rejectReasonInput');

        function showToast(msg, isSuccess = true) {
            toast.className = isSuccess 
                ? 'mb-6 p-4 rounded-2xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-xs'
                : 'mb-6 p-4 rounded-2xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200 shadow-xs';
            toast.innerHTML = msg;
            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.add('hidden'); }, 4000);
        }

        async function approveTutor(id) {
            const confirmed = await window.AppiConfirm.show({
                title: 'Approve Tutor Application',
                message: 'Are you sure you want to approve this tutor? They will immediately be granted access to create availability slots and receive student bookings.',
                confirmText: 'Approve Tutor',
                isDestructive: false
            });
            if (!confirmed) return;

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

        function rejectTutor(id) {
            rejectIdInput.value = id;
            rejectReasonInput.value = 'Qualifications or DBS credentials could not be verified.';
            rejectModal.classList.remove('hidden');
        }

        function closeRejectModal() {
            rejectModal.classList.add('hidden');
        }

        async function submitRejectTutor() {
            const id = rejectIdInput.value;
            const reason = rejectReasonInput.value.trim();
            if (!reason) {
                alert('Please provide a reason.');
                return;
            }

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
                    closeRejectModal();
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
