<?php
declare(strict_types=1);

/**
 * Public Page: Email Verification Prompt
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthService;

$email = htmlspecialchars($_GET['email'] ?? 'your email', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email | AppiTutors</title>
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
        <div class="w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        </div>
        <h2 class="text-3xl font-extrabold text-slate-900">Verify your email address</h2>
        <p class="mt-2 text-sm text-slate-600">
            We've sent a confirmation email to <strong class="text-slate-900"><?= $email ?></strong>.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl shadow-slate-200/50 sm:rounded-2xl sm:px-10 border border-slate-200/80 text-center">
            
            <p class="text-sm text-slate-600 leading-relaxed mb-6">
                Please check your inbox (and spam folder) and click the confirmation link to activate your AppiTutors account.
            </p>

            <div id="alertMessage" class="hidden mb-6 p-4 rounded-xl text-sm"></div>

            <div class="space-y-3">
                <button type="button" id="resendBtn"
                        class="w-full flex justify-center py-2.5 px-4 border border-slate-300 rounded-xl shadow-sm text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition">
                    Resend Verification Email
                </button>

                <a href="/login.php"
                   class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition">
                    Return to Log In
                </a>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">
                    Need help? Contact our support team at <a href="mailto:support@appitutors.co.uk" class="text-indigo-600 font-medium">support@appitutors.co.uk</a>.
                </p>
            </div>

        </div>
    </div>

    <script src="/assets/js/auth.js"></script>
    <script>
        const resendBtn = document.getElementById('resendBtn');
        const alertBox = document.getElementById('alertMessage');

        resendBtn.addEventListener('click', async () => {
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';

            try {
                await window.AppiAuth.resendVerificationEmail();
                alertBox.className = 'mb-6 p-4 rounded-xl text-sm bg-green-50 text-green-800 border border-green-200';
                alertBox.textContent = 'Verification email resent! Please check your inbox.';
            } catch (e) {
                alertBox.className = 'mb-6 p-4 rounded-xl text-sm bg-amber-50 text-amber-800 border border-amber-200';
                alertBox.textContent = e.message || 'Please log in again to trigger email resend.';
            } finally {
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Verification Email';
            }
        });
    </script>
</body>
</html>
