/**
 * UK Tutoring Platform - Server-Side Role and User Helpers
 * Authentication Phase (F02)
 */

import { getAuth } from 'firebase-admin/auth';
import { getFirestore, FieldValue } from 'firebase-admin/firestore';
import { CustomClaims, UserDocument, UserRole, UserStatus } from '../types';
import { appLogger } from './logger';

/**
 * Assigns a role claim to a Firebase Auth user and updates the Firestore user record.
 * Must ONLY be executed from trusted server-side Admin SDK contexts.
 */
export async function setUserRole(uid: string, role: UserRole, requestId?: string): Promise<void> {
  const log = appLogger.forRequest(requestId || 'server-admin');
  log.info('Assigning custom claim role to user', { uid, role });

  // 1. Set Custom Claims in Firebase Authentication
  const claims: CustomClaims = { role };
  await getAuth().setCustomUserClaims(uid, claims);

  // 2. Synchronize with users/{uid} document in Firestore
  const db = getFirestore();
  const userRef = db.collection('users').doc(uid);

  await userRef.set(
    {
      role,
      updatedAt: FieldValue.serverTimestamp(),
    },
    { merge: true }
  );

  log.info('Successfully updated user role and custom claims', { uid, role });
}

/**
 * Fetches the authoritative users/{uid} document from Firestore.
 */
export async function getUserDocument(uid: string): Promise<UserDocument | null> {
  const db = getFirestore();
  const userDoc = await db.collection('users').doc(uid).get();

  if (!userDoc.exists) {
    return null;
  }

  return userDoc.data() as UserDocument;
}

/**
 * Updates application account status (e.g. PENDING -> ACTIVE).
 */
export async function setUserStatus(
  uid: string,
  status: UserStatus,
  requestId?: string
): Promise<void> {
  const log = appLogger.forRequest(requestId || 'server-admin');
  log.info('Updating user account status', { uid, status });

  const db = getFirestore();
  await db.collection('users').doc(uid).set(
    {
      status,
      updatedAt: FieldValue.serverTimestamp(),
    },
    { merge: true }
  );
}

/**
 * Creates initial users/{uid} document for a newly registered user.
 * Initial status is strictly PENDING.
 */
export async function createUserRecord(
  params: {
    uid: string;
    email: string;
    displayName?: string;
    role: UserRole;
    emailVerified?: boolean;
  },
  requestId?: string
): Promise<UserDocument> {
  const { uid, email, displayName = '', role, emailVerified = false } = params;
  const log = appLogger.forRequest(requestId || 'registration');

  log.info('Creating Firestore user record', { uid, role, emailVerified });

  const db = getFirestore();
  const userRef = db.collection('users').doc(uid);

  const newUserData = {
    uid,
    email,
    displayName,
    role,
    status: 'PENDING' as UserStatus,
    emailVerifiedAt: emailVerified ? FieldValue.serverTimestamp() : null,
    createdAt: FieldValue.serverTimestamp(),
    updatedAt: FieldValue.serverTimestamp(),
  };

  await userRef.set(newUserData);

  return {
    ...newUserData,
    emailVerifiedAt: emailVerified ? new Date().toISOString() : null,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  } as unknown as UserDocument;
}
