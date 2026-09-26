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
     * Render Accessible Public Header
     */
    public static function renderPublicHeader(?array $user = null, string $activePage = 'home'): void
    {
        $fullName = $user ? htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) : '';
        $navItems = [
            ['label' => 'Home', 'url' => '/', 'key' => 'home'],
            ['label' => 'Find a Tutor', 'url' => '/tutors.php', 'key' => 'find_tutors'],
            ['label' => 'Subjects', 'url' => '/#subjects', 'key' => 'subjects'],
            ['label' => 'How It Works', 'url' => '/#how-it-works', 'key' => 'how_it_works'],
            ['label' => 'Become a Tutor', 'url' => '/register.php', 'key' => 'become_a_tutor'],
        ];
        ?>
        <header id="appiPublicHeader" class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80 transition-all duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
                <div class="flex items-center gap-8">
                    <a href="/" class="flex items-center gap-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-xl group" aria-label="AppiTutors Home">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-extrabold text-lg shadow-md shadow-indigo-600/20 group-hover:bg-indigo-700 transition" aria-hidden="true">
                            A
                        </div>
                        <span class="text-xl font-extrabold tracking-tight text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
                    </a>

                    <!-- Desktop Navigation Links -->
                    <nav class="hidden md:flex items-center gap-1.5 text-sm font-semibold text-slate-600" aria-label="Main Navigation">
                        <?php foreach ($navItems as $item): ?>
                            <?php $isActive = ($activePage === $item['key']); ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>" 
                               class="px-3.5 py-1.5 rounded-lg transition-colors hover:text-indigo-600 hover:bg-slate-50 <?= $isActive ? 'text-indigo-600 font-bold bg-indigo-50/70' : '' ?>"
                               <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Right Side Actions (Desktop & Mobile) -->
                <div class="flex items-center gap-3">
                    <?php if ($user): ?>
                        <a href="/dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <span>My Dashboard</span>
                            <span class="text-xs font-normal opacity-90 hidden sm:inline">(<?= $fullName ?>)</span>
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="text-sm font-semibold text-slate-700 hover:text-indigo-600 transition px-3.5 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg">
                            Log In
                        </a>
                        <a href="/register.php" class="inline-flex items-center px-4.5 py-2 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm shadow-indigo-600/20 transition focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Get Started
                        </a>
                    <?php endif; ?>

                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="togglePublicMobileMenu()" class="md:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Toggle Navigation Menu" aria-expanded="false" id="publicMobileMenuBtn">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div id="publicMobileMenu" class="hidden md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-2 shadow-xl">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = ($activePage === $item['key']); ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="block px-3 py-2.5 rounded-xl text-sm font-semibold <?= $isActive ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                    <?php if (!$user): ?>
                        <a href="/login.php" class="text-center py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50">Log In</a>
                        <a href="/register.php" class="text-center py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold shadow-sm hover:bg-indigo-700">Get Started</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <script>
            function togglePublicMobileMenu() {
                const menu = document.getElementById('publicMobileMenu');
                const btn = document.getElementById('publicMobileMenuBtn');
                const isHidden = menu.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', !isHidden);
            }
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const menu = document.getElementById('publicMobileMenu');
                    if (menu && !menu.classList.contains('hidden')) {
                        togglePublicMobileMenu();
                    }
                }
            });
            window.addEventListener('scroll', () => {
                const header = document.getElementById('appiPublicHeader');
                if (header) {
                    if (window.scrollY > 15) {
                        header.classList.add('shadow-xs');
                    } else {
                        header.classList.remove('shadow-xs');
                    }
                }
            }, { passive: true });
        </script>
        <?php
    }

    /**
     * Render Accessible Public Footer
     */
    public static function renderPublicFooter(): void
    {
        ?>
        <footer class="bg-slate-900 text-slate-400 py-14 border-t border-slate-800 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-800">
                    <div class="space-y-4 md:col-span-1">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-base shadow-sm">A</div>
                            <span class="text-xl font-extrabold text-white tracking-tight">Appi<span class="text-indigo-400">Tutors</span></span>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Helping families across the UK find trusted, DBS-verified subject specialists for 1-to-1 tuition, exam confidence, and measurable academic progress.
                        </p>
                        <div class="inline-flex items-center gap-2 text-[11px] font-semibold text-emerald-400 bg-emerald-950/60 px-3 py-1 rounded-full border border-emerald-800/60">
                            <span>✓</span> Enhanced DBS Verified Tutors
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3.5">Platform</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="/tutors.php" class="hover:text-white transition">Find a Tutor</a></li>
                            <li><a href="/#subjects" class="hover:text-white transition">Subjects & Curricula</a></li>
                            <li><a href="/#how-it-works" class="hover:text-white transition">How It Works</a></li>
                            <li><a href="/register.php" class="hover:text-white transition">Become a Tutor</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3.5">Curricula & Standards</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="/tutors.php" class="hover:text-white transition">GCSE & IGCSE Tuition</a></li>
                            <li><a href="/tutors.php" class="hover:text-white transition">A-Level & AS Subjects</a></li>
                            <li><a href="/tutors.php" class="hover:text-white transition">11-Plus & Entrance Exams</a></li>
                            <li><a href="/tutors.php" class="hover:text-white transition">Online & In-Person Sessions</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-3.5">Account & Portals</h4>
                        <ul class="space-y-2.5 text-xs">
                            <li><a href="/login.php" class="hover:text-white transition">Log In to Portal</a></li>
                            <li><a href="/register.php" class="hover:text-white transition">Parent / Student Registration</a></li>
                            <li><a href="/register.php" class="hover:text-white transition">Tutor Application</a></li>
                            <li><a href="/login.php" class="hover:text-white transition">Manager Sign-in</a></li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                    <div>© <?= date('Y') ?> AppiTutors Ltd. UK Registered Tutoring Platform. All rights reserved.</div>
                    <div class="flex items-center gap-3 text-slate-400 text-xs">
                        <span>Europe/London (UK)</span>
                        <span>•</span>
                        <span>DBS Verified Network</span>
                    </div>
                </div>
            </div>
        </footer>
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
