<?php declare(strict_types=1);

/**
 * Tutor Portal: Select & Manage Subjects
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

use App\Services\UIHelper;

// Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor();
$csrfToken = CsrfService::getToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <?= UIHelper::renderHeader($user, 'Subjects') ?>

    <main class="flex-grow max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Teaching Subjects</h1>
                <p class="text-slate-600 text-sm mt-1">Select the UK curricula and subjects you are qualified and enthusiastic to teach.</p>
            </div>
            <button type="button" id="saveSubjectsTopBtn" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition flex items-center gap-2">
                <span>Save Subjects</span>
            </button>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-2xl text-sm font-medium"></div>

        <!-- Selected Summary Badge Bar -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Currently Selected:</span>
                <span id="selectedCountBadge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">0 subjects</span>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold">
                <button type="button" id="selectAllBtn" class="text-indigo-600 hover:underline">Select All in View</button>
                <span class="text-slate-300">|</span>
                <button type="button" id="clearAllBtn" class="text-rose-600 hover:underline">Clear All</button>
            </div>
        </div>

        <!-- Subjects Container grouped by Curricula -->
        <div id="curriculaContainer" class="space-y-6">
            <div class="text-center py-12 text-slate-400">Loading subjects catalog...</div>
        </div>

        <div class="mt-8 flex justify-end">
            <button type="button" id="saveSubjectsBottomBtn" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md transition">
                Save Selected Subjects
            </button>
        </div>
    </main>

    <?= UIHelper::renderFooter() ?>
    <?= UIHelper::renderConfirmationModal() ?>

    <script>
        let selectedSubjectIds = new Set();
        let curriculaData = [];
        let allSubjectsData = [];

        const alertBox = document.getElementById('alertBox');
        const container = document.getElementById('curriculaContainer');
        const badge = document.getElementById('selectedCountBadge');
        const saveTop = document.getElementById('saveSubjectsTopBtn');
        const saveBottom = document.getElementById('saveSubjectsBottomBtn');

        async function loadSubjects() {
            try {
                const res = await fetch('/api/tutor/subjects.php');
                const json = await res.json();
                if (!json.success) {
                    showAlert(json.message || 'Failed to load subjects', 'error');
                    return;
                }

                curriculaData = json.data.curricula || [];
                allSubjectsData = json.data.all_subjects || [];
                selectedSubjectIds = new Set((json.data.selected_subject_ids || []).map(Number));

                renderSubjects();
            } catch (e) {
                showAlert('Network error loading subjects.', 'error');
            }
        }

        function renderSubjects() {
            badge.textContent = `${selectedSubjectIds.size} subject${selectedSubjectIds.size === 1 ? '' : 's'}`;

            let html = '';
            curriculaData.forEach(c => {
                const subjectsInCurriculum = allSubjectsData.filter(s => Number(s.curriculum_id) === Number(c.id));
                if (subjectsInCurriculum.length === 0) return;

                html += `
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-base">${escapeHtml(c.name)}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(c.description || '')}</p>
                            </div>
                            <span class="text-xs font-mono font-bold px-2 py-1 bg-white border border-slate-200 rounded text-slate-600">${escapeHtml(c.code)}</span>
                        </div>
                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                `;

                subjectsInCurriculum.forEach(s => {
                    const isChecked = selectedSubjectIds.has(Number(s.id));
                    html += `
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border transition cursor-pointer select-none ${
                            isChecked ? 'border-indigo-500 bg-indigo-50/50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                        }">
                            <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 subject-checkbox" 
                                data-id="${s.id}" ${isChecked ? 'checked' : ''}>
                            <div class="text-sm">
                                <div class="font-bold text-slate-900 leading-snug">${escapeHtml(s.name)}</div>
                            </div>
                        </label>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Bind checkbox change listeners
            document.querySelectorAll('.subject-checkbox').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    const id = Number(e.target.dataset.id);
                    if (e.target.checked) {
                        selectedSubjectIds.add(id);
                    } else {
                        selectedSubjectIds.delete(id);
                    }
                    renderSubjects();
                });
            });
        }

        async function saveSubjects() {
            saveTop.disabled = true;
            saveBottom.disabled = true;
            saveTop.textContent = 'Saving...';
            saveBottom.textContent = 'Saving...';

            try {
                const res = await fetch('/api/tutor/subjects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?= $csrfToken ?>',
                        subject_ids: Array.from(selectedSubjectIds)
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showAlert('Subjects updated successfully!', 'success');
                } else {
                    showAlert(data.message || 'Failed to save subjects.', 'error');
                }
            } catch (err) {
                showAlert('Network error while saving subjects.', 'error');
            } finally {
                saveTop.disabled = false;
                saveBottom.disabled = false;
                saveTop.textContent = 'Save Subjects';
                saveBottom.textContent = 'Save Selected Subjects';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.className = type === 'success'
                ? 'mb-6 p-4 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200'
                : 'mb-6 p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        saveTop.addEventListener('click', saveSubjects);
        saveBottom.addEventListener('click', saveSubjects);

        document.getElementById('clearAllBtn').addEventListener('click', () => {
            selectedSubjectIds.clear();
            renderSubjects();
        });

        document.getElementById('selectAllBtn').addEventListener('click', () => {
            allSubjectsData.forEach(s => selectedSubjectIds.add(Number(s.id)));
            renderSubjects();
        });

        loadSubjects();
    </script>
</body>
</html>
