/**
 * UK Tutoring Platform - Tutor Onboarding & Manager Approval Endpoints
 * Phase F04 — Tutor Onboarding, DBS & Manager Approval
 */

import { Response } from 'express';
import { z } from 'zod';
import { getAdminFirestore } from '../config';
import { AuthenticatedRequest } from '../middleware/auth';

import { sendSuccess, sendError } from '../helpers/response';
import { appLogger } from '../helpers/logger';
import { ErrorCode, TutorProfileDocument, PublicTutorDocument, AuditLogDocument } from '../types';

// ============================================================================
// ZOD SCHEMAS FOR VALIDATION
// ============================================================================

export const UpdateTutorProfileSchema = z.object({
  bio: z.string().min(10, 'Bio must be at least 10 characters long'),
  subjects: z.array(z.string().min(1)).min(1, 'At least one subject is required'),
  qualifications: z.array(z.string().min(1)).min(1, 'At least one qualification is required'),
  hourlyRatePence: z
    .number()
    .int('Hourly rate must be an integer in pence')
    .positive('Hourly rate must be a positive integer'),
  contactPhone: z.string().optional(),
});

export const SubmitDbsDocumentSchema = z.object({
  dbsDocumentPath: z.string().min(1, 'DBS document path is required'),
});

export const ManagerApproveTutorSchema = z.object({
  tutorUid: z.string().min(1, 'tutorUid is required'),
  managerNotes: z.string().optional(),
});

export const ManagerRejectTutorSchema = z.object({
  tutorUid: z.string().min(1, 'tutorUid is required'),
  reason: z.string().optional(),
});

export const ManagerSuspendTutorSchema = z.object({
  tutorUid: z.string().min(1, 'tutorUid is required'),
  reason: z.string().optional(),
});

export const ManagerReapproveTutorSchema = z.object({
  tutorUid: z.string().min(1, 'tutorUid is required'),
  managerNotes: z.string().optional(),
});

// ============================================================================
// ENDPOINTS IMPLEMENTATION
// ============================================================================

/**
 * Endpoint: updateTutorProfile
 * Allows an authenticated TUTOR to complete or update their onboarding profile.
 */
export async function updateTutorProfile(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  log.info('Executing updateTutorProfile', { uid });

  const parseResult = UpdateTutorProfileSchema.safeParse(req.body);
  if (!parseResult.success) {
    log.warn('UpdateTutorProfile validation failed', parseResult.error.format());
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid tutor profile data',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { bio, subjects, qualifications, hourlyRatePence, contactPhone } = parseResult.data;
  const db = getAdminFirestore();
  const profileRef = db.collection('tutorProfiles').doc(uid);

  try {
    const docSnap = await profileRef.get();
    const existingData = docSnap.exists ? (docSnap.data() as TutorProfileDocument) : null;

    const currentOnboarding = existingData?.onboardingStatus || 'REGISTERED';
    const currentDbsStatus = existingData?.dbsStatus || 'NOT_SUBMITTED';

    let nextOnboarding = currentOnboarding;
    if (
      currentOnboarding === 'REGISTERED' ||
      currentOnboarding === 'EMAIL_VERIFIED' ||
      currentOnboarding === 'PROFILE_COMPLETE'
    ) {
      nextOnboarding = currentDbsStatus === 'SUBMITTED' ? 'PENDING_REVIEW' : 'PROFILE_COMPLETE';
    }

    const now = new Date().toISOString();
    const updatedProfile: Partial<TutorProfileDocument> = {
      uid,
      bio,
      subjects,
      qualifications,
      hourlyRatePence,
      contactPhone: contactPhone || existingData?.contactPhone,
      onboardingStatus: nextOnboarding,
      approvalStatus: existingData?.approvalStatus || 'PENDING',
      dbsStatus: currentDbsStatus,
      isBookable: existingData?.isBookable || false,
      isPublic: existingData?.isPublic || false,
      updatedAt: now,
      ...(existingData ? {} : { createdAt: now }),
    };

    await profileRef.set(updatedProfile, { merge: true });

    log.info('Tutor profile updated successfully', { uid, onboardingStatus: nextOnboarding });
    sendSuccess(res, {
      message: 'Profile updated successfully',
      profile: updatedProfile,
    });
  } catch (error) {
    log.warn('Error in updateTutorProfile', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to update tutor profile', requestId, 500);
  }
}

/**
 * Endpoint: submitDbsDocument
 * Allows an authenticated TUTOR to submit their private DBS certificate storage path.
 */
export async function submitDbsDocument(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  log.info('Executing submitDbsDocument', { uid });

  const parseResult = SubmitDbsDocumentSchema.safeParse(req.body);
  if (!parseResult.success) {
    sendError(
      res,
      ErrorCode.VALIDATION_ERROR,
      'Invalid DBS submission payload',
      requestId,
      400,
      parseResult.error.format()
    );
    return;
  }

  const { dbsDocumentPath } = parseResult.data;

  // Verify path ownership
  if (!dbsDocumentPath.startsWith(`dbs/${uid}/`)) {
    log.warn('DBS document path ownership violation attempt', { uid, path: dbsDocumentPath });
    sendError(
      res,
      ErrorCode.DBS_ACCESS_DENIED,
      'DBS document path must belong to your authorized UID storage directory',
      requestId,
      403
    );
    return;
  }

  const db = getAdminFirestore();
  const profileRef = db.collection('tutorProfiles').doc(uid);

  try {
    const docSnap = await profileRef.get();
    const existingData = docSnap.exists ? (docSnap.data() as TutorProfileDocument) : null;

    const bio = existingData?.bio;
    const subjects = existingData?.subjects;
    const qualifications = existingData?.qualifications;
    const hourlyRatePence = existingData?.hourlyRatePence;

    const isProfileComplete =
      Boolean(bio && bio.length >= 10) &&
      Boolean(subjects && subjects.length > 0) &&
      Boolean(qualifications && qualifications.length > 0) &&
      Boolean(hourlyRatePence && hourlyRatePence > 0);

    const nextOnboarding: TutorProfileDocument['onboardingStatus'] = isProfileComplete
      ? 'PENDING_REVIEW'
      : 'DBS_SUBMITTED';

    const now = new Date().toISOString();
    const dbsUpdate: Partial<TutorProfileDocument> = {
      uid,
      dbsDocumentPath,
      dbsSubmittedAt: now,
      dbsStatus: 'SUBMITTED',
      onboardingStatus: nextOnboarding,
      updatedAt: now,
    };

    await profileRef.set(dbsUpdate, { merge: true });

    log.info('DBS document submitted successfully', { uid, onboardingStatus: nextOnboarding });
    sendSuccess(res, {
      message: 'DBS document submitted successfully',
      dbsStatus: 'SUBMITTED',
      onboardingStatus: nextOnboarding,
    });
  } catch (error) {
    log.warn('Error in submitDbsDocument', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to submit DBS document', requestId, 500);
  }
}

/**
 * Endpoint: getTutorOnboardingStatus
 * Returns current tutor onboarding status and profile data.
 */
export async function getTutorOnboardingStatus(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const uid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const db = getAdminFirestore();

  try {
    const profileSnap = await db.collection('tutorProfiles').doc(uid).get();
    const profile = profileSnap.exists ? (profileSnap.data() as TutorProfileDocument) : null;

    sendSuccess(res, {
      uid,
      emailVerified: req.context.emailVerified,
      onboardingStatus: profile?.onboardingStatus || 'REGISTERED',
      approvalStatus: profile?.approvalStatus || 'PENDING',
      dbsStatus: profile?.dbsStatus || 'NOT_SUBMITTED',
      isBookable: profile?.isBookable || false,
      isPublic: profile?.isPublic || false,
      profile,
    });
  } catch (error) {
    log.warn('Error in getTutorOnboardingStatus', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to fetch onboarding status', requestId, 500);
  }
}

/**
 * Endpoint: managerListPendingTutors
 * Allows a MANAGER to view tutor profiles requiring review or management.
 */
export async function managerListPendingTutors(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const log = appLogger.forRequest(requestId);
  const db = getAdminFirestore();

  try {
    const snapshot = await db.collection('tutorProfiles').get();
    const tutors = snapshot.docs.map(
      (doc: FirebaseFirestore.QueryDocumentSnapshot) => doc.data() as TutorProfileDocument
    );

    log.info('Manager listed tutor profiles', { count: tutors.length });
    sendSuccess(res, { tutors });
  } catch (error) {
    log.warn('Error in managerListPendingTutors', { error: String(error) });
    sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to list tutor profiles', requestId, 500);
  }
}

/**
 * Endpoint: managerApproveTutor
 * Allows a MANAGER to approve a tutor, marking them bookable/public and publishing to publicTutors.
 */
export async function managerApproveTutor(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const managerUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = ManagerApproveTutorSchema.safeParse(req.body);
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

  const { tutorUid, managerNotes } = parseResult.data;
  const db = getAdminFirestore();

  try {
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const tutorRef = db.collection('tutorProfiles').doc(tutorUid);
      const tutorSnap = await transaction.get(tutorRef);

      if (!tutorSnap.exists) {
        throw { code: ErrorCode.TUTOR_NOT_FOUND, status: 404, message: 'Tutor profile not found' };
      }

      const tutorData = tutorSnap.data() as TutorProfileDocument;
      const currentOnboarding = tutorData.onboardingStatus || 'REGISTERED';
      const currentApproval = tutorData.approvalStatus || 'PENDING';

      if (currentApproval === 'APPROVED') {
        throw {
          code: ErrorCode.ALREADY_APPROVED,
          status: 400,
          message: 'Tutor is already approved',
        };
      }

      if (
        currentOnboarding !== 'PENDING_REVIEW' &&
        currentOnboarding !== 'DBS_SUBMITTED' &&
        currentOnboarding !== 'PROFILE_COMPLETE'
      ) {
        throw {
          code: ErrorCode.INVALID_STATE_TRANSITION,
          status: 400,
          message: `Cannot approve tutor from onboarding state: ${currentOnboarding}`,
        };
      }

      const userSnap = await transaction.get(db.collection('users').doc(tutorUid));
      const userData = userSnap.exists ? userSnap.data() : null;
      const displayName = userData?.displayName || 'Verified Tutor';

      const now = new Date().toISOString();

      transaction.update(tutorRef, {
        onboardingStatus: 'MANAGER_APPROVED',
        approvalStatus: 'APPROVED',
        dbsStatus: 'VERIFIED',
        isBookable: true,
        isPublic: true,
        approvedAt: now,
        approvedBy: managerUid,
        managerNotes: managerNotes || tutorData.managerNotes || null,
        updatedAt: now,
      });

      const publicRef = db.collection('publicTutors').doc(tutorUid);
      const publicDoc: PublicTutorDocument = {
        uid: tutorUid,
        displayName,
        bio: tutorData.bio || '',
        subjects: tutorData.subjects || [],
        hourlyRatePence: tutorData.hourlyRatePence || 0,
        updatedAt: now,
      };
      transaction.set(publicRef, publicDoc);

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'TUTOR_APPROVED',
        actorUid: managerUid,
        targetUid: tutorUid,
        details: { managerNotes: managerNotes || null },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);
    });

    log.info('Manager approved tutor successfully', { tutorUid, managerUid });
    sendSuccess(res, {
      message: 'Tutor approved successfully and published to directory',
      tutorUid,
    });
  } catch (error: unknown) {
    log.warn('Error in managerApproveTutor', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to approve tutor', requestId, 500);
    }
  }
}

/**
 * Endpoint: managerRejectTutor
 * Allows a MANAGER to reject a tutor application.
 */
export async function managerRejectTutor(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const managerUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = ManagerRejectTutorSchema.safeParse(req.body);
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

  const { tutorUid, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const tutorRef = db.collection('tutorProfiles').doc(tutorUid);
      const tutorSnap = await transaction.get(tutorRef);

      if (!tutorSnap.exists) {
        throw { code: ErrorCode.TUTOR_NOT_FOUND, status: 404, message: 'Tutor profile not found' };
      }

      const now = new Date().toISOString();

      transaction.update(tutorRef, {
        onboardingStatus: 'REJECTED',
        approvalStatus: 'REJECTED',
        dbsStatus: 'REJECTED',
        isBookable: false,
        isPublic: false,
        rejectedAt: now,
        rejectedBy: managerUid,
        rejectionReason: reason || null,
        updatedAt: now,
      });

      const publicRef = db.collection('publicTutors').doc(tutorUid);
      transaction.delete(publicRef);

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'TUTOR_REJECTED',
        actorUid: managerUid,
        targetUid: tutorUid,
        details: { reason: reason || null },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);
    });

    log.info('Manager rejected tutor', { tutorUid, managerUid });
    sendSuccess(res, {
      message: 'Tutor rejected successfully',
      tutorUid,
    });
  } catch (error: unknown) {
    log.warn('Error in managerRejectTutor', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to reject tutor', requestId, 500);
    }
  }
}

/**
 * Endpoint: managerSuspendTutor
 * Allows a MANAGER to suspend an approved tutor, immediately revoking public/bookable access.
 */
export async function managerSuspendTutor(req: AuthenticatedRequest, res: Response): Promise<void> {
  const { requestId } = req.context;
  const managerUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = ManagerSuspendTutorSchema.safeParse(req.body);
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

  const { tutorUid, reason } = parseResult.data;
  const db = getAdminFirestore();

  try {
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const tutorRef = db.collection('tutorProfiles').doc(tutorUid);
      const tutorSnap = await transaction.get(tutorRef);

      if (!tutorSnap.exists) {
        throw { code: ErrorCode.TUTOR_NOT_FOUND, status: 404, message: 'Tutor profile not found' };
      }

      const tutorData = tutorSnap.data() as TutorProfileDocument;
      if (tutorData.approvalStatus !== 'APPROVED') {
        throw {
          code: ErrorCode.INVALID_STATE_TRANSITION,
          status: 400,
          message: `Cannot suspend tutor with approval status: ${tutorData.approvalStatus}`,
        };
      }

      const now = new Date().toISOString();

      transaction.update(tutorRef, {
        onboardingStatus: 'SUSPENDED',
        approvalStatus: 'SUSPENDED',
        isBookable: false,
        isPublic: false,
        suspendedAt: now,
        suspendedBy: managerUid,
        suspensionReason: reason || null,
        updatedAt: now,
      });

      const publicRef = db.collection('publicTutors').doc(tutorUid);
      transaction.delete(publicRef);

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'TUTOR_SUSPENDED',
        actorUid: managerUid,
        targetUid: tutorUid,
        details: { reason: reason || null },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);
    });

    log.info('Manager suspended tutor', { tutorUid, managerUid });
    sendSuccess(res, {
      message: 'Tutor suspended successfully',
      tutorUid,
    });
  } catch (error: unknown) {
    log.warn('Error in managerSuspendTutor', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to suspend tutor', requestId, 500);
    }
  }
}

/**
 * Endpoint: managerReapproveTutor
 * Allows a MANAGER to re-approve a suspended tutor.
 */
export async function managerReapproveTutor(
  req: AuthenticatedRequest,
  res: Response
): Promise<void> {
  const { requestId } = req.context;
  const managerUid = req.context.uid;
  const log = appLogger.forRequest(requestId);

  const parseResult = ManagerReapproveTutorSchema.safeParse(req.body);
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

  const { tutorUid, managerNotes } = parseResult.data;
  const db = getAdminFirestore();

  try {
    await db.runTransaction(async (transaction: FirebaseFirestore.Transaction) => {
      const tutorRef = db.collection('tutorProfiles').doc(tutorUid);
      const tutorSnap = await transaction.get(tutorRef);

      if (!tutorSnap.exists) {
        throw { code: ErrorCode.TUTOR_NOT_FOUND, status: 404, message: 'Tutor profile not found' };
      }

      const tutorData = tutorSnap.data() as TutorProfileDocument;
      if (tutorData.onboardingStatus !== 'SUSPENDED' && tutorData.approvalStatus !== 'SUSPENDED') {
        throw {
          code: ErrorCode.INVALID_STATE_TRANSITION,
          status: 400,
          message: `Cannot re-approve tutor with onboarding status: ${tutorData.onboardingStatus}`,
        };
      }

      const userSnap = await transaction.get(db.collection('users').doc(tutorUid));
      const userData = userSnap.exists ? userSnap.data() : null;
      const displayName = userData?.displayName || 'Verified Tutor';

      const now = new Date().toISOString();

      transaction.update(tutorRef, {
        onboardingStatus: 'MANAGER_APPROVED',
        approvalStatus: 'APPROVED',
        isBookable: true,
        isPublic: true,
        approvedAt: now,
        approvedBy: managerUid,
        managerNotes: managerNotes || tutorData.managerNotes || null,
        updatedAt: now,
      });

      const publicRef = db.collection('publicTutors').doc(tutorUid);
      const publicDoc: PublicTutorDocument = {
        uid: tutorUid,
        displayName,
        bio: tutorData.bio || '',
        subjects: tutorData.subjects || [],
        hourlyRatePence: tutorData.hourlyRatePence || 0,
        updatedAt: now,
      };
      transaction.set(publicRef, publicDoc);

      const auditRef = db.collection('auditLogs').doc(requestId);
      const auditDoc: AuditLogDocument = {
        id: requestId,
        eventType: 'TUTOR_REAPPROVED',
        actorUid: managerUid,
        targetUid: tutorUid,
        details: { managerNotes: managerNotes || null },
        timestamp: now,
      };
      transaction.set(auditRef, auditDoc);
    });

    log.info('Manager re-approved tutor', { tutorUid, managerUid });
    sendSuccess(res, {
      message: 'Tutor re-approved successfully',
      tutorUid,
    });
  } catch (error: unknown) {
    log.warn('Error in managerReapproveTutor', { error: String(error) });
    const err = error as { code?: ErrorCode; status?: number; message?: string };
    if (err.code && err.message) {
      sendError(res, err.code, err.message, requestId, err.status || 400);
    } else {
      sendError(res, ErrorCode.INTERNAL_ERROR, 'Failed to re-approve tutor', requestId, 500);
    }
  }
}
