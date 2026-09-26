<?php declare(strict_types=1);

/**
 * Parent Portal: Manage Children
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Services\CsrfService;
use App\Services\UIHelper;

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
<body class="min-h-full flex flex-col justify-between text-slate-800 bg-slate-50 antialiased">

    <?php UIHelper::renderHeader($user, 'children'); ?>

    <main class="flex-grow max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Manage Children & Students</h1>
                <p class="text-slate-600 text-sm mt-1">Add student details, academic year groups, and learning goals for tailored tutor matching.</p>
            </div>
            <button type="button" onclick="openAddModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center gap-2 self-start sm:self-auto">
                <span>+ Add Child</span>
            </button>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-xl text-xs font-semibold" role="alert"></div>

        <div id="childrenContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="col-span-full text-center py-16 text-slate-400">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent mb-2"></div>
                <div class="text-xs font-semibold">Loading student profiles...</div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Child Modal -->
    <div id="childModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 id="modalTitle" class="text-base font-bold text-slate-900">Add Child Profile</h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold p-1 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Close modal">&times;</button>
            </div>

            <form id="childForm" class="space-y-4">
                <input type="hidden" id="child_id" name="child_id" value="">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="date_of_birth" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="year_group" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Year Group / Key Stage</label>
                        <input type="text" id="year_group" name="year_group" placeholder="e.g. Year 10 (GCSE) or A-Level" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="school_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">School Name (Optional)</label>
                    <input type="text" id="school_name" name="school_name" placeholder="e.g. St Mary's High School" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label for="learning_goals" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Learning Goals & Focus Areas</label>
                    <textarea id="learning_goals" name="learning_goals" rows="3" placeholder="e.g. Needs confidence boost in GCSE Physics & exam paper practice..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400">Cancel</button>
                    <button type="submit" id="saveChildBtn" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition focus:outline-none focus:ring-2 focus:ring-indigo-500">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <?php UIHelper::renderConfirmationModal(); ?>
    <?php UIHelper::renderFooter(); ?>

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
                showAlert('Network error fetching children profiles.', 'error');
            }
        }

        function renderChildren() {
            if (childrenList.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl font-bold">👶</div>
                        <h3 class="font-extrabold text-slate-800 text-base">No Children Profiles Registered Yet</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Add your children to start booking tailored lessons with verified UK tutors.</p>
                        <button type="button" onclick="openAddModal()" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition">Add First Child</button>
                    </div>
                `;
                return;
            }

            let html = '';
            childrenList.forEach(c => {
                const initials = (c.first_name[0] || '') + (c.last_name[0] || '');
                html += `
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 flex flex-col justify-between hover:border-slate-300 transition">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-sm">${escapeHtml(initials)}</div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-sm">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</h3>
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">${escapeHtml(c.year_group || 'Year not specified')}</span>
                                    </div>
                                </div>
                            </div>

                            ${c.school_name ? `<p class="text-xs text-slate-500 mt-2">🏫 <strong>${escapeHtml(c.school_name)}</strong></p>` : ''}
                            ${c.learning_goals ? `<p class="text-xs text-slate-600 mt-3 p-3 bg-slate-50 rounded-xl leading-relaxed">🎯 <em>${escapeHtml(c.learning_goals)}</em></p>` : ''}
                        </div>

                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <a href="/tutors.php" class="text-xs font-bold text-indigo-600 hover:underline">Find Tutors &rarr;</a>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="openEditModal(${c.id})" class="px-2.5 py-1 text-xs font-semibold text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50 transition">Edit</button>
                                <button type="button" onclick="deleteChild(${c.id}, '${escapeHtml(c.first_name)}')" class="px-2.5 py-1 text-xs font-semibold text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-50 transition">Delete</button>
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
            document.getElementById('first_name').focus();
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
            document.getElementById('first_name').focus();
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
                    showAlert(isEdit ? 'Child profile updated successfully.' : 'Child profile created successfully.', 'success');
                    loadChildren();
                } else {
                    let errMsg = data.message || 'Failed to save child profile';
                    if (data.errors) {
                        errMsg += ': ' + Object.values(data.errors).join(' ');
                    }
                    showAlert(errMsg, 'error');
                }
            } catch (err) {
                showAlert('Network error while saving child profile.', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Profile';
            }
        });

        async function deleteChild(childId, childName) {
            const confirmed = await window.AppiConfirm.show({
                title: 'Delete Child Profile',
                message: `Are you sure you want to delete ${childName}'s profile? Existing bookings will remain preserved in your history.`,
                confirmText: 'Delete Profile',
                isDestructive: true
            });

            if (!confirmed) return;

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
                ? 'mb-6 p-4 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-xs font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            alertBox.classList.remove('hidden');
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

