<?php
declare(strict_types=1);

/**
 * Public Page: Login
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\CsrfService;

// If already logged in, redirect to appropriate portal
$currentUser = AuthService::user();
if ($currentUser) {
    if ($currentUser['role'] === 'MANAGER') {
        header('Location: /manager/dashboard.php');
        exit;
    } elseif ($currentUser['role'] === 'TUTOR') {
        if (($currentUser['tutor_approval_status'] ?? '') === 'APPROVED') {
            header('Location: /tutor/dashboard.php');
            exit;
        }
        header('Location: /tutor/pending.php');
        exit;
    } elseif ($currentUser['role'] === 'STUDENT_PARENT') {
        header('Location: /parent/dashboard.php');
        exit;
    }
}

$config = require CONFIG_PATH . '/app.php';
$firebaseClientConfig = $config['firebase']['client'];
$csrfToken = CsrfService::getToken();
$isFirebaseConfigured = !empty($firebaseClientConfig['apiKey']) && $firebaseClientConfig['apiKey'] !== 'AIzaSyDemoApiKey1234567890';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In | AppiTutors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>

    <!-- Firebase App & Auth SDKs -->
    <script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-auth-compat.js"></script>
</head>
<body class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="/" class="inline-flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-xl shadow-md shadow-indigo-200">
                A
            </div>
            <span class="text-2xl font-extrabold tracking-tight text-slate-900">Appi<span class="text-indigo-600">Tutors</span></span>
        </a>
        <h2 class="mt-6 text-3xl font-extrabold text-slate-900">Sign in to your account</h2>
        <p class="mt-2 text-sm text-slate-600">
            Or <a href="/register.php" class="font-medium text-indigo-600 hover:text-indigo-500">create a new account</a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-200/80">

            <?php if (!$isFirebaseConfigured): ?>
                <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <span class="font-semibold">Firebase Not Configured Yet:</span> To enable live email and Google authentication, provide valid Firebase API keys in your <code class="bg-amber-100 px-1 py-0.5 rounded">.env</code> file.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="alertMessage" class="hidden mb-6 p-4 rounded-xl text-sm"></div>

            <form id="loginForm" class="space-y-6">
                <?= CsrfService::field() ?>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required
                               class="appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="you@example.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="text-sm">
                        <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Forgot your password?</a>
                    </div>
                </div>

                <div>
                    <button type="submit" id="submitBtn"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition disabled:opacity-50">
                        Sign in
                    </button>
                </div>
            </form>

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-slate-500">Or continue with</span>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="button" id="googleSignInBtn"
                            class="w-full inline-flex justify-center items-center gap-3 py-2.5 px-4 border border-slate-300 rounded-xl shadow-sm bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                        </svg>
                        Sign in with Google
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Inject Client Config -->
    <script>
        window.APPITUTORS_FIREBASE_CONFIG = <?= json_encode($firebaseClientConfig) ?>;
        window.APPITUTORS_CSRF_TOKEN = "<?= htmlspecialchars($csrfToken) ?>";
    </script>
    <script src="/assets/js/auth.js"></script>
    <script>
        const form = document.getElementById('loginForm');
        const alertBox = document.getElementById('alertMessage');
        const submitBtn = document.getElementById('submitBtn');
        const googleBtn = document.getElementById('googleSignInBtn');

        function showAlert(message, type = 'error') {
            alertBox.classList.remove('hidden', 'bg-red-50', 'text-red-800', 'border-red-200', 'bg-green-50', 'text-green-800', 'border-green-200');
            if (type === 'error') {
                alertBox.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
            } else {
                alertBox.classList.add('bg-green-50', 'text-green-800', 'border', 'border-green-200');
            }
            alertBox.innerHTML = message;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertBox.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            try {
                const res = await window.AppiAuth.login(email, password);
                if (res.needsVerification) {
                    window.location.href = '/verify-email.php?email=' + encodeURIComponent(email);
                    return;
                }
                showAlert('Signed in successfully! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = res.backendData.redirect || '/dashboard.php';
                }, 800);
            } catch (err) {
                showAlert(err.message || 'Login failed. Please check credentials.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign in';
            }
        });

        googleBtn.addEventListener('click', async () => {
            try {
                const res = await window.AppiAuth.loginWithGoogle();
                showAlert('Signed in with Google! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = res.backendData.redirect || '/dashboard.php';
                }, 800);
            } catch (err) {
                showAlert(err.message || 'Google sign in failed');
            }
        });
    </script>
</body>
</html>
