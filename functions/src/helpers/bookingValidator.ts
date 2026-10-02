/**
 * UK Tutoring Platform - Lesson Booking State Transition Validator
 * Phase F05 — Lesson Booking, Availability & Double-Booking Prevention
 */

import { BookingStatus } from '../types';

/**
 * Validates allowed booking lifecycle transitions according to F05 specification.
 *
 * Allowed transitions:
 * PENDING -> CONFIRMED, REJECTED, RESCHEDULE_PROPOSED, CANCELLED, SYSTEM_CANCELLED
 * CONFIRMED -> COMPLETED, CANCELLED, SYSTEM_CANCELLED
 * RESCHEDULE_PROPOSED -> PENDING, CANCELLED, REJECTED, SYSTEM_CANCELLED
 * Terminal states (REJECTED, CANCELLED, COMPLETED, SYSTEM_CANCELLED) cannot transition further.
 */
export function isValidBookingTransition(
  currentStatus: BookingStatus,
  nextStatus: BookingStatus
): boolean {
  if (currentStatus === nextStatus) return false;

  switch (currentStatus) {
    case 'PENDING':
      return (
        nextStatus === 'CONFIRMED' ||
        nextStatus === 'REJECTED' ||
        nextStatus === 'RESCHEDULE_PROPOSED' ||
        nextStatus === 'CANCELLED' ||
        nextStatus === 'SYSTEM_CANCELLED'
      );

    case 'CONFIRMED':
      return (
        nextStatus === 'COMPLETED' ||
        nextStatus === 'CANCELLED' ||
        nextStatus === 'SYSTEM_CANCELLED'
      );

    case 'RESCHEDULE_PROPOSED':
      return (
        nextStatus === 'PENDING' ||
        nextStatus === 'CANCELLED' ||
        nextStatus === 'REJECTED' ||
        nextStatus === 'SYSTEM_CANCELLED'
      );

    case 'REJECTED':
    case 'CANCELLED':
    case 'COMPLETED':
    case 'SYSTEM_CANCELLED':
      return false;

    default:
      return false;
  }
}
