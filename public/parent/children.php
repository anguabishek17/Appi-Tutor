<?php declare(strict_types=1);

/**
 * Parent Portal: Manage Children
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;

// Require STUDENT_PARENT role
$user = AuthMiddleware::requireRole(['STUDENT_PARENT']);
$csrfToken = CsrfService::getToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children | AppiTutors</title>
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
                <a href="/parent/dashboard.php" class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-xl font-bold text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-2 text-sm font-semibold">
                    <a href="/parent/dashboard.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Overview</a>
                    <a href="/parent/children.php" class="px-3 py-1.5 text-indigo-600 bg-indigo-50 rounded-lg">Children</a>
                    <a href="/parent/bookings.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">My Bookings</a>
                    <a href="/tutors.php" class="px-3 py-1.5 text-slate-600 hover:text-indigo-600 rounded-lg">Find a Tutor</a>
                </nav>
            </div>
            <div class="flex items-center gap-4 text-sm font-semibold">
                <span class="text-slate-600"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                <a href="/logout.php" class="text-rose-600 hover:text-rose-700">Sign out</a>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Manage Children & Students</h1>
                <p class="text-slate-600 text-sm mt-1">Add your children's details, year groups, and learning goals for tailored tutor matching.</p>
            </div>
            <button type="button" onclick="openAddModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition flex items-center gap-2">
                <span>+ Add Child</span>
            </button>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-sm font-medium"></div>

        <div id="childrenContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="col-span-full text-center py-12 text-slate-400">Loading children profiles...</div>
        </div>
    </main>

    <!-- Add/Edit Child Modal -->
    <div id="childModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 id="modalTitle" class="text-lg font-bold text-slate-900">Add Child Profile</h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>

            <form id="childForm" class="space-y-4">
                <input type="hidden" id="child_id" name="child_id" value="">
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Year Group</label>
                        <input type="text" id="year_group" name="year_group" placeholder="e.g. Year 10 (GCSE)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">School Name</label>
                    <input type="text" id="school_name" name="school_name" placeholder="e.g. St Mary's High School" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Learning Goals & Focus Areas</label>
                    <textarea id="learning_goals" name="learning_goals" rows="2" placeholder="e.g. Needs confidence boost in GCSE Physics & exam paper practice..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" id="saveChildBtn" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition">Save Child Profile</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        © <?= date('Y') ?> AppiTutors Ltd.
    </footer>

    <script>
        let childrenList = [];
        const container = document.getElementById('childrenContainer');
        const alertBox = document.getElementById('alertBox');
        const modal = document.getElementById('childModal');
        const form = document.getElementById('childForm');
        const modalTitle = document.getElementById('modalTitle');
        const saveBtn = document.getElementById('saveChildBtn');

        async function loadChildren() {
            try {
                const res = await fetch('/api/parent/children.php');
                const json = await res.json();
                if (!json.success) {
                    showAlert(json.message || 'Failed to load children', 'error');
                    return;
                }
                childrenList = json.data.children || [];
                renderChildren();
            } catch (e) {
                showAlert('Network error fetching children.', 'error');
            }
        }

        function renderChildren() {
            if (childrenList.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">👶</div>
                        <h3 class="font-extrabold text-slate-800 text-lg">No Children Profiles Added Yet</h3>
                        <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">Add your child's profile to book personalized lessons with verified tutors.</p>
                        <button type="button" onclick="openAddModal()" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md">Add First Child</button>
                    </div>
                `;
                return;
            }

            let html = '';
            childrenList.forEach(c => {
                const initials = (c.first_name[0] || '') + (c.last_name[0] || '');
                html += `
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-base">${escapeHtml(initials)}</div>
                                    <div>
                                        <h3 class="font-extrabold text-slate-900 text-base">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</h3>
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">${escapeHtml(c.year_group || 'Not specified')}</span>
                                    </div>
                                </div>
                            </div>

                            ${c.school_name ? `<p class="text-xs text-slate-500 mt-1">🏫 <strong>${escapeHtml(c.school_name)}</strong></p>` : ''}
                            ${c.learning_goals ? `<p class="text-xs text-slate-600 mt-3 p-3 bg-slate-50 rounded-xl leading-relaxed">🎯 <em>${escapeHtml(c.learning_goals)}</em></p>` : ''}
                        </div>

                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <a href="/tutors.php" class="text-xs font-bold text-indigo-600 hover:underline">Find Tutors &rarr;</a>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openEditModal(${c.id})" class="px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Edit</button>
                                <button type="button" onclick="deleteChild(${c.id})" class="px-2.5 py-1 text-xs font-semibold text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-50">Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function openAddModal() {
            form.reset();
            document.getElementById('child_id').value = '';
            modalTitle.textContent = 'Add Child Profile';
            modal.classList.remove('hidden');
        }

        function openEditModal(childId) {
            const child = childrenList.find(c => Number(c.id) === Number(childId));
            if (!child) return;

            document.getElementById('child_id').value = child.id;
            document.getElementById('first_name').value = child.first_name || '';
            document.getElementById('last_name').value = child.last_name || '';
            document.getElementById('date_of_birth').value = child.date_of_birth || '';
            document.getElementById('year_group').value = child.year_group || '';
            document.getElementById('school_name').value = child.school_name || '';
            document.getElementById('learning_goals').value = child.learning_goals || '';

            modalTitle.textContent = 'Edit Child Profile';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            const childId = document.getElementById('child_id').value;
            const isEdit = Boolean(childId);

            const payload = {
                csrf_token: '<?= $csrfToken ?>',
                id: childId ? parseInt(childId, 10) : undefined,
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                date_of_birth: document.getElementById('date_of_birth').value,
                year_group: document.getElementById('year_group').value,
                school_name: document.getElementById('school_name').value,
                learning_goals: document.getElementById('learning_goals').value,
                _method: isEdit ? 'PUT' : 'POST'
            };

            try {
                const res = await fetch('/api/parent/children.php', {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    closeModal();
                    showAlert(isEdit ? 'Child profile updated!' : 'Child profile created!', 'success');
                    loadChildren();
                } else {
                    let errMsg = data.message || 'Failed to save child profile';
                    if (data.errors) {
                        errMsg += ': ' + Object.values(data.errors).join(' ');
                    }
                    alert(errMsg);
                }
            } catch (err) {
                alert('Network error while saving child profile.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Child Profile';
            }
        });

        async function deleteChild(childId) {
            if (!confirm('Are you sure you want to delete this child profile?')) return;

            try {
                const res = await fetch('/api/parent/children.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        id: childId
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('Child profile deleted successfully.', 'success');
                    loadChildren();
                } else {
                    showAlert(data.message || 'Failed to delete child profile.', 'error');
                }
            } catch (e) {
                showAlert('Network error deleting child profile.', 'error');
            }
        }

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

        loadChildren();
    </script>
</body>
</html>
