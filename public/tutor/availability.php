<?php declare(strict_types=1);

/**
 * Tutor Portal: Availability Slots Management
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

// Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor();
$csrfToken = CsrfService::getToken();

// Calculate minimum allowed date (at least 24 hours in advance)
$tz = new DateTimeZone('Europe/London');
$minDate = (new DateTimeImmutable('now', $tz))->modify('+24 hours')->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Calendar & Slots | AppiTutors</title>
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
                    <a href="/tutor/availability.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">Availability</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-slate-900">Availability & Lesson Schedule</h1>
            <p class="text-slate-600 text-sm mt-1">Create specific dated availability slots when you are available for ONE_TO_ONE or GROUP tuition sessions. All times in London (UK) timezone.</p>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-sm font-medium"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Add New Slot Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        Add Availability Slot
                    </h2>

                    <form id="addSlotForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Date *</label>
                            <input type="date" id="slot_date" name="slot_date" min="<?= $minDate ?>" required
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            <span class="text-xs text-slate-400 mt-1 block">Must be at least 24h ahead</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Start Time *</label>
                                <input type="time" id="start_time" name="start_time" value="10:00" required
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">End Time *</label>
                                <input type="time" id="end_time" name="end_time" value="11:00" required
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Session Type *</label>
                            <select id="session_type" name="session_type" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                <option value="ONE_TO_ONE">1-to-1 Tutoring (Single Student)</option>
                                <option value="GROUP">Group Class (Multiple Students)</option>
                            </select>
                        </div>

                        <div id="capacityContainer" class="hidden">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Max Group Capacity *</label>
                            <input type="number" id="max_capacity" name="max_capacity" min="2" max="30" value="5"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            <span class="text-xs text-slate-400 mt-1 block">Between 2 and 30 students</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Delivery Mode *</label>
                            <select id="delivery_mode" name="delivery_mode" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                <option value="ONLINE">Online Lesson (Zoom / Google Meet)</option>
                                <option value="IN_PERSON">In-Person (Student / Tutor Location)</option>
                            </select>
                        </div>

                        <button type="submit" id="addSlotBtn" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition flex items-center justify-center gap-2 mt-2">
                            <span>Add Slot to Calendar</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right: Slot List & Actions -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <h3 class="font-extrabold text-slate-900 text-base">Upcoming Availability Slots</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Manage dated slots, toggle reservations, or remove obsolete slots.</p>
                        </div>
                        <button type="button" onclick="loadSlots()" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">Refresh</button>
                    </div>

                    <div id="slotsTableContainer" class="p-6">
                        <div class="text-center py-12 text-slate-400">Loading your schedule...</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>

    <script>
        const sessionTypeSelect = document.getElementById('session_type');
        const capacityContainer = document.getElementById('capacityContainer');
        const alertBox = document.getElementById('alertBox');
        const addForm = document.getElementById('addSlotForm');
        const addBtn = document.getElementById('addSlotBtn');
        const slotsContainer = document.getElementById('slotsTableContainer');

        sessionTypeSelect.addEventListener('change', () => {
            if (sessionTypeSelect.value === 'GROUP') {
                capacityContainer.classList.remove('hidden');
            } else {
                capacityContainer.classList.add('hidden');
            }
        });

        async function loadSlots() {
            try {
                const res = await fetch('/api/tutor/availability.php');
                const json = await res.json();
                if (!json.success) {
                    showAlert(json.message || 'Failed to load slots', 'error');
                    return;
                }
                renderSlots(json.data.slots || []);
            } catch (e) {
                showAlert('Network error fetching slots.', 'error');
            }
        }

        function renderSlots(slots) {
            if (slots.length === 0) {
                slotsContainer.innerHTML = `
                    <div class="text-center py-12">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3 text-xl">📅</div>
                        <h4 class="font-bold text-slate-800 text-base">No Availability Slots Created Yet</h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Use the form on the left to add your first dated teaching slot.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="space-y-3">
            `;

            slots.forEach(slot => {
                const start = new Date(slot.start_time.replace(' ', 'T'));
                const end = new Date(slot.end_time.replace(' ', 'T'));
                
                const dateFormatted = start.toLocaleDateString('en-GB', {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                });
                const startTimeFormatted = start.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                const endTimeFormatted = end.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                const isBlocked = Boolean(Number(slot.is_blocked));
                const bookedCount = Number(slot.booked_count);
                const maxCapacity = Number(slot.max_capacity);

                html += `
                    <div class="p-4 rounded-xl border transition flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                        isBlocked ? 'bg-slate-50/70 border-slate-200 opacity-60' : 'bg-white border-slate-200 hover:border-slate-300'
                    }">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl flex flex-col items-center justify-center font-bold text-xs ${
                                isBlocked ? 'bg-slate-200 text-slate-600' : 'bg-indigo-50 text-indigo-700'
                            }">
                                <span>${start.getDate()}</span>
                                <span class="text-[9px] uppercase font-semibold">${start.toLocaleString('en-GB', { month: 'short' })}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-slate-900 text-sm">${startTimeFormatted} – ${endTimeFormatted}</span>
                                    <span class="text-xs px-2 py-0.5 rounded font-semibold ${
                                        slot.session_type === 'ONE_TO_ONE' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800'
                                    }">${slot.session_type === 'ONE_TO_ONE' ? '1-to-1' : 'Group (' + maxCapacity + ')'}</span>
                                    <span class="text-xs px-2 py-0.5 rounded font-semibold ${
                                        slot.delivery_mode === 'ONLINE' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'
                                    }">${slot.delivery_mode === 'ONLINE' ? 'Online' : 'In-Person'}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-1 flex items-center gap-3">
                                    <span>${dateFormatted}</span>
                                    <span>•</span>
                                    <span>Bookings: <strong>${bookedCount}/${maxCapacity}</strong></span>
                                    ${isBlocked ? '<span class="text-rose-600 font-bold">• BLOCKED (Private)</span>' : ''}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <button type="button" onclick="toggleBlockSlot(${slot.id})" class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition ${
                                isBlocked ? 'border-indigo-300 text-indigo-600 hover:bg-indigo-50' : 'border-slate-200 text-slate-600 hover:bg-slate-100'
                            }">
                                ${isBlocked ? 'Unblock Slot' : 'Block Slot'}
                            </button>
                            ${bookedCount === 0 ? `
                                <button type="button" onclick="deleteSlot(${slot.id})" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 transition">
                                    Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
            slotsContainer.innerHTML = html;
        }

        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            addBtn.disabled = true;
            addBtn.textContent = 'Adding Slot...';

            const payload = {
                csrf_token: '<?= $csrfToken ?>',
                action: 'create',
                slot_date: document.getElementById('slot_date').value,
                start_time: document.getElementById('start_time').value,
                end_time: document.getElementById('end_time').value,
                session_type: document.getElementById('session_type').value,
                delivery_mode: document.getElementById('delivery_mode').value,
                max_capacity: parseInt(document.getElementById('max_capacity').value, 10) || 1
            };

            try {
                const res = await fetch('/api/tutor/availability.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('Slot created successfully!', 'success');
                    loadSlots();
                } else {
                    let errMsg = data.message || 'Failed to create slot';
                    if (data.errors) {
                        errMsg += ': ' + Object.values(data.errors).join(' ');
                    }
                    showAlert(errMsg, 'error');
                }
            } catch (err) {
                showAlert('Network error creating slot.', 'error');
            } finally {
                addBtn.disabled = false;
                addBtn.textContent = 'Add Slot to Calendar';
            }
        });

        async function toggleBlockSlot(slotId) {
            try {
                const res = await fetch('/api/tutor/availability.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        action: 'toggle_block',
                        slot_id: slotId
                    })
                });
                const data = await res.json();
                if (data.success) {
                    loadSlots();
                } else {
                    showAlert(data.message || 'Failed to update slot status', 'error');
                }
            } catch (e) {
                showAlert('Network error updating slot.', 'error');
            }
        }

        async function deleteSlot(slotId) {
            if (!confirm('Are you sure you want to delete this availability slot?')) return;

            try {
                const res = await fetch('/api/tutor/availability.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        action: 'delete',
                        slot_id: slotId
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Slot deleted.', 'success');
                    loadSlots();
                } else {
                    showAlert(data.message || 'Failed to delete slot', 'error');
                }
            } catch (e) {
                showAlert('Network error deleting slot.', 'error');
            }
        }

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = type === 'success'
                ? 'mb-6 p-4 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        loadSlots();
    </script>
</body>
</html>
