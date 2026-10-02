/**
 * UK Tutoring Platform - Payment & Refund State Transition Validator
 * Phase F06 — Payments & Refunds
 */

import { PaymentStatus, RefundStatus } from '../types';

/**
 * Validates allowed payment lifecycle transitions according to F06 specification.
 *
 * Allowed transitions:
 * PENDING -> SUCCEEDED, FAILED, CANCELLED
 * SUCCEEDED -> REFUNDED, PARTIALLY_REFUNDED
 * PARTIALLY_REFUNDED -> REFUNDED
 * Terminal states (FAILED, CANCELLED, REFUNDED) cannot transition further.
 */
export function isValidPaymentTransition(
  currentStatus: PaymentStatus,
  nextStatus: PaymentStatus
): boolean {
  if (currentStatus === nextStatus) return false;

  switch (currentStatus) {
    case 'PENDING':
      return nextStatus === 'SUCCEEDED' || nextStatus === 'FAILED' || nextStatus === 'CANCELLED';

    case 'SUCCEEDED':
      return nextStatus === 'REFUNDED' || nextStatus === 'PARTIALLY_REFUNDED';

    case 'PARTIALLY_REFUNDED':
      return nextStatus === 'REFUNDED';

    case 'FAILED':
    case 'CANCELLED':
    case 'REFUNDED':
      return false;

    default:
      return false;
  }
}

/**
 * Validates allowed refund lifecycle transitions according to F06 specification.
 */
export function isValidRefundTransition(
  currentStatus: RefundStatus,
  nextStatus: RefundStatus
): boolean {
  if (currentStatus === nextStatus) return false;

  switch (currentStatus) {
    case 'PENDING':
      return nextStatus === 'SUCCEEDED' || nextStatus === 'FAILED' || nextStatus === 'CANCELLED';

    case 'SUCCEEDED':
    case 'FAILED':
    case 'CANCELLED':
      return false;

    default:
      return false;
  }
}
