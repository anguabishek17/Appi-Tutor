/**
 * UK Tutoring Platform - Client API Service
 * Authentication Phase (F02)
 */

import { auth } from '../firebase/config.js';

// Base URL for Cloud Functions
const FUNCTIONS_HOST =
  import.meta.env.VITE_FUNCTIONS_HOST || 'http://127.0.0.1:5001/uk-tutoring-dev/europe-west2';

/**
 * Makes an HTTP request to a Cloud Function, automatically attaching the Firebase ID token if authenticated.
 */
export async function callFunction(endpointName, options = {}) {
  const { method = 'POST', body, token: explicitToken } = options;

  let token = explicitToken;
  if (!token && auth.currentUser) {
    token = await auth.currentUser.getIdToken();
  }

  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const url = `${FUNCTIONS_HOST}/${endpointName}`;

  try {
    const res = await fetch(url, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    const data = await res.json();
    return {
      ok: res.ok,
      status: res.status,
      requestId: res.headers.get('x-request-id') || data.request_id || null,
      data,
    };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      requestId: null,
      data: {
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: err.message || 'Network request failed to Cloud Function',
        },
      },
    };
  }
}
