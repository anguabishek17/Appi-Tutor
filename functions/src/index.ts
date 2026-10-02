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
// Foundation Phase Endpoints
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
      version: '0.2.0-f02',
      timestamp: context.timestamp,
    });
  })
);

// Authentication & Authorization Endpoints (Phase F02)
export {
  registerUser,
  protectedStudentParentExample,
  protectedTutorExample,
  protectedManagerExample,
} from './endpoints/auth';

// Tutor Onboarding & Manager Approval Endpoints (Phase F04)
export {
  updateTutorProfile,
  submitDbsDocument,
  getTutorOnboardingStatus,
  managerListPendingTutors,
  managerApproveTutor,
  managerRejectTutor,
  managerSuspendTutor,
  managerReapproveTutor,
} from './endpoints/tutorOnboarding';

// Lesson Booking & Availability Endpoints (Phase F05)
export {
  createAvailabilitySlot,
  getAvailableSlots,
  updateAvailabilitySlot,
  deleteAvailabilitySlot,
  createBooking,
  getMyBookings,
  getBooking,
  getTutorBookings,
  confirmBooking,
  rejectBooking,
  proposeReschedule,
  cancelBooking,
  completeBooking,
  managerListBookings,
  managerCancelBooking,
} from './endpoints/booking';

// Payments & Refunds Endpoints (Phase F06)
export {
  createPayment,
  confirmPayment,
  getPayment,
  getMyPayments,
  processRefund,
  handlePaymentWebhook,
  managerListPayments,
} from './endpoints/payment';
export * from './services/paymentProvider';

// Export shared helpers, config, types, and middleware for subsequent phases
export * from './config';
export * from './helpers';
export * from './middleware';
export * from './types';
