/**
 * UK Tutoring Platform - Authentication Testbed Component
 * Authentication Phase (F02)
 */

import {
  registerWithEmailPassword,
  loginWithEmailPassword,
  loginWithGoogle,
  logoutUser,
  sendVerificationEmail,
  sendPasswordReset,
  subscribeToAuthState,
  refreshToken,
  reloadUserState,
} from '../services/auth.js';
import { callFunction } from '../services/api.js';

export function setupAuthUI(container) {
  if (!container) return;

  container.innerHTML = `
    <div class="space-y-8 max-w-4xl mx-auto">
      
      <!-- Top Authentication Status Card -->
      <div id="auth-status-card" class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 backdrop-blur shadow-xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-slate-700/60">
          <div class="flex items-center space-x-3">
            <div id="auth-badge-dot" class="w-3 h-3 rounded-full bg-slate-500"></div>
            <div>
              <h2 class="text-lg font-semibold text-white tracking-tight">Authentication Session State</h2>
              <p id="auth-state-text" class="text-xs text-slate-400">Loading session...</p>
            </div>
          </div>
          <div id="auth-actions-group" class="hidden flex-wrap items-center gap-2">
            <button id="btn-refresh-token" class="px-3 py-1.5 text-xs font-medium bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg transition-colors border border-slate-600">
              Refresh ID Token & Claims
            </button>
            <button id="btn-logout" class="px-3 py-1.5 text-xs font-medium bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 rounded-lg transition-colors">
              Sign Out
            </button>
          </div>
        </div>

        <!-- User Details Panel (Shown when signed in) -->
        <div id="user-details-panel" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 text-xs">
          <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-800">
            <span class="text-slate-500 block font-medium">User Identity</span>
            <span id="user-email" class="font-mono text-slate-200 block truncate font-medium mt-0.5">--</span>
            <span id="user-uid" class="font-mono text-slate-500 block truncate text-[10px] mt-0.5">UID: --</span>
          </div>

          <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-800">
            <span class="text-slate-500 block font-medium">Authoritative Role Claim</span>
            <div class="flex items-center space-x-2 mt-1">
              <span id="user-role-badge" class="px-2 py-0.5 rounded font-mono font-bold uppercase bg-slate-800 text-slate-400 border border-slate-700">
                NONE
              </span>
            </div>
          </div>

          <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-800">
            <span class="text-slate-500 block font-medium">Email Verification Status</span>
            <div id="email-verified-status" class="mt-1 flex items-center space-x-2">
              <span class="w-2 h-2 rounded-full bg-amber-400"></span>
              <span class="text-amber-300 font-medium">Unverified</span>
            </div>
          </div>
        </div>

        <!-- Verification Warning Banner -->
        <div id="unverified-banner" class="hidden mt-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
          <div class="flex items-center space-x-2">
            <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>Please verify your email address to access role-protected services.</span>
          </div>
          <div class="flex items-center space-x-2 shrink-0">
            <button id="btn-resend-verification" class="px-2.5 py-1 text-xs bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 rounded border border-amber-500/40">
              Resend Email
            </button>
            <button id="btn-reload-user" class="px-2.5 py-1 text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 rounded border border-slate-600">
              Check Verified Status
            </button>
          </div>
        </div>
      </div>

      <!-- Auth Forms Grid (Registration & Login) -->
      <div id="auth-forms-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Registration Card -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div>
            <h3 class="text-base font-semibold text-white">Create Account</h3>
            <p class="text-xs text-slate-400 mt-1">Register with an authoritative role (STUDENT_PARENT or TUTOR).</p>
          </div>

          <form id="form-register" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Display Name</label>
              <input type="text" id="reg-name" placeholder="Alex Taylor" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>

            <div>
              <label class="block text-slate-400 font-medium mb-1">Email Address *</label>
              <input type="email" id="reg-email" required placeholder="alex@example.co.uk" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>

            <div>
              <label class="block text-slate-400 font-medium mb-1">Password *</label>
              <input type="password" id="reg-password" required minlength="6" placeholder="••••••••••••" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>

            <div>
              <label class="block text-slate-400 font-medium mb-1">Select Requested Role *</label>
              <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center space-x-2 p-2.5 bg-slate-900 border border-slate-700 rounded-lg cursor-pointer hover:border-sky-500">
                  <input type="radio" name="reg-role" value="STUDENT_PARENT" checked class="text-sky-500 focus:ring-0">
                  <span class="text-slate-200 font-medium">Student / Parent</span>
                </label>
                <label class="flex items-center space-x-2 p-2.5 bg-slate-900 border border-slate-700 rounded-lg cursor-pointer hover:border-sky-500">
                  <input type="radio" name="reg-role" value="TUTOR" class="text-sky-500 focus:ring-0">
                  <span class="text-slate-200 font-medium">Tutor</span>
                </label>
              </div>
            </div>

            <button type="submit" id="btn-submit-register" class="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-500 text-white font-medium rounded-lg shadow transition-colors flex items-center justify-center">
              Register & Request Role
            </button>
          </form>

          <!-- Security demonstration button -->
          <div class="pt-2 border-t border-slate-700/50">
            <button type="button" id="btn-test-malicious-manager-reg" class="w-full py-1.5 px-3 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-lg text-[11px] transition-colors">
              &bull; Security Test: Attempt requestedRole="MANAGER"
            </button>
          </div>
        </div>

        <!-- Sign In Card -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div>
            <h3 class="text-base font-semibold text-white">Sign In</h3>
            <p class="text-xs text-slate-400 mt-1">Authenticate using your credentials or Google account.</p>
          </div>

          <form id="form-login" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Email Address</label>
              <input type="email" id="login-email" required placeholder="name@example.co.uk" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>

            <div>
              <div class="flex justify-between items-center mb-1">
                <label class="text-slate-400 font-medium">Password</label>
                <button type="button" id="btn-forgot-password" class="text-[11px] text-sky-400 hover:underline">Forgot password?</button>
              </div>
              <input type="password" id="login-password" required placeholder="••••••••••••" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>

            <button type="submit" id="btn-submit-login" class="w-full py-2.5 px-4 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg shadow transition-colors flex items-center justify-center">
              Sign In with Email
            </button>
          </form>

          <div class="relative my-4">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-700"></div></div>
            <div class="relative flex justify-center text-[10px] uppercase"><span class="bg-slate-800 px-2 text-slate-500 font-medium">Or continue with</span></div>
          </div>

          <button type="button" id="btn-google-login" class="w-full py-2 px-4 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-medium rounded-lg transition-colors flex items-center justify-center space-x-2 text-xs">
            <svg class="w-4 h-4" viewBox="0 0 24 24">
              <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.7l3.1-3.1C17.3 1.8 14.8 1 12 1 7.4 1 3.5 3.6 1.6 7.3l3.7 2.9C6.2 7.1 8.8 5 12 5z"/>
              <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.7-.2-2.3H12v4.6h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.9z"/>
              <path fill="#FBBC05" d="M5.3 14.8c-.2-.7-.4-1.5-.4-2.8s.2-2.1.4-2.8L1.6 6.3C.6 8.3 0 10.6 0 13s.6 4.7 1.6 6.7l3.7-4.9z"/>
              <path fill="#34A853" d="M12 23c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3.2 0-5.8-2.1-6.7-5.2L1.6 18C3.5 21.7 7.4 23 12 23z"/>
            </svg>
            <span>Sign In with Google</span>
          </button>
        </div>
      </div>

      <!-- Protected Functions Testing Console -->
      <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
        <div>
          <h3 class="text-base font-semibold text-white">Authorization Test Console</h3>
          <p class="text-xs text-slate-400 mt-1">
            Test invocation of server-side Cloud Functions protected by <code class="text-sky-400">requireAuth</code>, <code class="text-sky-400">requireVerifiedEmail</code>, <code class="text-sky-400">requireActiveUser</code>, and <code class="text-sky-400">requireRole</code>.
          </p>
        </div>

        <!-- Endpoints Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
          <button id="btn-call-health" class="p-3 bg-slate-900/80 hover:bg-slate-900 border border-slate-700/70 hover:border-slate-600 rounded-xl text-left transition-all">
            <span class="block font-semibold text-slate-200">Health Check</span>
            <span class="text-[10px] text-slate-500 block mt-0.5">Public Endpoint</span>
          </button>

          <button id="btn-call-student" class="p-3 bg-sky-950/30 hover:bg-sky-950/50 border border-sky-800/40 hover:border-sky-700 rounded-xl text-left transition-all">
            <span class="block font-semibold text-sky-300">STUDENT_PARENT</span>
            <span class="text-[10px] text-sky-500 block mt-0.5">Protected Endpoint</span>
          </button>

          <button id="btn-call-tutor" class="p-3 bg-emerald-950/30 hover:bg-emerald-950/50 border border-emerald-800/40 hover:border-emerald-700 rounded-xl text-left transition-all">
            <span class="block font-semibold text-emerald-300">TUTOR</span>
            <span class="text-[10px] text-emerald-500 block mt-0.5">Protected Endpoint</span>
          </button>

          <button id="btn-call-manager" class="p-3 bg-purple-950/30 hover:bg-purple-950/50 border border-purple-800/40 hover:border-purple-700 rounded-xl text-left transition-all">
            <span class="block font-semibold text-purple-300">MANAGER</span>
            <span class="text-[10px] text-purple-500 block mt-0.5">Protected Endpoint</span>
          </button>
        </div>

        <!-- Live Response Output -->
        <div class="mt-4">
          <div class="flex items-center justify-between text-xs text-slate-400 mb-1 px-1">
            <span class="font-medium">API Response Inspector</span>
            <div class="flex items-center space-x-2">
              <span id="response-status-badge" class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-900 text-slate-400 border border-slate-800">
                STATUS: READY
              </span>
              <span id="response-request-id" class="font-mono text-[10px] text-slate-500 hidden truncate max-w-[200px]"></span>
            </div>
          </div>
          <pre id="api-output" class="bg-slate-950 p-4 rounded-xl border border-slate-800/80 text-xs font-mono text-emerald-400 overflow-x-auto max-h-56 select-all">// Click one of the test buttons above to trigger a Cloud Function request.</pre>
        </div>
      </div>

    </div>
  `;

  // Attach interactive listeners
  attachEventListeners();
}

function displayOutput(status, data, requestId) {
  const outputEl = document.getElementById('api-output');
  const statusBadge = document.getElementById('response-status-badge');
  const reqIdEl = document.getElementById('response-request-id');

  if (outputEl) {
    outputEl.textContent = JSON.stringify(data, null, 2);
    if (status >= 200 && status < 300) {
      outputEl.className =
        'bg-slate-950 p-4 rounded-xl border border-slate-800/80 text-xs font-mono text-emerald-400 overflow-x-auto max-h-56 select-all';
    } else {
      outputEl.className =
        'bg-slate-950 p-4 rounded-xl border border-rose-900/40 text-xs font-mono text-rose-400 overflow-x-auto max-h-56 select-all';
    }
  }

  if (statusBadge) {
    statusBadge.textContent = `HTTP ${status}`;
    statusBadge.className = `px-2 py-0.5 rounded text-[10px] font-mono border ${
      status >= 200 && status < 300
        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
        : 'bg-rose-500/10 text-rose-400 border-rose-500/30'
    }`;
  }

  if (reqIdEl) {
    if (requestId) {
      reqIdEl.textContent = `req: ${requestId}`;
      reqIdEl.classList.remove('hidden');
    } else {
      reqIdEl.classList.add('hidden');
    }
  }
}

function attachEventListeners() {
  // Subscribe to Auth state
  subscribeToAuthState(state => {
    const { user, role, isVerified, loading } = state;

    const stateText = document.getElementById('auth-state-text');
    const badgeDot = document.getElementById('auth-badge-dot');
    const actionsGroup = document.getElementById('auth-actions-group');
    const userDetailsPanel = document.getElementById('user-details-panel');
    const unverifiedBanner = document.getElementById('unverified-banner');
    const userEmail = document.getElementById('user-email');
    const userUid = document.getElementById('user-uid');
    const userRoleBadge = document.getElementById('user-role-badge');
    const emailVerifiedStatus = document.getElementById('email-verified-status');

    if (loading) {
      if (stateText) stateText.textContent = 'Verifying authentication state...';
      return;
    }

    if (!user) {
      if (stateText) stateText.textContent = 'Signed Out (Unauthenticated)';
      if (badgeDot) badgeDot.className = 'w-3 h-3 rounded-full bg-slate-500';
      if (actionsGroup) actionsGroup.classList.add('hidden');
      if (userDetailsPanel) userDetailsPanel.classList.add('hidden');
      if (unverifiedBanner) unverifiedBanner.classList.add('hidden');
    } else {
      if (stateText) stateText.textContent = `Signed In as ${user.displayName || user.email}`;
      if (badgeDot) badgeDot.className = 'w-3 h-3 rounded-full bg-emerald-400';
      if (actionsGroup) actionsGroup.classList.remove('hidden');
      if (userDetailsPanel) userDetailsPanel.classList.remove('hidden');

      if (userEmail) userEmail.textContent = user.email || '(No email)';
      if (userUid) userUid.textContent = `UID: ${user.uid}`;

      if (userRoleBadge) {
        userRoleBadge.textContent = role || 'NO ROLE CLAIM';
        if (role === 'MANAGER') {
          userRoleBadge.className =
            'px-2 py-0.5 rounded font-mono font-bold uppercase bg-purple-500/20 text-purple-300 border border-purple-500/40';
        } else if (role === 'TUTOR') {
          userRoleBadge.className =
            'px-2 py-0.5 rounded font-mono font-bold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
        } else if (role === 'STUDENT_PARENT') {
          userRoleBadge.className =
            'px-2 py-0.5 rounded font-mono font-bold uppercase bg-sky-500/20 text-sky-300 border border-sky-500/40';
        } else {
          userRoleBadge.className =
            'px-2 py-0.5 rounded font-mono font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40';
        }
      }

      if (emailVerifiedStatus) {
        if (isVerified) {
          emailVerifiedStatus.innerHTML = `
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span class="text-emerald-400 font-medium">Verified</span>
          `;
          if (unverifiedBanner) unverifiedBanner.classList.add('hidden');
        } else {
          emailVerifiedStatus.innerHTML = `
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            <span class="text-amber-300 font-medium">Unverified</span>
          `;
          if (unverifiedBanner) unverifiedBanner.classList.remove('hidden');
        }
      }
    }
  });

  // Register Form
  const formRegister = document.getElementById('form-register');
  if (formRegister) {
    formRegister.addEventListener('submit', async e => {
      e.preventDefault();
      const email = document.getElementById('reg-email')?.value?.trim();
      const password = document.getElementById('reg-password')?.value;
      const displayName = document.getElementById('reg-name')?.value?.trim();
      const roleRadio = document.querySelector('input[name="reg-role"]:checked');
      const requestedRole = roleRadio ? roleRadio.value : 'STUDENT_PARENT';

      try {
        const result = await registerWithEmailPassword(email, password, requestedRole, displayName);
        displayOutput(200, result.registration, 'registration-client');
      } catch (err) {
        displayOutput(400, { error: { message: err.message } }, 'registration-error');
      }
    });
  }

  // Malicious / Forbidden Manager self-registration test
  const btnTestMalicious = document.getElementById('btn-test-malicious-manager-reg');
  if (btnTestMalicious) {
    btnTestMalicious.addEventListener('click', async () => {
      try {
        // We bypass frontend typing and attempt to send requestedRole: "MANAGER" to the backend
        const regResult = await callFunction('registerUser', {
          method: 'POST',
          body: {
            requestedRole: 'MANAGER',
            displayName: 'Malicious Attempt',
          },
        });
        displayOutput(regResult.status, regResult.data, regResult.requestId);
      } catch (err) {
        displayOutput(400, { error: { message: err.message } }, 'security-test');
      }
    });
  }

  // Sign In Form
  const formLogin = document.getElementById('form-login');
  if (formLogin) {
    formLogin.addEventListener('submit', async e => {
      e.preventDefault();
      const email = document.getElementById('login-email')?.value?.trim();
      const password = document.getElementById('login-password')?.value;

      try {
        const credential = await loginWithEmailPassword(email, password);
        displayOutput(
          200,
          {
            message: 'Signed in successfully',
            uid: credential.user.uid,
            email: credential.user.email,
          },
          'login'
        );
      } catch (err) {
        displayOutput(401, { error: { message: err.message } }, 'login-error');
      }
    });
  }

  // Google Sign-In
  const btnGoogleLogin = document.getElementById('btn-google-login');
  if (btnGoogleLogin) {
    btnGoogleLogin.addEventListener('click', async () => {
      try {
        const credential = await loginWithGoogle('STUDENT_PARENT');
        displayOutput(
          200,
          {
            message: 'Google authentication successful',
            uid: credential.user.uid,
            email: credential.user.email,
          },
          'google-auth'
        );
      } catch (err) {
        displayOutput(400, { error: { message: err.message } }, 'google-error');
      }
    });
  }

  // Forgot Password
  const btnForgotPassword = document.getElementById('btn-forgot-password');
  if (btnForgotPassword) {
    btnForgotPassword.addEventListener('click', async () => {
      const email = prompt('Enter your registered email address to receive a password reset link:');
      if (email) {
        try {
          await sendPasswordReset(email.trim());
          displayOutput(
            200,
            { message: `Password reset email dispatched to ${email}` },
            'reset-password'
          );
        } catch (err) {
          displayOutput(400, { error: { message: err.message } }, 'reset-error');
        }
      }
    });
  }

  // Resend Email Verification
  const btnResendVerification = document.getElementById('btn-resend-verification');
  if (btnResendVerification) {
    btnResendVerification.addEventListener('click', async () => {
      try {
        await sendVerificationEmail();
        displayOutput(
          200,
          { message: 'Verification email sent. Please check your inbox.' },
          'email-verify'
        );
      } catch (err) {
        displayOutput(400, { error: { message: err.message } }, 'verify-error');
      }
    });
  }

  // Check / Reload User State
  const btnReloadUser = document.getElementById('btn-reload-user');
  if (btnReloadUser) {
    btnReloadUser.addEventListener('click', async () => {
      const user = await reloadUserState();
      displayOutput(200, { emailVerified: user?.emailVerified, uid: user?.uid }, 'reload-user');
    });
  }

  // Refresh Token Button
  const btnRefreshToken = document.getElementById('btn-refresh-token');
  if (btnRefreshToken) {
    btnRefreshToken.addEventListener('click', async () => {
      await refreshToken();
      displayOutput(
        200,
        { message: 'ID Token and Custom Claims refreshed successfully' },
        'refresh-token'
      );
    });
  }

  // Logout Button
  const btnLogout = document.getElementById('btn-logout');
  if (btnLogout) {
    btnLogout.addEventListener('click', async () => {
      await logoutUser();
      displayOutput(200, { message: 'User logged out' }, 'logout');
    });
  }

  // Protected Call Buttons
  const btnCallHealth = document.getElementById('btn-call-health');
  if (btnCallHealth) {
    btnCallHealth.addEventListener('click', async () => {
      const res = await callFunction('healthCheck', { method: 'GET' });
      displayOutput(res.status, res.data, res.requestId);
    });
  }

  const btnCallStudent = document.getElementById('btn-call-student');
  if (btnCallStudent) {
    btnCallStudent.addEventListener('click', async () => {
      const res = await callFunction('protectedStudentParentExample', { method: 'GET' });
      displayOutput(res.status, res.data, res.requestId);
    });
  }

  const btnCallTutor = document.getElementById('btn-call-tutor');
  if (btnCallTutor) {
    btnCallTutor.addEventListener('click', async () => {
      const res = await callFunction('protectedTutorExample', { method: 'GET' });
      displayOutput(res.status, res.data, res.requestId);
    });
  }

  const btnCallManager = document.getElementById('btn-call-manager');
  if (btnCallManager) {
    btnCallManager.addEventListener('click', async () => {
      const res = await callFunction('protectedManagerExample', { method: 'GET' });
      displayOutput(res.status, res.data, res.requestId);
    });
  }
}
