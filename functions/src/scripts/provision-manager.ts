/* eslint-disable no-console */
/**
 * UK Tutoring Platform - Manager Account Provisioning Script
 * Authentication Phase (F02)

 *
 * PRIVILEGED ADMINISTRATIVE SCRIPT:
 * - This script must NEVER be exposed as an HTTP/callable endpoint.
 * - MANAGER roles can ONLY be provisioned via explicit server-side administrative execution.
 * - Do NOT hardcode credentials.
 *
 * Usage (Local / Emulator):
 *   node lib/scripts/provision-manager.js --email=manager@tutoring.co.uk --password=TempPassword123! --name="Platform Manager"
 *   OR using environment variables:
 *   MANAGER_EMAIL=manager@tutoring.co.uk MANAGER_PASSWORD=... node lib/scripts/provision-manager.js
 */

import { initializeApp, getApps } from 'firebase-admin/app';
import { getAuth } from 'firebase-admin/auth';
import { getFirestore, FieldValue } from 'firebase-admin/firestore';

export interface ProvisionOptions {
  email: string;
  password?: string;
  displayName: string;
  status: 'ACTIVE' | 'PENDING';
}

function parseArgs(): ProvisionOptions {
  const args = process.argv.slice(2);
  const parsed: Record<string, string> = {};

  for (const arg of args) {
    if (arg.startsWith('--')) {
      const [key, ...values] = arg.slice(2).split('=');
      parsed[key] = values.join('=');
    }
  }

  const email = parsed.email || process.env.MANAGER_EMAIL;
  const password = parsed.password || process.env.MANAGER_PASSWORD;
  const displayName =
    parsed.name || parsed.displayName || process.env.MANAGER_DISPLAY_NAME || 'Platform Manager';
  const status = (parsed.status as 'ACTIVE' | 'PENDING') || 'ACTIVE';

  if (!email) {
    console.error(
      'ERROR: Manager email is required. Provide --email=<email> or set MANAGER_EMAIL env var.'
    );
    process.exit(1);
  }

  return { email, password, displayName, status };
}

export async function provisionManager(
  options: ProvisionOptions
): Promise<{ uid: string; email: string }> {
  const { email, password, displayName, status } = options;

  // Initialize Firebase Admin if not already initialized
  if (getApps().length === 0) {
    if (process.env.FUNCTIONS_EMULATOR === 'true' || process.env.USE_EMULATOR === 'true') {
      process.env.FIREBASE_AUTH_EMULATOR_HOST =
        process.env.FIREBASE_AUTH_EMULATOR_HOST || '127.0.0.1:9099';
      process.env.FIRESTORE_EMULATOR_HOST = process.env.FIRESTORE_EMULATOR_HOST || '127.0.0.1:8080';
    }
    initializeApp({
      projectId:
        process.env.GCLOUD_PROJECT || process.env.VITE_FIREBASE_PROJECT_ID || 'uk-tutoring-dev',
    });
  }

  console.info(`[Provisioning] Starting Manager account provisioning for: ${email}`);

  const auth = getAuth();
  const db = getFirestore();

  let uid: string;

  try {
    const existingUser = await auth.getUserByEmail(email);
    uid = existingUser.uid;
    console.info(`[Provisioning] Found existing Firebase Auth user: ${uid}`);

    // Update display name and verification
    await auth.updateUser(uid, {
      displayName,
      emailVerified: true,
      ...(password ? { password } : {}),
    });
  } catch (err: unknown) {
    const error = err as { code?: string };
    if (error.code === 'auth/user-not-found') {
      if (!password) {
        throw new Error(
          'Password is required when creating a new manager account (--password=<password>)'
        );
      }
      const newUser = await auth.createUser({
        email,
        password,
        displayName,
        emailVerified: true,
      });
      uid = newUser.uid;
      console.info(`[Provisioning] Created new Firebase Auth user: ${uid}`);
    } else {
      throw err;
    }
  }

  // 1. Assign Authoritative Custom Claims: { role: 'MANAGER' }
  console.info(`[Provisioning] Assigning custom claim role: MANAGER to uid: ${uid}`);
  await auth.setCustomUserClaims(uid, { role: 'MANAGER' });

  // 2. Create/Update Firestore users/{uid} document
  console.info(`[Provisioning] Writing Firestore users/${uid} record`);
  const userRef = db.collection('users').doc(uid);
  const existingDoc = await userRef.get();

  if (existingDoc.exists) {
    await userRef.update({
      role: 'MANAGER',
      status,
      displayName,
      emailVerifiedAt: FieldValue.serverTimestamp(),
      updatedAt: FieldValue.serverTimestamp(),
    });
  } else {
    await userRef.set({
      uid,
      email,
      displayName,
      role: 'MANAGER',
      status,
      emailVerifiedAt: FieldValue.serverTimestamp(),
      createdAt: FieldValue.serverTimestamp(),
      updatedAt: FieldValue.serverTimestamp(),
    });
  }

  console.info(`[Provisioning] SUCCESS: Manager account provisioned successfully!`);
  console.info(`  UID: ${uid}`);
  console.info(`  Email: ${email}`);
  console.info(`  Role: MANAGER`);
  console.info(`  Status: ${status}`);

  return { uid, email };
}

// Execute CLI runner if run directly
if (process.env.NODE_ENV !== 'test' && require.main === module) {
  const options = parseArgs();
  provisionManager(options)
    .then(() => process.exit(0))
    .catch(err => {
      console.error('[Provisioning ERROR]', err);
      process.exit(1);
    });
}
