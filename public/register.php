<?php
declare(strict_types=1);

/**
 * Public Page: Registration
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;
use App\Services\CsrfService;

// If already logged in, redirect
$currentUser = AuthService::user();
if ($currentUser) {
    header('Location: /dashboard.php');
    exit;
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
    <title>Create an Account | AppiTutors</title>
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
        <h2 class="mt-6 text-3xl font-extrabold text-slate-900">Create your account</h2>
        <p class="mt-2 text-sm text-slate-600">
            Already have an account? <a href="/login.php" class="font-medium text-indigo-600 hover:text-indigo-500">Sign in</a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-200/80">

            <div id="alertMessage" class="hidden mb-6 p-4 rounded-xl text-sm"></div>

            <form id="registerForm" class="space-y-5">
                <?= CsrfService::field() ?>

                <!-- Role Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">I want to join as</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex flex-col items-center p-3 border-2 border-indigo-600 bg-indigo-50/50 rounded-xl cursor-pointer role-option" id="roleParentCard">
                            <input type="radio" name="role" value="STUDENT_PARENT" checked class="sr-only">
                            <span class="text-sm font-bold text-indigo-900">Parent / Guardian</span>
                            <span class="text-xs text-indigo-600 mt-0.5">Find a tutor</span>
                        </label>
                        <label class="relative flex flex-col items-center p-3 border-2 border-slate-200 bg-white rounded-xl cursor-pointer role-option hover:border-slate-300" id="roleTutorCard">
                            <input type="radio" name="role" value="TUTOR" class="sr-only">
                            <span class="text-sm font-bold text-slate-800">Tutor</span>
                            <span class="text-xs text-slate-500 mt-0.5">Teach & earn</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="firstName" class="block text-sm font-medium text-slate-700">First name</label>
                        <input id="firstName" name="firstName" type="text" required
                               class="mt-1 appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Sarah">
                    </div>
                    <div>
                        <label for="lastName" class="block text-sm font-medium text-slate-700">Last name</label>
                        <input id="lastName" name="lastName" type="text" required
                               class="mt-1 appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Jenkins">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="mt-1 appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           placeholder="you@example.co.uk">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required minlength="6"
                           class="mt-1 appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           placeholder="At least 6 characters">
                </div>

                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-slate-700">Confirm password</label>
                    <input id="confirmPassword" name="confirmPassword" type="password" required minlength="6"
                           class="mt-1 appearance-none block w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           placeholder="Repeat password">
                </div>

                <div>
                    <button type="submit" id="submitBtn"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition disabled:opacity-50">
                        Create Account
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Inject Client Config -->
    <script>
        window.APPITUTORS_FIREBASE_CONFIG = <?= json_encode($firebaseClientConfig) ?>;
        window.APPITUTORS_CSRF_TOKEN = "<?= htmlspecialchars($csrfToken) ?>";
    </script>
    <script src="/assets/js/auth.js"></script>
    <script>
        const form = document.getElementById('registerForm');
        const alertBox = document.getElementById('alertMessage');
        const submitBtn = document.getElementById('submitBtn');
        const roleParentCard = document.getElementById('roleParentCard');
        const roleTutorCard = document.getElementById('roleTutorCard');

        // Role radio toggling
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'STUDENT_PARENT') {
                    roleParentCard.className = 'relative flex flex-col items-center p-3 border-2 border-indigo-600 bg-indigo-50/50 rounded-xl cursor-pointer role-option';
                    roleTutorCard.className = 'relative flex flex-col items-center p-3 border-2 border-slate-200 bg-white rounded-xl cursor-pointer role-option hover:border-slate-300';
                } else {
                    roleTutorCard.className = 'relative flex flex-col items-center p-3 border-2 border-indigo-600 bg-indigo-50/50 rounded-xl cursor-pointer role-option';
                    roleParentCard.className = 'relative flex flex-col items-center p-3 border-2 border-slate-200 bg-white rounded-xl cursor-pointer role-option hover:border-slate-300';
                }
            });
        });

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

            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const role = document.querySelector('input[name="role"]:checked').value;

            if (password !== confirmPassword) {
                showAlert('Passwords do not match. Please verify.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';

            try {
                const res = await window.AppiAuth.register(email, password, firstName, lastName, role);
                showAlert('Account created! A verification link has been sent to your email.', 'success');
                setTimeout(() => {
                    window.location.href = '/verify-email.php?email=' + encodeURIComponent(email);
                }, 1200);
            } catch (err) {
                showAlert(err.message || 'Registration failed.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>
