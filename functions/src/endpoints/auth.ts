/**
 * UK Tutoring Platform - Authentication & Authorization Endpoints
 * Authentication Phase (F02)
 */

import { onRequest } from 'firebase-functions/v2/https';
import { DEFAULT_REGION } from '../config';
import { wrapHttpFunction, wrapProtectedHttpFunction, requireAuth } from '../middleware';
import { sendSuccess, sendError } from '../helpers/response';
import { validateOrThrow, z } from '../helpers/validation';
import { getUserDocument, setUserRole, createUserRecord } from '../helpers/roles';
import { ErrorCode, AllowedRegistrationRole } from '../types';

/**
 * Validation schema for public user registration.
 * Explicitly rejects 'MANAGER' or any role not in the allowed registration list.
 */
export const RegisterUserSchema = z.object({
  requestedRole: z.enum(['STUDENT_PARENT', 'TUTOR'] as const, {
    errorMap: () => ({
      message:
        "Invalid requestedRole. Must be either 'STUDENT_PARENT' or 'TUTOR'. Self-registering as MANAGER is strictly prohibited.",
    }),
  }),
  displayName: z.string().trim().min(1).max(120).optional(),
});

/**
 * 1. User Registration / Onboarding Endpoint
 *
 * Requirements:
 * - Requires verified Firebase Authentication identity.
 * - Accepts only 'STUDENT_PARENT' or 'TUTOR'.
 * - Never allows client to assign or request 'MANAGER'.
 * - Sets initial application status to 'PENDING'.
 * - Sets authoritative custom claims role server-side.
 * - Creates users/{uid} document in Firestore.
 */
export const registerUser = onRequest(
  {
    region: DEFAULT_REGION,
    cors: true,
  },
  wrapHttpFunction(async (req, res, context) => {
    // 1. Enforce authentication
    const authContext = await requireAuth(req, res, context);
    if (!authContext) return;

    // 2. Server-side validation of input payload
    const body = validateOrThrow(RegisterUserSchema, req.body, context.requestId);
    const requestedRole: AllowedRegistrationRole = body.requestedRole;

    // 3. Double-check allowlist to ensure MANAGER is never assigned via registration
    if (requestedRole !== 'STUDENT_PARENT' && requestedRole !== 'TUTOR') {
      sendError(
        res,
        ErrorCode.FORBIDDEN,
        'Invalid registration role. MANAGER accounts cannot be self-registered.',
        context.requestId,
        403
      );
      return;
    }

    // 4. Check for existing registration to prevent role hijacking/overwriting
    const existingDoc = await getUserDocument(authContext.uid);
    if (existingDoc) {
      // If user already exists, prevent re-registration with a different role
      sendError(
        res,
        ErrorCode.ALREADY_EXISTS,
        'User profile has already been initialized',
        context.requestId,
        409,
        { role: existingDoc.role, status: existingDoc.status }
      );
      return;
    }

    const email = authContext.email || '';
    const displayName = body.displayName || (req.body?.displayName as string) || '';

    // 5. Assign authoritative custom claims in Firebase Authentication
    await setUserRole(authContext.uid, requestedRole, context.requestId);

    // 6. Create users/{uid} document in Firestore with PENDING status
    const userDoc = await createUserRecord(
      {
        uid: authContext.uid,
        email,
        displayName,
        role: requestedRole,
        emailVerified: authContext.emailVerified,
      },
      context.requestId
    );

    // 7. Return standard response
    sendSuccess(res, {
      uid: userDoc.uid,
      email: userDoc.email,
      displayName: userDoc.displayName,
      role: userDoc.role,
      status: userDoc.status,
      message: 'Registration successful. Account created with status PENDING.',
    });
  })
);

/**
 * 2. Protected STUDENT_PARENT Example Function
 * Requires: Authenticated, Verified Email, Active Status, STUDENT_PARENT Role
 */
export const protectedStudentParentExample = onRequest(
  {
    region: DEFAULT_REGION,
    cors: true,
  },
  wrapHttpFunction(
    wrapProtectedHttpFunction(
      (_req, res, context) => {
        sendSuccess(res, {
          message: 'Access granted to STUDENT_PARENT protected resource',
          uid: context.uid,
          role: context.role,
          status: context.status,
          timestamp: context.timestamp,
        });
      },
      {
        requireVerifiedEmail: true,
        requireActive: true,
        allowedRoles: ['STUDENT_PARENT'],
      }
    )
  )
);

/**
 * 3. Protected TUTOR Example Function
 * Requires: Authenticated, Verified Email, Active Status, TUTOR Role
 */
export const protectedTutorExample = onRequest(
  {
    region: DEFAULT_REGION,
    cors: true,
  },
  wrapHttpFunction(
    wrapProtectedHttpFunction(
      (_req, res, context) => {
        sendSuccess(res, {
          message: 'Access granted to TUTOR protected resource',
          uid: context.uid,
          role: context.role,
          status: context.status,
          timestamp: context.timestamp,
        });
      },
      {
        requireVerifiedEmail: true,
        requireActive: true,
        allowedRoles: ['TUTOR'],
      }
    )
  )
);

/**
 * 4. Protected MANAGER Example Function
 * Requires: Authenticated, Verified Email, Active Status, MANAGER Role
 */
export const protectedManagerExample = onRequest(
  {
    region: DEFAULT_REGION,
    cors: true,
  },
  wrapHttpFunction(
    wrapProtectedHttpFunction(
      (_req, res, context) => {
        sendSuccess(res, {
          message: 'Access granted to MANAGER protected resource',
          uid: context.uid,
          role: context.role,
          status: context.status,
          timestamp: context.timestamp,
        });
      },
      {
        requireVerifiedEmail: true,
        requireActive: true,
        allowedRoles: ['MANAGER'],
      }
    )
  )
);
