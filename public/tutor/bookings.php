<?php declare(strict_types=1);

/**
 * Tutor Portal: Manage Bookings & Requests
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

// Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor();
$csrfToken = CsrfService::getToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | AppiTutors</title>
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
                <a href="/tutor/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-2 text-sm font-semibold">
                    <a href="/tutor/dashboard.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Overview</a>
                    <a href="/tutor/profile.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Profile</a>
                    <a href="/tutor/subjects.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Subjects</a>
                    <a href="/tutor/availability.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Availability</a>
                    <a href="/tutor/bookings.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">Bookings</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Booking Management</h1>
                <p class="text-slate-600 text-sm mt-1">Review incoming lesson requests from parents, confirm sessions, or inspect your schedule.</p>
            </div>
            <button type="button" onclick="loadBookings()" class="px-4 py-2 text-xs font-bold text-indigo-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-2xs">
                🔄 Refresh Bookings
            </button>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-sm font-medium"></div>

        <!-- Metric Filter Pills -->
        <div class="flex items-center gap-3 mb-8">
            <button type="button" onclick="setTab('pending')" id="tab_pending" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-sm flex items-center gap-2">
                <span>Pending Requests</span>
                <span id="badge_pending" class="px-2 py-0.5 rounded-full bg-indigo-700 text-white text-[11px]">0</span>
            </button>
            <button type="button" onclick="setTab('accepted')" id="tab_accepted" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2">
                <span>Upcoming Lessons</span>
                <span id="badge_accepted" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px]">0</span>
            </button>
            <button type="button" onclick="setTab('all')" id="tab_all" class="px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2">
                <span>All Bookings</span>
                <span id="badge_all" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px]">0</span>
            </button>
        </div>

        <!-- Bookings Container -->
        <div id="bookingsList" class="space-y-4">
            <div class="text-center py-16 text-slate-400">Loading bookings...</div>
        </div>
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-lg font-bold text-slate-900">Decline Booking Request</h3>
                <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>
            <p class="text-xs text-slate-600">The parent will be notified and the slot capacity will be automatically released back to your schedule.</p>
            
            <form id="rejectForm" class="space-y-3">
                <input type="hidden" id="reject_booking_id" value="">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reason for declining (Optional)</label>
                    <textarea id="reject_reason" rows="3" placeholder="e.g. Unforeseen schedule conflict, already at capacity..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" id="confirmRejectBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold rounded-xl shadow-sm transition">Decline Request</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white mt-16">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>

    <script>
        let allBookings = [];
        let currentTab = 'pending';
        const alertBox = document.getElementById('alertBox');
        const container = document.getElementById('bookingsList');
        const rejectModal = document.getElementById('rejectModal');
        const rejectForm = document.getElementById('rejectForm');

        function setTab(tab) {
            currentTab = tab;
            ['pending', 'accepted', 'all'].forEach(t => {
                const btn = document.getElementById('tab_' + t);
                if (t === tab) {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-sm flex items-center gap-2';
                } else {
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 flex items-center gap-2';
                }
            });
            renderBookings();
        }

        async function loadBookings() {
            try {
                const res = await fetch('/api/tutor/bookings.php');
                const json = await res.json();
                if (!json.success) {
                    showAlert(json.message || 'Failed to load bookings', 'error');
                    return;
                }

                allBookings = json.data.bookings || [];
                const counts = json.data.counts || {};
                document.getElementById('badge_pending').textContent = counts.pending || 0;
                document.getElementById('badge_accepted').textContent = counts.accepted || 0;
                document.getElementById('badge_all').textContent = json.data.total || 0;

                renderBookings();
            } catch (e) {
                showAlert('Network error fetching bookings.', 'error');
            }
        }

        function renderBookings() {
            let filtered = allBookings;
            if (currentTab === 'pending') {
                filtered = allBookings.filter(b => b.status === 'PENDING');
            } else if (currentTab === 'accepted') {
                filtered = allBookings.filter(b => b.status === 'ACCEPTED');
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3 text-xl">📋</div>
                        <h3 class="font-extrabold text-slate-800 text-lg">No ${currentTab.toUpperCase()} Bookings</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">When parents submit lesson requests, they will appear here for your review.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            filtered.forEach(b => {
                const start = new Date(b.scheduled_start.replace(' ', 'T'));
                const end = new Date(b.scheduled_end.replace(' ', 'T'));
                const dateFormatted = start.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
                const startTime = start.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                const endTime = end.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                const isPending = b.status === 'PENDING';
                const isAccepted = b.status === 'ACCEPTED';

                const statusBadge = isPending 
                    ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">Pending Review</span>'
                    : (isAccepted 
                        ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Confirmed & Accepted</span>'
                        : '<span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">' + escapeHtml(b.status) + '</span>');

                html += `
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:border-slate-300 transition">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-lg">
                                🎓
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3 class="font-extrabold text-slate-900 text-base">${escapeHtml(b.subject_name)}</h3>
                                    ${statusBadge}
                                </div>
                                <p class="text-xs text-slate-600 mt-1">
                                    Student: <strong>${escapeHtml(b.child_first_name || 'Self')} ${escapeHtml(b.child_last_name || '')}</strong> 
                                    ${b.child_year_group ? ' (' + escapeHtml(b.child_year_group) + ')' : ''} • 
                                    Parent: <strong>${escapeHtml(b.parent_first_name)} ${escapeHtml(b.parent_last_name)}</strong>
                                </p>
                                <div class="text-xs text-slate-500 mt-2 flex flex-wrap items-center gap-3">
                                    <span>🗓️ ${dateFormatted}</span>
                                    <span>•</span>
                                    <span>🕒 ${startTime} – ${endTime} (UK)</span>
                                    <span>•</span>
                                    <span>${b.delivery_mode === 'ONLINE' ? '🌐 Online' : '📍 In-Person'}</span>
                                    <span>•</span>
                                    <span class="font-mono text-[11px] text-slate-400">Ref: ${escapeHtml(b.booking_reference)}</span>
                                </div>
                                ${b.student_notes ? `<div class="mt-3 p-3 bg-slate-50 rounded-xl text-xs text-slate-700 leading-relaxed border border-slate-100">💬 <strong>Parent Note:</strong> ${escapeHtml(b.student_notes)}</div>` : ''}
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row md:flex-col items-end justify-between gap-4 self-stretch md:self-center border-t md:border-t-0 pt-4 md:pt-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <div class="text-lg font-extrabold text-slate-900">£${Number(b.total_amount).toFixed(2)}</div>
                                <div class="text-[11px] text-slate-400">£${Number(b.hourly_rate).toFixed(2)} / hr</div>
                            </div>

                            ${isPending ? `
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="acceptBooking(${b.booking_id})" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                                        Accept Request
                                    </button>
                                    <button type="button" onclick="openRejectModal(${b.booking_id})" class="px-4 py-2 border border-rose-200 text-rose-600 hover:bg-rose-50 font-bold text-xs rounded-xl transition">
                                        Decline
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function acceptBooking(bookingId) {
            if (!confirm('Accept this booking request? This will confirm the session on your calendar.')) return;

            try {
                const res = await fetch('/api/bookings/accept.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        id: bookingId
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('Booking request confirmed and accepted!', 'success');
                    loadBookings();
                } else {
                    showAlert(data.message || 'Failed to accept booking', 'error');
                }
            } catch (err) {
                showAlert('Network error while accepting booking.', 'error');
            }
        }

        function openRejectModal(bookingId) {
            document.getElementById('reject_booking_id').value = bookingId;
            document.getElementById('reject_reason').value = '';
            rejectModal.classList.remove('hidden');
        }

        function closeRejectModal() {
            rejectModal.classList.add('hidden');
        }

        rejectForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('confirmRejectBtn');
            btn.disabled = true;
            btn.textContent = 'Declining...';

            const bookingId = parseInt(document.getElementById('reject_booking_id').value, 10);
            const reason = document.getElementById('reject_reason').value;

            try {
                const res = await fetch('/api/bookings/reject.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        id: bookingId,
                        rejection_reason: reason
                    })
                });
                const data = await res.json();

                if (data.success) {
                    closeRejectModal();
                    showAlert('Booking request declined and slot capacity released.', 'success');
                    loadBookings();
                } else {
                    alert(data.message || 'Failed to decline booking');
                }
            } catch (err) {
                alert('Network error while declining booking.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Decline Request';
            }
        });

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

        loadBookings();
    </script>
</body>
</html>
