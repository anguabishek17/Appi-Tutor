/**
 * UK Tutoring Platform - Client Authentication Service
 * Authentication Phase (F02)
 */

import {
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
  signOut,
  sendEmailVerification,
  sendPasswordResetEmail,
  onIdTokenChanged,
  updateProfile,
  getIdTokenResult,
} from 'firebase/auth';
import { auth } from '../firebase/config.js';
import { callFunction } from './api.js';

const googleProvider = new GoogleAuthProvider();

/**
 * Registers a new user with email and password, creates the server-side role and user record,
 * and sends an email verification link.
 *
 * @param {string} email
 * @param {string} password
 * @param {'STUDENT_PARENT' | 'TUTOR'} requestedRole
 * @param {string} [displayName]
 */
export async function registerWithEmailPassword(email, password, requestedRole, displayName = '') {
  // 1. Create user in Firebase Authentication
  const userCredential = await createUserWithEmailAndPassword(auth, email, password);
  const user = userCredential.user;

  // 2. Set display name in Firebase Auth profile if provided
  if (displayName) {
    await updateProfile(user, { displayName });
  }

  // 3. Trigger email verification
  try {
    await sendEmailVerification(user);
  } catch (err) {
    console.warn('Failed to send email verification during registration:', err);
  }

  // 4. Call server-side registration function to enforce role allowlist and create Firestore record
  const token = await user.getIdToken(true);
  const regResult = await callFunction('registerUser', {
    method: 'POST',
    token,
    body: {
      requestedRole,
      displayName: displayName || email.split('@')[0],
    },
  });

  if (!regResult.ok) {
    console.error('Server-side registration error:', regResult.data);
    throw new Error(regResult.data?.error?.message || 'Server-side onboarding failed');
  }

  // 5. Force refresh the ID token so custom claims (role) are reflected locally
  await user.getIdToken(true);

  return { user, registration: regResult.data };
}

/**
 * Signs in an existing user with email and password.
 */
export async function loginWithEmailPassword(email, password) {
  const credential = await signInWithEmailAndPassword(auth, email, password);
  // Force token refresh to ensure latest claims
  await credential.user.getIdToken(true);
  return credential;
}

/**
 * Signs in with Google OAuth popup.
 * If the user is new / does not have custom claims role yet, registers them with the requested role.
 *
 * @param {'STUDENT_PARENT' | 'TUTOR'} [requestedRole='STUDENT_PARENT']
 */
export async function loginWithGoogle(requestedRole = 'STUDENT_PARENT') {
  const credential = await signInWithPopup(auth, googleProvider);
  const user = credential.user;

  // Check if role custom claims are already assigned
  const tokenResult = await getIdTokenResult(user, true);

  if (!tokenResult.claims.role) {
    // Complete onboarding on server
    const token = await user.getIdToken();
    await callFunction('registerUser', {
      method: 'POST',
      token,
      body: {
        requestedRole,
        displayName: user.displayName || user.email?.split('@')[0] || 'Google User',
      },
    });

    // Force ID token refresh after claims assignment
    await user.getIdToken(true);
  }

  return credential;
}

/**
 * Signs out the current authenticated user.
 */
export async function logoutUser() {
  await signOut(auth);
}

/**
 * Triggers an email verification link to the current user.
 */
export async function sendVerificationEmail() {
  if (!auth.currentUser) {
    throw new Error('No authenticated user found');
  }
  await sendEmailVerification(auth.currentUser);
}

/**
 * Sends a password reset email via Firebase Auth.
 */
export async function sendPasswordReset(email) {
  if (!email || !email.includes('@')) {
    throw new Error('A valid email address is required');
  }
  await sendPasswordResetEmail(auth, email);
}

/**
 * Forces an ID token refresh to pull updated custom claims from the backend.
 */
export async function refreshToken() {
  if (!auth.currentUser) return null;
  return await auth.currentUser.getIdToken(true);
}

/**
 * Reloads the user's Firebase Auth profile to check for verified email status.
 */
export async function reloadUserState() {
  if (!auth.currentUser) return null;
  await auth.currentUser.reload();
  await auth.currentUser.getIdToken(true);
  return auth.currentUser;
}

/**
 * Returns the current Firebase Auth user or null.
 */
export function getCurrentUser() {
  return auth.currentUser;
}

/**
 * Observes authentication and ID token changes with parsed role and claims.
 *
 * @param {(state: {
 *   user: import('firebase/auth').User | null,
 *   role: string | null,
 *   isVerified: boolean,
 *   loading: boolean
 * }) => void} callback
 * @returns {import('firebase/auth').Unsubscribe}
 */
export function subscribeToAuthState(callback) {
  return onIdTokenChanged(auth, async user => {
    if (!user) {
      callback({
        user: null,
        role: null,
        isVerified: false,
        loading: false,
      });
      return;
    }

    try {
      const tokenResult = await getIdTokenResult(user);
      const role = tokenResult.claims.role || null;

      callback({
        user,
        role,
        isVerified: Boolean(user.emailVerified),
        loading: false,
      });
    } catch {
      callback({
        user,
        role: null,
        isVerified: Boolean(user.emailVerified),
        loading: false,
      });
    }
  });
}
