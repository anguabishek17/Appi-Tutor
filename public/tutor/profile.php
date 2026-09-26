<?php declare(strict_types=1);

/**
 * Tutor Portal: Edit Tutor Profile
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Middleware\AuthMiddleware;
use App\Database\Connection;
use App\Services\CsrfService;

use App\Services\UIHelper;

// Require APPROVED tutor status
$user = AuthMiddleware::requireApprovedTutor();
$db = Connection::getInstance();

// Fetch existing profile data
$stmt = $db->prepare('
    SELECT 
        tp.id AS tutor_profile_id,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.teaching_mode,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.avatar_url
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.user_id = :uid
    LIMIT 1
');
$stmt->execute([':uid' => $user['id']]);
$profile = $stmt->fetch() ?: [];
$csrfToken = CsrfService::getToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tutor Profile | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-between text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <?= UIHelper::renderHeader($user, 'Profile') ?>

    <main class="flex-grow max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Breadcrumb / Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Tutor Profile Settings</h1>
                <p class="text-slate-600 text-sm mt-1">Complete your professional credentials so parents and students can learn about your tutoring style.</p>
            </div>
            <?php if (!empty($profile['tutor_profile_id'])): ?>
                <a href="/tutor.php?id=<?= (int)$profile['tutor_profile_id'] ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-700 bg-indigo-50 px-3.5 py-2 rounded-xl border border-indigo-100 transition self-start sm:self-auto">
                    <span>Preview Public Profile</span>
                    <span>↗</span>
                </a>
            <?php endif; ?>
        </div>

        <div id="alertBox" class="hidden mb-6 p-4 rounded-2xl text-sm font-medium"></div>

        <form id="profileForm" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6">
            <?= CsrfService::field() ?>

            <!-- Personal Info -->
            <div class="border-b border-slate-100 pb-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">Personal Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-400 text-sm cursor-not-allowed">
                        <span class="text-xs text-slate-400 mt-0.5 block">Managed via authentication provider</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Contact Phone</label>
                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="+44 7123 456789"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Profile Photo URL</label>
                        <input type="url" id="avatar_url" name="avatar_url" value="<?= htmlspecialchars($profile['avatar_url'] ?? '') ?>" placeholder="https://example.com/photo.jpg"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>
                </div>
            </div>

            <!-- Tutoring Details -->
            <div class="border-b border-slate-100 pb-6">
                <h2 class="text-lg font-bold text-slate-900 mb-4">Tutoring & Experience</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Professional Headline *</label>
                        <input type="text" id="headline" name="headline" value="<?= htmlspecialchars($profile['headline'] ?? '') ?>" placeholder="e.g. Oxford Maths Graduate | 5+ Years GCSE & A-Level Specialist" required
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Hourly Rate (£ GBP) *</label>
                            <input type="number" id="hourly_rate" name="hourly_rate" step="0.50" min="10" max="500" value="<?= htmlspecialchars((string)($profile['hourly_rate'] ?? 35.00)) ?>" required
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm font-semibold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Years Experience *</label>
                            <input type="number" id="experience_years" name="experience_years" min="0" max="60" value="<?= htmlspecialchars((string)($profile['experience_years'] ?? 1)) ?>" required
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Teaching Mode *</label>
                            <select id="teaching_mode" name="teaching_mode" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                <option value="ONLINE" <?= ($profile['teaching_mode'] ?? '') === 'ONLINE' ? 'selected' : '' ?>>Online Only</option>
                                <option value="IN_PERSON" <?= ($profile['teaching_mode'] ?? '') === 'IN_PERSON' ? 'selected' : '' ?>>In-Person Only</option>
                                <option value="BOTH" <?= ($profile['teaching_mode'] ?? 'BOTH') === 'BOTH' ? 'selected' : '' ?>>Both Online & In-Person</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">About / Bio *</label>
                        <textarea id="bio" name="bio" rows="5" placeholder="Describe your teaching philosophy, methodology, exam boards covered, and what makes your lessons engaging..." required
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm leading-relaxed"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Qualifications & Degrees</label>
                        <textarea id="qualifications" name="qualifications" rows="3" placeholder="e.g. BSc Mathematics (1st Class) - Imperial College London, PGCE Secondary Education, Qualified Teacher Status (QTS)"
                                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"><?= htmlspecialchars($profile['qualifications'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="/tutor/dashboard.php" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-semibold text-sm transition">Cancel</a>
                <button type="submit" id="saveBtn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-md shadow-indigo-100 transition flex items-center gap-2">
                    <span>Save Profile Changes</span>
                </button>
            </div>
        </form>
    </main>

    <?= UIHelper::renderFooter() ?>

    <script>
        const form = document.getElementById('profileForm');
        const alertBox = document.getElementById('alertBox');
        const saveBtn = document.getElementById('saveBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span>Saving...</span>';
            alertBox.className = 'hidden';

            const payload = {
                csrf_token: '<?= $csrfToken ?>',
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                phone: document.getElementById('phone').value,
                avatar_url: document.getElementById('avatar_url').value,
                headline: document.getElementById('headline').value,
                hourly_rate: parseFloat(document.getElementById('hourly_rate').value),
                experience_years: parseInt(document.getElementById('experience_years').value, 10),
                teaching_mode: document.getElementById('teaching_mode').value,
                bio: document.getElementById('bio').value,
                qualifications: document.getElementById('qualifications').value
            };

            try {
                const res = await fetch('/api/tutor/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    alertBox.textContent = 'Profile updated successfully!';
                    alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200';
                } else {
                    let errMsg = data.message || 'Failed to update profile';
                    if (data.errors) {
                        errMsg += ': ' + Object.values(data.errors).join(' ');
                    }
                    alertBox.textContent = errMsg;
                    alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
                }
            } catch (err) {
                alertBox.textContent = 'Network error occurred. Please try again.';
                alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200';
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span>Save Profile Changes</span>';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>
