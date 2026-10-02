/**
 * UK Tutoring Platform - Payments & Refunds Endpoints
 * Phase F06 — Payments & Refunds
 */

import { Request, Response } from 'express';
import { z } from 'zod';
import { getAdminFirestore } from '../config';
import { AuthenticatedRequest } from '../middleware/auth';
import { sendSuccess, sendError } from '../helpers/response';
import { appLogger } from '../helpers/logger';
import { defaultPaymentProvider, PaymentProvider } from '../services/paymentProvider';
import { isValidPaymentTransition } from '../helpers/paymentValidator';

import {
  ErrorCode,
  PaymentDocument,
  RefundDocument,
  PaymentEventDocument,
  IdempotencyKeyDocument,
  BookingDocument,
  TutorProfileDocument,
  AuditLogDocument,
  PaymentStatus,
} from '../types';

// Active payment provider instance (configurable / injectable for test abstraction)
let activePaymentProvider: PaymentProvider = defaultPaymentProvider;

export function setPaymentProvider(provider: PaymentProvider) {
  activePaymentProvider = provider;
}

export function getPaymentProvider(): PaymentProvider {
  return activePaymentProvider;
}

// ============================================================================
// ZOD SCHEMAS FOR VALIDATION
// ============================================================================

export const CreatePaymentSchema = z.object({
  bookingId: z.string().min(1, 'bookingId is required'),
  idempotencyKey: z.string().optional().nullable(),
});

export const ConfirmPaymentSchema = z.object({
  paymentId: z.string().min(1, 'paymentId is required'),
});

export const ProcessRefundSchema = z.object({
  paymentId: z.string().min(1, 'paymentId is required'),
  amount: z
    .number()
    .int('Amount must be an integer minor unit')
    .positive('Amount must be positive')
    .optional(),
  reason: z.string().optional(),
});

export const ManagerRefundSchema = z.object({
  paymentId: z.string().min(1, 'paymentId is required'),
  amount: z
    .number()
    .int('Amount must be an integer minor unit')
    .positive('Amount must be positive')
    .optional(),
  reason: z.string().optional(),
});

// ============================================================================
// ENDPOINTS IMPLEMENTATION
// ============================================================================

/**
 * Endpoint: createPayment
 * Server-authoritative payment initiation with idempotency support.
 */
export async function createPayment(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const studentUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  log.info('Executing createPayment', { studentUid });

  const parseResult = CreatePaymentSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid payment payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { bookingId, idempotencyKey } = parseResult.data;
  const db = getAdminFirestore();

  try {
    // 1. Idempotency Check
    if (idempotencyKey) {
      const keyRef = db.collection('idempotencyKeys').doc(idempotencyKey);
      const keySnap = await keyRef.get();
      if (keySnap.exists) {
        const cached = keySnap.data() as IdempotencyKeyDocument;
        log.info('Idempotent payment request recognized', {
          idempotencyKey,
          paymentId: cached.paymentId,
        });
        sendSuccess(res, cached.response);
        return;
      }
    }

    // 2. Read Booking & Verify Ownership
    const bookingRef = db.collection('bookings').doc(bookingId);
    const bookingSnap = await bookingRef.get();

    if (!bookingSnap.exists) {
      sendError(res, ErrorCode.BOOKING_NOT_FOUND, 'Booking not found', requestId, 404);
      return;
    }

    const bookingData = bookingSnap.data() as BookingDocument;

    if (bookingData.studentUid !== studentUid) {
      log.warn('Unauthorized payment attempt for booking', {
        studentUid,
        bookingOwner: bookingData.studentUid,
      });
      sendError(
        res,
        ErrorCode.PAYMENT_UNAUTHORIZED,
        'Cannot initiate payment for a booking belonging to another user',
        requestId,
        403
      );
      return;
    }

    // 3. Derive Authoritative Amount Server-Side (Integer Minor Units in Pence)
    const tutorSnap = await db.collection('tutorProfiles').doc(bookingData.tutorUid).get();
    const tutorData = tutorSnap.exists ? (tutorSnap.data() as TutorProfileDocument) : null;
    const hourlyRatePence = tutorData?.hourlyRatePence || 3500; // Default 3500p (£35.00)

    // Calculate duration in hours
    let durationHours = 1;
    if (bookingData.startAt && bookingData.endAt) {
      const startMs = new Date(bookingData.startAt as string).getTime();
      const endMs = new Date(bookingData.endAt as string).getTime();
      if (!isNaN(startMs) && !isNaN(endMs) && endMs > startMs) {
        durationHours = Math.max(0.5, (endMs - startMs) / (1000 * 60 * 60));
      }
    }

    const authoritativeAmountPence = Math.round(hourlyRatePence * durationHours);
    const currency = 'GBP';

    // 4. Create Payment Intent via Provider Abstraction
    const provider = getPaymentProvider();
    const intentResult = await provider.createPaymentIntent({
      amount: authoritativeAmountPence,
      currency,
      bookingId,
      metadata: { studentUid, tutorUid: bookingData.tutorUid },
    });

    const paymentRef = db.collection('payments').doc();
    const paymentId = paymentRef.id;
    const now = new Date().toISOString();

    const paymentDoc: PaymentDocument = {
      id: paymentId,
      paymentId,
      bookingId,
      studentId: studentUid,
      studentUid,
      parentId: bookingData.parentId || studentUid,
      tutorId: bookingData.tutorUid,
      tutorUid: bookingData.tutorUid,
      amount: authoritativeAmountPence,
      currency,
      status: 'PENDING',
      provider: provider.name,
      providerPaymentId: intentResult.providerPaymentId,
      idempotencyKey: idempotencyKey || null,
      refundedAmount: 0,
      createdAt: now,
      updatedAt: now,
    };

    // 5. Transactional Write Payment & Audit Record
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      transaction.set(paymentRef, paymentDoc);

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'PAYMENT_CREATED',
        actorUid: studentUid,
        targetUid: bookingData.tutorUid,
        details: { paymentId, bookingId, amount: authoritativeAmountPence, currency },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);

      if (idempotencyKey) {
        const keyRef = db.collection('idempotencyKeys').doc(idempotencyKey);
        const keyDoc: IdempotencyKeyDocument = {
          key: idempotencyKey,
          paymentId,
          response: { message: 'Payment created successfully', payment: paymentDoc },
          createdAt: now,
        };
        transaction.set(keyRef, keyDoc);
      }
    });

    log.info('Payment created successfully', {
      paymentId,
      bookingId,
      amount: authoritativeAmountPence,
    });
    sendSuccess(res, {
      message: 'Payment created successfully',
      payment: paymentDoc,
      clientSecret: intentResult.clientSecret,
    });
  } catch (error) {
    log.warn('Error in createPayment', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to create payment', requestId, 500);
  }
}

/**
 * Endpoint: confirmPayment
 * Confirms/captures a pending payment via server provider call.
 */
export async function confirmPayment(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = ConfirmPaymentSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid confirmation payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { paymentId } = parseResult.data;
  const db = getAdminFirestore();

  try {
    let updatedPayment: PaymentDocument | null = null;

    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const paymentRef = db.collection('payments').doc(paymentId);
      const paymentSnap = await transaction.get(paymentRef);

      if (!paymentSnap.exists) {
        throw {
          code: ErrorCode.PAYMENT_NOT_FOUND,
          status: 404,
          message: 'Payment record not found',
        };
      }

      const payment = paymentSnap.data() as PaymentDocument;

      if (payment.studentId !== uid && req.context.role !== 'MANAGER') {
        throw {
          code: ErrorCode.PAYMENT_UNAUTHORIZED,
          status: 403,
          message: 'Unauthorized to confirm this payment',
        };
      }

      if (!isValidPaymentTransition(payment.status, 'SUCCEEDED')) {
        throw {
          code: ErrorCode.PAYMENT_INVALID_STATE_TRANSITION,
          status: 400,
          message: `Cannot transition payment from ${payment.status} to SUCCEEDED`,
        };
      }

      const provider = getPaymentProvider();
      const captureResult = await provider.capturePayment(payment.providerPaymentId);

      if (captureResult.status !== 'SUCCEEDED') {
        throw {
          code: ErrorCode.PAYMENT_PROVIDER_ERROR,
          status: 400,
          message: 'Payment provider failed to capture payment',
        };
      }

      const now = new Date().toISOString();
      transaction.update(paymentRef, {
        status: 'SUCCEEDED',
        updatedAt: now,
      });

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'PAYMENT_SUCCEEDED',
        actorUid: uid,
        targetUid: payment.tutorId,
        details: {
          paymentId: payment.paymentId,
          bookingId: payment.bookingId,
          amount: payment.amount,
        },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);

      updatedPayment = {
        ...payment,
        status: 'SUCCEEDED',
        updatedAt: now,
      };
    });

    log.info('Payment confirmed successfully', { paymentId });
    sendSuccess(res, {
      message: 'Payment confirmed successfully',
      payment: updatedPayment,
    });
  } catch (error: unknown) {
    log.warn('Error in confirmPayment', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to confirm payment', requestId, 500);
    }
  }
}

/**
 * Endpoint: getPayment
 * Returns a payment document if caller is authorized.
 */
export async function getPayment(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const role = req.context.role;
  const paymentId = req.params.paymentId || (req.query.paymentId as string);
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  if (!paymentId) {
    sendError(res, ErrorCode.VALIDATION_ERROR, 'paymentId parameter is required', requestId, 400);
    return;
  }

  try {
    const paymentSnap = await db.collection('payments').doc(paymentId).get();

    if (!paymentSnap.exists) {
      sendError(res, ErrorCode.PAYMENT_NOT_FOUND, 'Payment not found', requestId, 404);
      return;
    }

    const payment = paymentSnap.data() as PaymentDocument;

    const isStudent = payment.studentId === uid || payment.studentUid === uid;
    const isTutor = payment.tutorId === uid || payment.tutorUid === uid;
    const isManager = role === 'MANAGER';

    if (!isStudent && !isTutor && !isManager) {
      sendError(
        res,
        ErrorCode.PAYMENT_UNAUTHORIZED,
        'Unauthorized to view this payment',
        requestId,
        403
      );
      return;
    }

    sendSuccess(res, { payment });
  } catch (error) {
    log.warn('Error in getPayment', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve payment', requestId, 500);
  }
}

/**
 * Endpoint: getMyPayments
 * Returns payment history for current user.
 */
export async function getMyPayments(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const role = req.context.role;
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  try {
    let query: FirebaseFirestore.Query = db.collection('payments');

    if (role === 'TUTOR') {
      query = query.where('tutorId', '==', uid);
    } else if (role === 'STUDENT_PARENT') {
      query = query.where('studentId', '==', uid);
    } else if (role === 'MANAGER') {
      // Manager lists all
    } else {
      query = query.where('studentId', '==', uid);
    }

    const snapshot = await query.get();
    const payments = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as PaymentDocument
    );

    sendSuccess(res, { payments });
  } catch (error) {
    log.warn('Error in getMyPayments', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve payments', requestId, 500);
  }
}

/**
 * Endpoint: processRefund
 * Allows student (for own succeeded payment) or manager to issue full or partial refund.
 */
export async function processRefund(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const userRole = req.context.role;
  const log = appLogger.forRequest(requestId);

  const parseResult = ProcessRefundSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid refund payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { paymentId, amount: requestedAmount, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    let createdRefund: RefundDocument | null = null;

    const finalRefund = await db.runTransaction(
      async (transaction: FirebaseFirestore.Transaction) => {
        const paymentRef = db.collection('payments').doc(paymentId);
        const paymentSnap = await transaction.get(paymentRef);

        if (!paymentSnap.exists) {
          throw {
            code: ErrorCode.PAYMENT_NOT_FOUND,
            status: 404,
            message: 'Payment record not found',
          };
        }

        const payment = paymentSnap.data() as PaymentDocument;

        const isStudent = payment.studentId === uid || payment.studentUid === uid;
        const isManager = userRole === 'MANAGER';

        if (!isStudent && !isManager) {
          throw {
            code: ErrorCode.REFUND_UNAUTHORIZED,
            status: 403,
            message: 'Unauthorized to request refund for this payment',
          };
        }

        if (
          payment.status === 'REFUNDED' ||
          (payment.refundedAmount && payment.refundedAmount >= payment.amount)
        ) {
          throw {
            code: ErrorCode.REFUND_ALREADY_PROCESSED,
            status: 400,
            message: 'Payment has already been fully refunded',
          };
        }

        if (payment.status !== 'SUCCEEDED' && payment.status !== 'PARTIALLY_REFUNDED') {
          throw {
            code: ErrorCode.REFUND_NOT_ALLOWED,
            status: 400,
            message: `Cannot refund payment in status: ${payment.status}`,
          };
        }

        const currentRefundedAmount = payment.refundedAmount || 0;
        const remainingBalance = payment.amount - currentRefundedAmount;

        if (remainingBalance <= 0) {
          throw {
            code: ErrorCode.REFUND_ALREADY_PROCESSED,
            status: 400,
            message: 'Payment has already been fully refunded',
          };
        }

        const refundAmount = requestedAmount || remainingBalance;

        if (refundAmount <= 0 || refundAmount > remainingBalance) {
          throw {
            code: ErrorCode.REFUND_AMOUNT_INVALID,
            status: 400,
            message: `Refund amount (${refundAmount}) exceeds available balance (${remainingBalance})`,
          };
        }

        // Execute refund with provider
        const provider = getPaymentProvider();
        const refundResult = await provider.refundPayment({
          providerPaymentId: payment.providerPaymentId,
          amount: refundAmount,
          currency: payment.currency,
          reason: reason || 'User requested refund',
        });

        if (refundResult.status !== 'SUCCEEDED') {
          throw {
            code: ErrorCode.PAYMENT_PROVIDER_ERROR,
            status: 400,
            message: 'Payment provider rejected refund',
          };
        }

        const refundRef = db.collection('refunds').doc();
        const refundId = refundRef.id;
        const now = new Date().toISOString();

        const newRefundedTotal = currentRefundedAmount + refundAmount;
        const nextPaymentStatus: PaymentStatus =
          newRefundedTotal === payment.amount ? 'REFUNDED' : 'PARTIALLY_REFUNDED';

        const refundDoc: RefundDocument = {
          id: refundId,
          refundId,
          paymentId,
          bookingId: payment.bookingId,
          amount: refundAmount,
          currency: payment.currency,
          status: 'SUCCEEDED',
          reason: reason || null,
          providerRefundId: refundResult.providerRefundId,
          createdBy: uid,
          createdAt: now,
          updatedAt: now,
        };

        transaction.set(refundRef, refundDoc);
        transaction.update(paymentRef, {
          status: nextPaymentStatus,
          refundedAmount: newRefundedTotal,
          updatedAt: now,
        });

        const eventType = isManager ? 'MANAGER_REFUND_ACTION' : 'REFUND_SUCCEEDED';
        const auditRef = db.collection('auditLogs').doc(requestId);
        const auditDoc: AuditLogDocument = {
          id: requestId,
          eventType,
          actorUid: uid,
          targetUid: payment.studentId,
          details: { refundId, paymentId, amount: refundAmount, newStatus: nextPaymentStatus },
          timestamp: now,
        };
        transaction.set(auditRef, auditDoc);

        createdRefund = refundDoc;
        return refundDoc;
      }
    );

    createdRefund = finalRefund;

    log.info('Refund processed successfully', { refundId: createdRefund.refundId, paymentId });
    sendSuccess(res, {
      message: 'Refund processed successfully',
      refund: createdRefund,
    });
  } catch (error: unknown) {
    log.warn('Error in processRefund', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to process refund', requestId, 500);
    }
  }
}

/**
 * Endpoint: handlePaymentWebhook
 * Verifies provider webhook signature and processes events idempotently.
 */
export async function handlePaymentWebhook(req: Request, res: Response): Promise<void> {
  const requestId = (req.headers['x-request-id'] as string) || `wh_${Date.now()}`;
  const log = appLogger.forRequest(requestId);
  const signature =
    (req.headers['x-webhook-signature'] as string) || (req.headers['signature'] as string) || '';
  const webhookSecret = process.env.PAYMENT_WEBHOOK_SECRET || 'mock_webhook_secret';

  log.info('Received payment webhook notification');

  const provider = getPaymentProvider();
  const rawPayload = JSON.stringify(req.body);

  const isValidSig = provider.verifyWebhookSignature(rawPayload, signature, webhookSecret);
  if (!isValidSig) {
    log.warn('Webhook signature verification failed', { signature });
    sendError(res, ErrorCode.PAYMENT_WEBHOOK_INVALID, 'Invalid webhook signature', requestId, 400);
    return;
  }

  const { providerEventId, providerPaymentId, eventType } = req.body;

  if (!providerEventId) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Missing providerEventId in payload',
      requestId,
      400
    );
    return;
  }

  const db = getAdminFirestore();

  try {
    let alreadyProcessed = false;

    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const eventRef = db.collection('paymentEvents').doc(providerEventId);
      const eventSnap = await transaction.get(eventRef);

      if (eventSnap.exists) {
        alreadyProcessed = true;
        return;
      }

      const now = new Date().toISOString();

      if (
        providerPaymentId &&
        (eventType === 'payment_intent.succeeded' || eventType === 'charge.succeeded')
      ) {
        const snapshot = await db
          .collection('payments')
          .where('providerPaymentId', '==', providerPaymentId)
          .get();

        if (!snapshot.empty) {
          const paymentDoc = snapshot.docs[0];
          const paymentData = paymentDoc.data() as PaymentDocument;

          if (isValidPaymentTransition(paymentData.status, 'SUCCEEDED')) {
            transaction.update(db.collection('payments').doc(paymentDoc.id), {
              status: 'SUCCEEDED',
              updatedAt: now,
            });
          }
        }
      }

      const eventDoc: PaymentEventDocument = {
        id: providerEventId,
        provider: provider.name,
        providerEventId,
        paymentId: providerPaymentId || null,
        eventType: eventType || 'unknown',
        receivedAt: now,
        processedAt: now,
        status: 'PROCESSED',
      };
      transaction.set(eventRef, eventDoc);
    });

    if (alreadyProcessed) {
      log.info('Duplicate webhook event ignored safely', { providerEventId });
      sendSuccess(res, { message: 'Duplicate webhook event ignored safely', providerEventId });
      return;
    }

    log.info('Webhook event processed successfully', { providerEventId });
    sendSuccess(res, { message: 'Webhook event processed successfully', providerEventId });
  } catch (error) {
    log.warn('Error processing webhook', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Webhook processing failed', requestId, 500);
  }
}

/**
 * Endpoint: managerListPayments
 * Allows MANAGER to list all platform payments and refunds.
 */
export async function managerListPayments(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const log = appLogger.forRequest(requestId);

  if (req.context.role !== 'MANAGER') {
    sendError(res, ErrorCode.FORBIDDEN, 'Manager access required', requestId, 403);
    return;
  }

  const db = getAdminFirestore();

  try {
    const paymentsSnap = await db.collection('payments').get();
    const refundsSnap = await db.collection('refunds').get();

    const payments = paymentsSnap.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as PaymentDocument
    );
    const refunds = refundsSnap.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as RefundDocument
    );

    sendSuccess(res, { payments, refunds });
  } catch (error) {
    log.warn('Error in managerListPayments', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to list payments', requestId, 500);
  }
}
