/**
 * UK Tutoring Platform - Cloud Functions Entry Point
 * Foundation Phase (F01)
 *
 * All business logic, tutor workflow, bookings, payments, and authentication logic
 * belong to subsequent implementation phases.
 */

import { initializeApp } from 'firebase-admin/app';
import { onRequest } from 'firebase-functions/v2/https';
import { setGlobalOptions } from 'firebase-functions/v2';
import { DEFAULT_REGION, config } from './config';
import { wrapHttpFunction } from './middleware';
import { sendSuccess } from './helpers/response';

// Initialize Firebase Admin SDK
initializeApp();

// Set global options for Cloud Functions 2nd Gen
setGlobalOptions({
  region: DEFAULT_REGION,
  maxInstances: 10,
});

/**
 * Foundation Health Check Endpoint
 * Used strictly for verifying 2nd Gen runtime, europe-west2 region,
 * and standard request ID / response formatting.
 */
export const healthCheck = onRequest(
  {
    region: DEFAULT_REGION,
    cors: true,
  },
  wrapHttpFunction((_req, res, context) => {
    sendSuccess(res, {
      status: 'healthy',
      region: DEFAULT_REGION,
      env: config.env,
      version: '0.1.0-f01',
      timestamp: context.timestamp,
    });
  })
);

// Export shared helpers, config, types, and middleware for subsequent phases
export * from './config';
export * from './helpers';
export * from './middleware';
export * from './types';
