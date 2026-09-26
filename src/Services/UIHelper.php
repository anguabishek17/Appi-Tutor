<?php
declare(strict_types=1);

namespace App\Services;

/**
 * AppiTutors Shared UI Component & Formatting Helper
 * Provides consistent UK formatting, status badges, layout shell, and modals.
 */
class UIHelper
{
    /**
     * Format a DateTime or SQL datetime string in UK Format: DD/MM/YYYY
     */
    public static function formatDate(?string $dateStr, string $format = 'd/m/Y'): string
    {
        if (!$dateStr) {
            return '—';
        }
        try {
            $tz = new \DateTimeZone('Europe/London');
            $dt = new \DateTimeImmutable($dateStr, $tz);
            return $dt->format($format);
        } catch (\Throwable $e) {
            return htmlspecialchars($dateStr);
        }
    }

    /**
     * Format a DateTime in UK friendly format: e.g. "Thu, 15 Oct 2026 at 14:00"
     */
    public static function formatDateTime(?string $dateStr): string
    {
        if (!$dateStr) {
            return '—';
        }
        try {
            $tz = new \DateTimeZone('Europe/London');
            $dt = new \DateTimeImmutable($dateStr, $tz);
            return $dt->format('D, j M Y \a\t H:i');
        } catch (\Throwable $e) {
            return htmlspecialchars($dateStr);
        }
    }

    /**
     * Format time range in UK 24-hour style: e.g. "14:00 - 15:00"
     */
    public static function formatTimeRange(?string $startStr, ?string $endStr): string
    {
        if (!$startStr || !$endStr) {
            return '—';
        }
        try {
            $tz = new \DateTimeZone('Europe/London');
            $start = new \DateTimeImmutable($startStr, $tz);
            $end = new \DateTimeImmutable($endStr, $tz);
            return $start->format('H:i') . ' – ' . $end->format('H:i');
        } catch (\Throwable $e) {
            return htmlspecialchars($startStr . ' - ' . $endStr);
        }
    }

    /**
     * Format UK currency in GBP (£)
     */
    public static function formatCurrency(float|int|string|null $amount): string
    {
        $val = (float)($amount ?? 0);
        return '£' . number_format($val, 2);
    }

    /**
     * Render a consistent booking status badge
     */
    public static function renderBookingStatusBadge(string $status): string
    {
        $config = match ($status) {
            'PENDING' => [
                'label' => 'Pending Approval',
                'bg' => 'bg-amber-50 text-amber-800 border-amber-200/80',
                'dot' => 'bg-amber-500'
            ],
            'ACCEPTED' => [
                'label' => 'Confirmed',
                'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
                'dot' => 'bg-emerald-500'
            ],
            'REJECTED' => [
                'label' => 'Declined',
                'bg' => 'bg-rose-50 text-rose-800 border-rose-200/80',
                'dot' => 'bg-rose-500'
            ],
            'RESCHEDULE_PROPOSED' => [
                'label' => 'Reschedule Proposed',
                'bg' => 'bg-indigo-50 text-indigo-800 border-indigo-200/80',
                'dot' => 'bg-indigo-500'
            ],
            'CANCELLED' => [
                'label' => 'Cancelled',
                'bg' => 'bg-slate-100 text-slate-700 border-slate-200',
                'dot' => 'bg-slate-400'
            ],
            'SYSTEM_CANCELLED' => [
                'label' => 'Automatically Cancelled',
                'bg' => 'bg-orange-50 text-orange-800 border-orange-200/80',
                'dot' => 'bg-orange-500'
            ],
            'COMPLETED' => [
                'label' => 'Completed',
                'bg' => 'bg-blue-50 text-blue-800 border-blue-200/80',
                'dot' => 'bg-blue-500'
            ],
            default => [
                'label' => ucfirst(strtolower(str_replace('_', ' ', $status))),
                'bg' => 'bg-slate-100 text-slate-700 border-slate-200',
                'dot' => 'bg-slate-400'
            ]
        };

        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border %s" data-status="%s">
                <span class="w-1.5 h-1.5 rounded-full %s shrink-0" aria-hidden="true"></span>
                <span>%s</span>
            </span>',
            $config['bg'],
            htmlspecialchars($status),
            $config['dot'],
            htmlspecialchars($config['label'])
        );
    }

    /**
     * Render a consistent tutor approval status badge
     */
    public static function renderTutorApprovalBadge(string $status): string
    {
        $config = match ($status) {
            'PENDING' => [
                'label' => 'Pending Review',
                'bg' => 'bg-amber-50 text-amber-800 border-amber-200',
                'dot' => 'bg-amber-500'
            ],
            'APPROVED' => [
                'label' => 'Verified & Approved',
                'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'dot' => 'bg-emerald-500'
            ],
            'REJECTED' => [
                'label' => 'Application Declined',
                'bg' => 'bg-rose-50 text-rose-800 border-rose-200',
                'dot' => 'bg-rose-500'
            ],
            'SUSPENDED' => [
                'label' => 'Suspended',
                'bg' => 'bg-slate-100 text-slate-700 border-slate-300',
                'dot' => 'bg-slate-500'
            ],
            default => [
                'label' => ucfirst(strtolower($status)),
                'bg' => 'bg-slate-100 text-slate-700 border-slate-200',
                'dot' => 'bg-slate-400'
            ]
        };

        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border %s">
                <span class="w-1.5 h-1.5 rounded-full %s shrink-0" aria-hidden="true"></span>
                <span>%s</span>
            </span>',
            $config['bg'],
            $config['dot'],
            htmlspecialchars($config['label'])
        );
    }

    /**
     * Render Attendance Status Badge
     */
    public static function renderAttendanceBadge(string $attendance): string
    {
        $config = match ($attendance) {
            'ATTENDED' => [
                'label' => 'Attended',
                'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'dot' => 'bg-emerald-500'
            ],
            'PARTIAL' => [
                'label' => 'Partial / Late',
                'bg' => 'bg-amber-50 text-amber-800 border-amber-200',
                'dot' => 'bg-amber-500'
            ],
            'ABSENT' => [
                'label' => 'Absent / Missed',
                'bg' => 'bg-rose-50 text-rose-800 border-rose-200',
                'dot' => 'bg-rose-500'
            ],
            default => [
                'label' => 'Not Recorded',
                'bg' => 'bg-slate-100 text-slate-600 border-slate-200',
                'dot' => 'bg-slate-400'
            ]
        };

        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border %s">
                <span class="w-1.5 h-1.5 rounded-full %s shrink-0" aria-hidden="true"></span>
                <span>%s</span>
            </span>',
            $config['bg'],
            $config['dot'],
            htmlspecialchars($config['label'])
        );
    }

    /**
     * Render standard App Header with Role Navigation
     */
    public static function renderHeader(array $user, string $activePage = 'dashboard'): void
    {
        $role = $user['role'] ?? 'STUDENT_PARENT';
        $fullName = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
        
        $roleBadge = match ($role) {
            'MANAGER' => '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-900/80 text-indigo-200 border border-indigo-700/50">Manager</span>',
            'TUTOR' => '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Tutor Portal</span>',
            default => '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">Parent Portal</span>'
        };

        $navItems = [];
        if ($role === 'STUDENT_PARENT') {
            $navItems = [
                ['label' => 'Dashboard', 'url' => '/parent/dashboard.php', 'key' => 'dashboard'],
                ['label' => 'My Children', 'url' => '/parent/children.php', 'key' => 'children'],
                ['label' => 'Find Tutors', 'url' => '/tutors.php', 'key' => 'find_tutors'],
                ['label' => 'My Bookings', 'url' => '/parent/bookings.php', 'key' => 'bookings'],
            ];
        } elseif ($role === 'TUTOR') {
            $navItems = [
                ['label' => 'Dashboard', 'url' => '/tutor/dashboard.php', 'key' => 'dashboard'],
                ['label' => 'My Profile', 'url' => '/tutor/profile.php', 'key' => 'profile'],
                ['label' => 'Subjects', 'url' => '/tutor/subjects.php', 'key' => 'subjects'],
                ['label' => 'Availability', 'url' => '/tutor/availability.php', 'key' => 'availability'],
                ['label' => 'Bookings & Lessons', 'url' => '/tutor/bookings.php', 'key' => 'bookings'],
            ];
        } elseif ($role === 'MANAGER') {
            $navItems = [
                ['label' => 'Dashboard', 'url' => '/manager/dashboard.php', 'key' => 'dashboard'],
                ['label' => 'Tutor Approvals', 'url' => '/manager/tutors.php', 'key' => 'tutors'],
                ['label' => 'Browse Marketplace', 'url' => '/tutors.php', 'key' => 'find_tutors'],
            ];
        }
        ?>
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <a href="/dashboard.php" class="flex items-center gap-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg" aria-label="AppiTutors Home">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-sm" aria-hidden="true">
                            A
                        </div>
                        <span class="text-xl font-bold text-slate-900 tracking-tight">Appi<span class="text-indigo-600">Tutors</span></span>
                        <div class="hidden sm:inline-block ml-1">
                            <?= $roleBadge ?>
                        </div>
                    </a>

                    <!-- Desktop Nav -->
                    <nav class="hidden lg:flex items-center gap-1 text-sm font-semibold" aria-label="Main Navigation">
                        <?php foreach ($navItems as $item): ?>
                            <?php $isActive = ($activePage === $item['key']); ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" 
                               class="px-3 py-2 rounded-lg transition-colors <?= $isActive ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:text-indigo-600 hover:bg-slate-50' ?>"
                               <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <!-- User info & signout -->
                    <div class="hidden sm:flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center text-xs font-bold" aria-hidden="true">
                            <?= htmlspecialchars($initials ?: 'U') ?>
                        </div>
                        <span class="text-sm font-semibold text-slate-700"><?= $fullName ?></span>
                    </div>

                    <a href="/logout.php" class="text-xs sm:text-sm font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 px-3 py-1.5 rounded-lg border border-transparent hover:border-rose-100 transition focus:outline-none focus:ring-2 focus:ring-rose-500">
                        Sign out
                    </a>

                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="toggleMobileMenu()" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Toggle mobile menu" aria-expanded="false" id="mobileMenuBtn">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div id="mobileMenu" class="hidden lg:hidden border-t border-slate-200 bg-white px-4 pt-2 pb-4 space-y-1 shadow-lg">
                <div class="py-2 px-3 border-b border-slate-100 mb-2 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider"><?= $fullName ?></span>
                    <?= $roleBadge ?>
                </div>
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($activePage === $item['key']); ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="block px-3 py-2.5 rounded-lg text-sm font-semibold transition <?= $isActive ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>
        <script>
            function toggleMobileMenu() {
                const menu = document.getElementById('mobileMenu');
                const btn = document.getElementById('mobileMenuBtn');
                const isHidden = menu.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', !isHidden);
            }
        </script>
        <?php
    }

    /**
     * Render accessible standard confirmation modal HTML & JS helper
     */
    public static function renderConfirmationModal(): void
    {
        ?>
        <!-- Reusable Accessible Confirmation Modal -->
        <div id="appiConfirmModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="appiConfirmTitle" aria-describedby="appiConfirmMessage">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-4 transform transition-all">
                <div class="flex items-start gap-3">
                    <div id="appiConfirmIcon" class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div class="flex-1">
                        <h3 id="appiConfirmTitle" class="text-base font-bold text-slate-900">Confirm Action</h3>
                        <p id="appiConfirmMessage" class="text-xs text-slate-600 mt-1 leading-relaxed">Are you sure you want to proceed?</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="appiConfirmCancelBtn" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400">Cancel</button>
                    <button type="button" id="appiConfirmActionBtn" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-rose-500">Confirm</button>
                </div>
            </div>
        </div>
        <script>
            window.AppiConfirm = {
                show: function(options) {
                    return new Promise((resolve) => {
                        const modal = document.getElementById('appiConfirmModal');
                        const titleEl = document.getElementById('appiConfirmTitle');
                        const msgEl = document.getElementById('appiConfirmMessage');
                        const actionBtn = document.getElementById('appiConfirmActionBtn');
                        const cancelBtn = document.getElementById('appiConfirmCancelBtn');
                        const iconEl = document.getElementById('appiConfirmIcon');

                        titleEl.textContent = options.title || 'Confirm Action';
                        msgEl.textContent = options.message || 'Are you sure you want to proceed?';
                        actionBtn.textContent = options.confirmText || 'Confirm';

                        if (options.isDestructive === false) {
                            actionBtn.className = "px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500";
                            iconEl.className = "w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0 mt-0.5";
                        } else {
                            actionBtn.className = "px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-sm transition focus:outline-none focus:ring-2 focus:ring-rose-500";
                            iconEl.className = "w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5";
                        }

                        modal.classList.remove('hidden');
                        actionBtn.focus();

                        const cleanup = () => {
                            modal.classList.add('hidden');
                            actionBtn.onclick = null;
                            cancelBtn.onclick = null;
                            document.removeEventListener('keydown', handleKey);
                        };

                        const handleKey = (e) => {
                            if (e.key === 'Escape') {
                                cleanup();
                                resolve(false);
                            }
                        };

                        document.addEventListener('keydown', handleKey);

                        actionBtn.onclick = () => {
                            cleanup();
                            resolve(true);
                        };

                        cancelBtn.onclick = () => {
                            cleanup();
                            resolve(false);
                        };
                    });
                }
            };
        </script>
        <?php
    }

    /**
     * Render Standard Footer
     */
    public static function renderFooter(): void
    {
        ?>
        <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white mt-auto">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div>© <?= date('Y') ?> AppiTutors Ltd. UK Registered Tutoring Platform. All rights reserved.</div>
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-1 text-emerald-700 font-medium text-[11px] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Live System Ready
                    </span>
                </div>
            </div>
        </footer>
        <?php
    }
}
