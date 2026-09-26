<?php declare(strict_types=1);

/**
 * Public Tutor Search Page
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Database\Connection;

use App\Services\UIHelper;

$user = AuthService::user();
$db = Connection::getInstance();

// Fetch initial filter data
$subjectsStmt = $db->query('
    SELECT s.id, s.name, c.name AS curriculum_name 
    FROM subjects s 
    JOIN curricula c ON s.curriculum_id = c.id 
    ORDER BY c.id ASC, s.name ASC
');
$subjects = $subjectsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find an Expert UK Tutor | AppiTutors</title>
    <meta name="description" content="Browse verified, DBS-checked UK tutors across Primary, 11+, GCSE, and A-Level. Book online or in-person sessions.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <?= UIHelper::renderPublicHeader($user, 'find_tutors') ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Search Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Find Top Verified UK Tutors</h1>
            <p class="text-slate-600 mt-2">Every tutor on AppiTutors is manually reviewed, identity-verified, and approved by our management team.</p>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
            <form id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Subject</label>
                    <select id="filter_subject" name="subject_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        <option value="">All Subjects & Curricula</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['curriculum_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Delivery Mode</label>
                    <select id="filter_mode" name="delivery_mode" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        <option value="">Any Mode (Online or In-Person)</option>
                        <option value="ONLINE">Online Lessons</option>
                        <option value="IN_PERSON">In-Person Lessons</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Max Hourly Rate</label>
                    <select id="filter_max_rate" name="max_rate" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        <option value="">Any Rate</option>
                        <option value="30">Up to £30 / hr</option>
                        <option value="45">Up to £45 / hr</option>
                        <option value="60">Up to £60 / hr</option>
                        <option value="80">Up to £80 / hr</option>
                        <option value="120">Up to £120 / hr</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Keyword Search</label>
                    <div class="flex gap-2">
                        <input type="text" id="filter_search" name="search" placeholder="Name or keyword..."
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        <button type="button" id="resetBtn" class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 border border-slate-200 rounded-xl hover:bg-slate-50">Reset</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Result Stats & Tutor Grid -->
        <div class="flex items-center justify-between mb-6">
            <div id="resultsCount" class="text-sm font-bold text-slate-700">Loading verified tutors...</div>
        </div>

        <div id="tutorsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Dynamic Tutor Cards will render here -->
        </div>
    </main>

    <?= UIHelper::renderPublicFooter() ?>

    <script>
        const tutorsGrid = document.getElementById('tutorsGrid');
        const resultsCount = document.getElementById('resultsCount');
        const form = document.getElementById('filterForm');

        async function fetchTutors() {
            tutorsGrid.innerHTML = `
                <div class="col-span-full text-center py-16">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-r-transparent"></div>
                    <p class="mt-3 text-sm text-slate-500">Searching verified tutors...</p>
                </div>
            `;

            const params = new URLSearchParams({
                subject_id: document.getElementById('filter_subject').value,
                delivery_mode: document.getElementById('filter_mode').value,
                max_rate: document.getElementById('filter_max_rate').value,
                search: document.getElementById('filter_search').value
            });

            try {
                const res = await fetch('/api/tutors/search.php?' + params.toString());
                const data = await res.json();

                if (!data.success) {
                    tutorsGrid.innerHTML = `<div class="col-span-full p-6 text-center text-rose-600">${escapeHtml(data.message)}</div>`;
                    return;
                }

                renderTutors(data.data.tutors || []);
            } catch (err) {
                tutorsGrid.innerHTML = `<div class="col-span-full p-6 text-center text-rose-600">Failed to load tutors. Please try again.</div>`;
            }
        }

        function renderTutors(tutors) {
            resultsCount.textContent = `${tutors.length} Verified Tutor${tutors.length === 1 ? '' : 's'} Available`;

            if (tutors.length === 0) {
                tutorsGrid.innerHTML = `
                    <div class="col-span-full bg-white rounded-2xl border border-slate-200 p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3 text-xl">🔍</div>
                        <h3 class="font-extrabold text-slate-800 text-lg">No tutors match your filter criteria</h3>
                        <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">Try broadening your subject filter, adjusting the hourly rate, or removing keywords.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            tutors.forEach(t => {
                const initials = (t.first_name[0] || '') + (t.last_name[0] || '');
                const avatar = t.avatar_url 
                    ? `<img src="${escapeHtml(t.avatar_url)}" alt="${escapeHtml(t.first_name)}" class="w-14 h-14 rounded-2xl object-cover border border-slate-200">`
                    : `<div class="w-14 h-14 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-extrabold text-lg">${initials}</div>`;

                const subjectBadges = (t.subjects || []).slice(0, 3).map(s => 
                    `<span class="px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">${escapeHtml(s.name)}</span>`
                ).join(' ');

                const extraSubjectsCount = (t.subjects || []).length > 3 ? `<span class="text-xs text-slate-500 font-semibold">+${(t.subjects.length - 3)} more</span>` : '';

                const teachingBadge = t.teaching_mode === 'ONLINE' ? 'Online Only' : (t.teaching_mode === 'IN_PERSON' ? 'In-Person Only' : 'Online & In-Person');

                html += `
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div class="flex items-center gap-3.5">
                                    ${avatar}
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-extrabold text-slate-900 text-base">${escapeHtml(t.first_name)} ${escapeHtml(t.last_name[0])}.</h3>
                                            <span class="inline-flex items-center text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">✓ DBS Verified</span>
                                        </div>
                                        <span class="text-xs text-slate-500 font-medium">${t.experience_years} year${t.experience_years === 1 ? '' : 's'} experience</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-extrabold text-slate-900">£${t.hourly_rate.toFixed(2)}</div>
                                    <div class="text-[11px] text-slate-400">/ hour</div>
                                </div>
                            </div>

                            <p class="font-bold text-slate-800 text-sm line-clamp-1 mb-2">${escapeHtml(t.headline || 'UK Academic Specialist')}</p>
                            <p class="text-slate-600 text-xs line-clamp-3 leading-relaxed mb-4">${escapeHtml(t.bio || 'Dedicated tutor supporting students in mastering concepts and building exam confidence.')}</p>

                            <div class="flex flex-wrap items-center gap-1.5 mb-4">
                                ${subjectBadges}
                                ${extraSubjectsCount}
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                <span>📍 ${teachingBadge}</span>
                                ${t.active_slots_count > 0 ? `<span class="text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-bold">${t.active_slots_count} slots open</span>` : '<span class="text-slate-400">No active slots</span>'}
                            </div>
                            <a href="/tutor.php?id=${t.tutor_profile_id}" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition">
                                View Profile &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });

            tutorsGrid.innerHTML = html;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Search inputs event listeners
        ['filter_subject', 'filter_mode', 'filter_max_rate'].forEach(id => {
            document.getElementById(id).addEventListener('change', fetchTutors);
        });

        let searchDebounceTimer;
        document.getElementById('filter_search').addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(fetchTutors, 300);
        });

        document.getElementById('resetBtn').addEventListener('click', () => {
            form.reset();
            fetchTutors();
        });

        fetchTutors();
    </script>
</body>
</html>
