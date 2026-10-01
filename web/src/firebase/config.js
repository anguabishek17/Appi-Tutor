/**
 * UK Tutoring Platform - Firebase Client Configuration
 * Foundation Phase (F01)
 */

import { initializeApp } from 'firebase/app';
import { getAuth, connectAuthEmulator } from 'firebase/auth';
import { getFirestore, connectFirestoreEmulator } from 'firebase/firestore';
import { getStorage, connectStorageEmulator } from 'firebase/storage';
import { getFunctions, connectFunctionsEmulator } from 'firebase/functions';

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY || 'AIzaSyFakeDevApiKeyForLocalEmulatorOnly',
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || 'uk-tutoring-dev.firebaseapp.com',
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || 'uk-tutoring-dev',
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || 'uk-tutoring-dev.appspot.com',
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '000000000000',
  appId: import.meta.env.VITE_FIREBASE_APP_ID || '1:000000000000:web:0000000000000000000000',
};

// Initialize Firebase App
export const app = initializeApp(firebaseConfig);

// Initialize Services
export const auth = getAuth(app);
export const db = getFirestore(app);
export const storage = getStorage(app);
export const functions = getFunctions(app, 'europe-west2');

// Connect to Emulators during development or when explicitly enabled
const useEmulator = import.meta.env.DEV || import.meta.env.VITE_USE_FIREBASE_EMULATORS === 'true';

let emulatorsConnected = false;

export function initEmulators() {
  if (useEmulator && !emulatorsConnected) {
    try {
      const host = import.meta.env.VITE_EMULATOR_HOST || '127.0.0.1';
      connectAuthEmulator(auth, `http://${host}:9099`, { disableWarnings: true });
      connectFirestoreEmulator(db, host, 8080);
      connectStorageEmulator(storage, host, 9199);
      connectFunctionsEmulator(functions, host, 5001);
      emulatorsConnected = true;
    } catch {
      // Ignore re-initialization errors in hot-reloading
    }
  }
}

// Auto-initialize emulators in development
if (useEmulator) {
  initEmulators();
}
