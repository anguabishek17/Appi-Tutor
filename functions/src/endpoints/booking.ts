/**
 * UK Tutoring Platform - Lesson Booking & Availability Endpoints
 * Phase F05 — Lesson Booking, Availability & Double-Booking Prevention
 */

import { Response } from 'express';
import { z } from 'zod';
import { getAdminFirestore } from '../config';
import { AuthenticatedRequest } from '../middleware/auth';
import { sendSuccess, sendError } from '../helpers/response';
import { appLogger } from '../helpers/logger';
import { isValidBookingTransition } from '../helpers/bookingValidator';
import {
  ErrorCode,
  AvailabilitySlotDocument,
  BookingDocument,
  BookingStatusHistoryEntry,
  TutorProfileDocument,
  AuditLogDocument,
  BookingStatus,
} from '../types';

// ============================================================================
// ZOD SCHEMAS FOR VALIDATION
// ============================================================================

export const CreateAvailabilitySlotSchema = z.object({
  startAt: z.string().min(1, 'startAt ISO timestamp is required'),
  endAt: z.string().min(1, 'endAt ISO timestamp is required'),
  timezone: z.string().optional().default('Europe/London'),
});

export const UpdateAvailabilitySlotSchema = z.object({
  slotId: z.string().min(1, 'slotId is required'),
  startAt: z.string().optional(),
  endAt: z.string().optional(),
  status: z.enum(['AVAILABLE', 'BOOKED', 'BLOCKED']).optional(),
});

export const DeleteAvailabilitySlotSchema = z.object({
  slotId: z.string().min(1, 'slotId is required'),
});

export const CreateBookingSchema = z.object({
  slotId: z.string().min(1, 'slotId is required'),
  childId: z.string().optional().nullable(),
  reason: z.string().optional(),
});

export const BookingActionSchema = z.object({
  bookingId: z.string().min(1, 'bookingId is required'),
  reason: z.string().optional(),
});

export const ManagerCancelBookingSchema = z.object({
  bookingId: z.string().min(1, 'bookingId is required'),
  reason: z.string().optional(),
});

// ============================================================================
// AVAILABILITY SLOT ENDPOINTS
// ============================================================================

/**
 * Endpoint: createAvailabilitySlot
 * Allows an approved, bookable TUTOR to create availability slots.
 */
export async function createAvailabilitySlot(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  log.info('Executing createAvailabilitySlot', { uid });

  const parseResult = CreateAvailabilitySlotSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid availability slot payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { startAt, endAt, timezone } = parseResult.data;

  // Validate ISO dates and startAt < endAt
  const startDate = new Date(startAt);
  const endDate = new Date(endAt);

  if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || startDate >= endDate) {
    sendError(
      res,
      ErrorCode.INVALID_AVAILABILITY_RANGE,
      'startAt must be before endAt and both must be valid dates',
      requestId,
      400
    );
    return;
  }

  const db = getAdminFirestore();

  try {
    // Read authoritative tutor profile record
    const tutorSnap = await db.collection('tutorProfiles').doc(uid).get();

    if (!tutorSnap.exists) {
      sendError(res, ErrorCode.TUTOR_NOT_FOUND, 'Tutor profile not found', requestId, 404);
      return;
    }

    const tutorData = tutorSnap.data() as TutorProfileDocument;

    const isApproved =
      tutorData.approvalStatus === 'APPROVED' || tutorData.onboardingStatus === 'MANAGER_APPROVED';
    const isBookable = tutorData.isBookable === true || tutorData.bookableStatus === true;
    const isPublic = tutorData.isPublic === true || tutorData.publicStatus === true;

    if (!isApproved || !isBookable || !isPublic) {
      log.warn('Unapproved or unbookable tutor attempted slot creation', {
        uid,
        approvalStatus: tutorData.approvalStatus,
        isBookable: tutorData.isBookable,
        isPublic: tutorData.isPublic,
      });
      sendError(
        res,
        ErrorCode.TUTOR_NOT_BOOKABLE,
        'Only approved and bookable tutors can create availability slots',
        requestId,
        403
      );
      return;
    }

    const slotRef = db.collection('availabilitySlots').doc();
    const slotId = slotRef.id;
    const now = new Date().toISOString();

    const slotDoc: AvailabilitySlotDocument = {
      slotId,
      tutorId: uid,
      startAt: startDate.toISOString(),
      endAt: endDate.toISOString(),
      timezone: timezone || 'Europe/London',
      status: 'AVAILABLE',
      bookingId: null,
      createdAt: now,
      updatedAt: now,
    };

    await slotRef.set(slotDoc);

    log.info('Availability slot created successfully', { slotId, tutorId: uid });
    sendSuccess(res, {
      message: 'Availability slot created successfully',
      slot: slotDoc,
    });
  } catch (error) {
    log.warn('Error in createAvailabilitySlot', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to create availability slot', requestId, 500);
  }
}

/**
 * Endpoint: getAvailableSlots
 * Returns available slots for browsing. Excludes private tutor data.
 */
export async function getAvailableSlots(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  const tutorIdFilter = req.query.tutorId as string | undefined;

  try {
    let query: FirebaseFirestore.Query = db
      .collection('availabilitySlots')
      .where('status', '==', 'AVAILABLE');

    if (tutorIdFilter) {
      query = query.where('tutorId', '==', tutorIdFilter);
    }

    const snapshot = await query.get();
    const rawSlots = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as AvailabilitySlotDocument
    );

    // Fetch tutor profiles to verify bookable status
    const tutorIds = Array.from(new Set(rawSlots.map(s => s.tutorId)));
    const bookableTutorIds = new Set<string>();

    for (const tid of tutorIds) {
      const tutorSnap = await db.collection('tutorProfiles').doc(tid).get();
      if (tutorSnap.exists) {
        const tData = tutorSnap.data() as TutorProfileDocument;
        const isApproved =
          tData.approvalStatus === 'APPROVED' || tData.onboardingStatus === 'MANAGER_APPROVED';
        const isBookable = tData.isBookable === true || tData.bookableStatus === true;
        const isPublic = tData.isPublic === true || tData.publicStatus === true;
        if (isApproved && isBookable && isPublic) {
          bookableTutorIds.add(tid);
        }
      }
    }

    // Filter slots to only include bookable tutors and sanitize output (no private data)
    const availableSlots = rawSlots
      .filter(s => bookableTutorIds.has(s.tutorId))
      .map(s => ({
        slotId: s.slotId,
        tutorId: s.tutorId,
        startAt: s.startAt,
        endAt: s.endAt,
        timezone: s.timezone || 'Europe/London',
        status: s.status,
      }));

    sendSuccess(res, { slots: availableSlots });
  } catch (error) {
    log.warn('Error in getAvailableSlots', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve available slots', requestId, 500);
  }
}

/**
 * Endpoint: updateAvailabilitySlot
 * Allows a TUTOR to update their own availability slot.
 */
export async function updateAvailabilitySlot(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = UpdateAvailabilitySlotSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid update payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { slotId, startAt, endAt, status } = parseResult.data;
  const db = getAdminFirestore();

  try {
    const slotRef = db.collection('availabilitySlots').doc(slotId);
    const slotSnap = await slotRef.get();

    if (!slotSnap.exists) {
      sendError(res, ErrorCode.SLOT_NOT_FOUND, 'Availability slot not found', requestId, 404);
      return;
    }

    const slotData = slotSnap.data() as AvailabilitySlotDocument;

    if (slotData.tutorId !== uid) {
      log.warn('Tutor attempted to modify another tutor slot', {
        uid,
        slotTutorId: slotData.tutorId,
      });
      sendError(
        res,
        ErrorCode.AVAILABILITY_UNAUTHORIZED,
        'Cannot modify availability slot belonging to another tutor',
        requestId,
        403
      );
      return;
    }

    if (slotData.status === 'BOOKED' && status === 'AVAILABLE') {
      sendError(
        res,
        ErrorCode.SLOT_ALREADY_BOOKED,
        'Cannot reset status of a booked slot without booking cancellation',
        requestId,
        400
      );
      return;
    }

    const now = new Date().toISOString();
    const updateData: Partial<AvailabilitySlotDocument> = {
      updatedAt: now,
    };

    if (startAt) updateData.startAt = startAt;
    if (endAt) updateData.endAt = endAt;
    if (status) updateData.status = status;

    await slotRef.update(updateData);

    sendSuccess(res, {
      message: 'Slot updated successfully',
      slotId,
    });
  } catch (error) {
    log.warn('Error in updateAvailabilitySlot', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to update availability slot', requestId, 500);
  }
}

/**
 * Endpoint: deleteAvailabilitySlot
 * Allows a TUTOR to delete their own unbooked slot.
 */
export async function deleteAvailabilitySlot(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = DeleteAvailabilitySlotSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { slotId } = parseResult.data;
  const db = getAdminFirestore();

  try {
    const slotRef = db.collection('availabilitySlots').doc(slotId);
    const slotSnap = await slotRef.get();

    if (!slotSnap.exists) {
      sendError(res, ErrorCode.SLOT_NOT_FOUND, 'Availability slot not found', requestId, 404);
      return;
    }

    const slotData = slotSnap.data() as AvailabilitySlotDocument;

    if (slotData.tutorId !== uid) {
      sendError(
        res,
        ErrorCode.AVAILABILITY_UNAUTHORIZED,
        'Cannot delete availability slot belonging to another tutor',
        requestId,
        403
      );
      return;
    }

    await slotRef.delete();

    sendSuccess(res, { message: 'Availability slot deleted successfully', slotId });
  } catch (error) {
    log.warn('Error in deleteAvailabilitySlot', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to delete availability slot', requestId, 500);
  }
}

// ============================================================================
// LESSON BOOKING ENDPOINTS & TRANSACTIONAL LOGIC
// ============================================================================

/**
 * Endpoint: createBooking
 * Performs transactional double-booking prevention.
 */
export async function createBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const studentUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  log.info('Executing createBooking', { studentUid });

  const parseResult = CreateBookingSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid booking payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { slotId, childId, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    let createdBooking: BookingDocument | null = null;
    const finalBooking = await db.runTransaction(
      async (transaction: FirebaseFirestore.Transaction) => {
        // 1. Read the availability slot
        const slotRef = db.collection('availabilitySlots').doc(slotId);
        const slotSnap = await transaction.get(slotRef);

        if (!slotSnap.exists) {
          throw {
            code: ErrorCode.SLOT_NOT_FOUND,
            status: 404,
            message: 'Availability slot not found',
          };
        }

        const slotData = slotSnap.data() as AvailabilitySlotDocument;

        // 2. Check if slot is available
        if (slotData.status !== 'AVAILABLE' || slotData.bookingId) {
          throw {
            code: ErrorCode.SLOT_ALREADY_BOOKED,
            status: 409,
            message: 'This slot is no longer available for booking',
          };
        }

        // 3. Verify that the tutor is still bookable
        const tutorRef = db.collection('tutorProfiles').doc(slotData.tutorId);
        const tutorSnap = await transaction.get(tutorRef);

        if (!tutorSnap.exists) {
          throw {
            code: ErrorCode.TUTOR_NOT_FOUND,
            status: 404,
            message: 'Tutor profile not found',
          };
        }

        const tutorData = tutorSnap.data() as TutorProfileDocument;
        const isApproved =
          tutorData.approvalStatus === 'APPROVED' ||
          tutorData.onboardingStatus === 'MANAGER_APPROVED';
        const isBookable = tutorData.isBookable === true || tutorData.bookableStatus === true;
        const isPublic = tutorData.isPublic === true || tutorData.publicStatus === true;

        if (!isApproved || !isBookable || !isPublic) {
          throw {
            code: ErrorCode.TUTOR_NOT_BOOKABLE,
            status: 403,
            message: 'Tutor is not currently bookable',
          };
        }

        // 4. Create new booking ID and document
        const bookingRef = db.collection('bookings').doc();
        const bookingId = bookingRef.id;
        const now = new Date().toISOString();

        const initialHistory: BookingStatusHistoryEntry = {
          status: 'PENDING',
          changedAt: now,
          changedBy: studentUid,
          changedByRole: req.context.role || 'STUDENT_PARENT',
          reason: reason || 'Booking requested',
        };

        const bookingDoc: BookingDocument = {
          bookingId,
          studentUid,
          parentId: studentUid,
          tutorUid: slotData.tutorId,
          childId: childId || null,
          slotId,
          startAt: slotData.startAt,
          endAt: slotData.endAt,
          status: 'PENDING',
          statusHistory: [initialHistory],
          createdAt: now,
          updatedAt: now,
        };

        // 5. Write booking & reserve slot atomically
        transaction.set(bookingRef, bookingDoc);
        transaction.update(slotRef, {
          status: 'BOOKED',
          bookingId,
          updatedAt: now,
        });

        return bookingDoc;
      }
    );

    createdBooking = finalBooking;

    log.info('Booking created successfully', { bookingId: createdBooking.bookingId, studentUid });
    sendSuccess(res, {
      message: 'Booking created successfully',
      booking: createdBooking,
    });
  } catch (error: unknown) {
    log.warn('Error in createBooking', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to create booking', requestId, 500);
    }
  }
}

/**
 * Endpoint: getMyBookings
 * Returns bookings for the authenticated user based on role.
 */
export async function getMyBookings(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const role = req.context.role;
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  try {
    let query: FirebaseFirestore.Query = db.collection('bookings');

    if (role === 'TUTOR') {
      query = query.where('tutorUid', '==', uid);
    } else if (role === 'STUDENT_PARENT') {
      query = query.where('studentUid', '==', uid);
    } else if (role === 'MANAGER') {
      // Manager can see all bookings
    } else {
      query = query.where('studentUid', '==', uid);
    }

    const snapshot = await query.get();
    const bookings = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as BookingDocument
    );

    sendSuccess(res, { bookings });
  } catch (error) {
    log.warn('Error in getMyBookings', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve bookings', requestId, 500);
  }
}

/**
 * Endpoint: getBooking
 * Returns a single booking by ID if caller is authorized.
 */
export async function getBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const role = req.context.role;
  const bookingId = req.params.bookingId || (req.query.bookingId as string);
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  if (!bookingId) {
    sendError(res, ErrorCode.VALIDATION_ERROR, 'bookingId parameter is required', requestId, 400);
    return;
  }

  try {
    const bookingSnap = await db.collection('bookings').doc(bookingId).get();

    if (!bookingSnap.exists) {
      sendError(res, ErrorCode.BOOKING_NOT_FOUND, 'Booking not found', requestId, 404);
      return;
    }

    const booking = bookingSnap.data() as BookingDocument;

    const isStudent = booking.studentUid === uid;
    const isTutor = booking.tutorUid === uid;
    const isManager = role === 'MANAGER';

    if (!isStudent && !isTutor && !isManager) {
      sendError(
        res,
        ErrorCode.BOOKING_UNAUTHORIZED,
        'Unauthorized to view this booking',
        requestId,
        403
      );
      return;
    }

    sendSuccess(res, { booking });
  } catch (error) {
    log.warn('Error in getBooking', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve booking', requestId, 500);
  }
}

/**
 * Endpoint: getTutorBookings
 * Allows TUTOR to view their assigned bookings.
 */
export async function getTutorBookings(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  try {
    const snapshot = await db.collection('bookings').where('tutorUid', '==', uid).get();
    const bookings = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as BookingDocument
    );

    sendSuccess(res, { bookings });
  } catch (error) {
    log.warn('Error in getTutorBookings', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to retrieve tutor bookings', requestId, 500);
  }
}

/**
 * Helper for executing booking status transitions atomically.
 */
async function transitionBookingStatus(
  req: AuthenticatedRequest,
  res: Response,
  targetStatus: BookingStatus,
  allowedRoles: string[],
  releaseSlotOnTransition: boolean = false
): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const userRole = req.context.role || 'STUDENT_PARENT';
  const log = appLogger.forRequest(requestId);

  const parseResult = BookingActionSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { bookingId, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    let updatedBooking: BookingDocument | null = null;

    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const bookingRef = db.collection('bookings').doc(bookingId);
      const bookingSnap = await transaction.get(bookingRef);

      if (!bookingSnap.exists) {
        throw { code: ErrorCode.BOOKING_NOT_FOUND, status: 404, message: 'Booking not found' };
      }

      const booking = bookingSnap.data() as BookingDocument;

      // Ownership check
      const isStudent = booking.studentUid === uid;
      const isTutor = booking.tutorUid === uid;
      const isManager = userRole === 'MANAGER';

      let isAuthorized = false;
      if (allowedRoles.includes('STUDENT') && isStudent) isAuthorized = true;
      if (allowedRoles.includes('TUTOR') && isTutor) isAuthorized = true;
      if (allowedRoles.includes('MANAGER') && isManager) isAuthorized = true;

      if (!isAuthorized) {
        throw {
          code: ErrorCode.BOOKING_UNAUTHORIZED,
          status: 403,
          message: 'Unauthorized to perform action on this booking',
        };
      }

      // Check transition validity
      if (!isValidBookingTransition(booking.status, targetStatus)) {
        throw {
          code: ErrorCode.BOOKING_INVALID_STATE_TRANSITION,
          status: 400,
          message: `Invalid status transition from ${booking.status} to ${targetStatus}`,
        };
      }

      const now = new Date().toISOString();
      const newHistoryEntry: BookingStatusHistoryEntry = {
        status: targetStatus,
        changedAt: now,
        changedBy: uid,
        changedByRole: userRole,
        reason: reason || `Status updated to ${targetStatus}`,
      };

      const updatedHistory = [...(booking.statusHistory || []), newHistoryEntry];

      transaction.update(bookingRef, {
        status: targetStatus,
        statusHistory: updatedHistory,
        updatedAt: now,
      });

      // Release slot if cancelled/rejected and requested
      if (releaseSlotOnTransition && booking.slotId) {
        const slotRef = db.collection('availabilitySlots').doc(booking.slotId);
        const slotSnap = await transaction.get(slotRef);
        if (slotSnap.exists) {
          transaction.update(slotRef, {
            status: 'AVAILABLE',
            bookingId: null,
            updatedAt: now,
          });
        }
      }

      updatedBooking = {
        ...booking,
        status: targetStatus,
        statusHistory: updatedHistory,
        updatedAt: now,
      };
    });

    log.info(`Booking status transitioned to ${targetStatus}`, { bookingId, uid });
    sendSuccess(res, {
      message: `Booking status updated to ${targetStatus}`,
      booking: updatedBooking,
    });
  } catch (error: unknown) {
    log.warn(`Error transitioning booking status to ${targetStatus}`, { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to update booking status', requestId, 500);
    }
  }
}

/**
 * Endpoint: confirmBooking
 * Allows assigned TUTOR to confirm a PENDING booking.
 */
export async function confirmBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  return transitionBookingStatus(req, res, 'CONFIRMED', ['TUTOR']);
}

/**
 * Endpoint: rejectBooking
 * Allows assigned TUTOR or MANAGER to reject a PENDING booking.
 */
export async function rejectBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  return transitionBookingStatus(req, res, 'REJECTED', ['TUTOR', 'MANAGER'], true);
}

/**
 * Endpoint: proposeReschedule
 * Allows TUTOR or STUDENT to propose a reschedule.
 */
export async function proposeReschedule(req: AuthenticatedRequest, res: Response): Promise<void> {
  return transitionBookingStatus(req, res, 'RESCHEDULE_PROPOSED', ['TUTOR', 'STUDENT']);
}

/**
 * Endpoint: cancelBooking
 * Allows STUDENT, TUTOR, or MANAGER to cancel a booking.
 */
export async function cancelBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  return transitionBookingStatus(req, res, 'CANCELLED', ['STUDENT', 'TUTOR', 'MANAGER'], true);
}

/**
 * Endpoint: completeBooking
 * Allows assigned TUTOR to complete a CONFIRMED lesson.
 */
export async function completeBooking(req: AuthenticatedRequest, res: Response): Promise<void> {
  return transitionBookingStatus(req, res, 'COMPLETED', ['TUTOR']);
}

/**
 * Endpoint: managerListBookings
 * Allows a MANAGER to view all bookings in the platform.
 */
export async function managerListBookings(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const log = appLogger.forRequest(requestId);

  if (req.context.role !== 'MANAGER') {
    sendError(res, ErrorCode.FORBIDDEN, 'Manager access required', requestId, 403);
    return;
  }

  const db = getAdminFirestore();

  try {
    const snapshot = await db.collection('bookings').get();
    const bookings = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as BookingDocument
    );

    sendSuccess(res, { bookings });
  } catch (error) {
    log.warn('Error in managerListBookings', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to list bookings', requestId, 500);
  }
}

/**
 * Endpoint: managerCancelBooking
 * Allows a MANAGER to perform administrative cancellation of a booking with audit logging.
 */
export async function managerCancelBooking(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const managerUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  if (req.context.role !== 'MANAGER') {
    sendError(res, ErrorCode.FORBIDDEN, 'Manager access required', requestId, 403);
    return;
  }

  const parseResult = ManagerCancelBookingSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { bookingId, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const bookingRef = db.collection('bookings').doc(bookingId);
      const bookingSnap = await transaction.get(bookingRef);

      if (!bookingSnap.exists) {
        throw { code: ErrorCode.BOOKING_NOT_FOUND, status: 404, message: 'Booking not found' };
      }

      const booking = bookingSnap.data() as BookingDocument;

      if (!isValidBookingTransition(booking.status, 'CANCELLED')) {
        throw {
          code: ErrorCode.BOOKING_INVALID_STATE_TRANSITION,
          status: 400,
          message: `Cannot cancel booking in status: ${booking.status}`,
        };
      }

      const now = new Date().toISOString();
      const historyEntry: BookingStatusHistoryEntry = {
        status: 'CANCELLED',
        changedAt: now,
        changedBy: managerUid,
        changedByRole: 'MANAGER',
        reason: reason || 'Administrative cancellation by manager',
      };

      transaction.update(bookingRef, {
        status: 'CANCELLED',
        statusHistory: [...(booking.statusHistory || []), historyEntry],
        updatedAt: now,
      });

      if (booking.slotId) {
        const slotRef = db.collection('availabilitySlots').doc(booking.slotId);
        const slotSnap = await transaction.get(slotRef);
        if (slotSnap.exists) {
          transaction.update(slotRef, {
            status: 'AVAILABLE',
            bookingId: null,
            updatedAt: now,
          });
        }
      }

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'MANAGER_CANCEL_BOOKING',
        actorUid: managerUid,
        targetUid: booking.studentUid,
        details: { bookingId, reason: reason || null },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);
    });

    log.info('Manager cancelled booking successfully', { bookingId, managerUid });
    sendSuccess(res, { message: 'Booking cancelled by manager successfully', bookingId });
  } catch (error: unknown) {
    log.warn('Error in managerCancelBooking', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to cancel booking', requestId, 500);
    }
  }
}
