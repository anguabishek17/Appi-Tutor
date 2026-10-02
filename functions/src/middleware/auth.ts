/**
 * UK Tutoring Platform - Authorization Middleware & Helpers
 * Authentication Phase (F02)
 */

import { Request, Response } from 'express';
import { getAuth, DecodedIdToken } from 'firebase-admin/auth';
import { getUserDocument } from '../helpers/roles';
import { sendError } from '../helpers/response';
import { appLogger } from '../helpers/logger';
import { AuthenticatedContext, ErrorCode, RequestContext, UserDocument, UserRole } from '../types';

export interface AuthenticatedRequest extends Request {
  context: AuthenticatedContext;
}

export type AuthenticatedHttpHandler = (
  req: Request,
  res: Response,
  context: AuthenticatedContext
) => Promise<void> | void;

/**
 * Extracts and verifies the Firebase ID token from the HTTP Authorization header.
 */
export async function verifyAuthToken(req: Request): Promise<DecodedIdToken | null> {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return null;
  }

  const token = authHeader.split('Bearer ')[1]?.trim();
  if (!token) {
    return null;
  }

  try {
    return await getAuth().verifyIdToken(token);
  } catch {
    return null;
  }
}

/**
 * Validates that a request is authenticated.
 * Rejects with 401 UNAUTHENTICATED if missing or invalid.
 */
export async function requireAuth(
  req: Request,
  res: Response,
  context: RequestContext
): Promise<AuthenticatedContext | null> {
  const decodedToken = await verifyAuthToken(req);

  if (!decodedToken) {
    appLogger.forRequest(context.requestId).warn('Unauthenticated request rejected');
    sendError(res, ErrorCode.UNAUTHENTICATED, 'Authentication required', context.requestId, 401);
    return null;
  }

  const authContext: AuthenticatedContext = {
    ...context,
    uid: decodedToken.uid,
    email: decodedToken.email,
    emailVerified: Boolean(decodedToken.email_verified),
    role: decodedToken.role as UserRole | undefined,
  };

  return authContext;
}

/**
 * Validates that the authenticated user has verified their email address.
 * Rejects with 403 FORBIDDEN if email is not verified.
 */
export function requireVerifiedEmail(res: Response, authContext: AuthenticatedContext): boolean {
  if (!authContext.emailVerified) {
    appLogger
      .forRequest(authContext.requestId)
      .warn('Request rejected: Unverified email', { uid: authContext.uid });
    sendError(
      res,
      ErrorCode.FORBIDDEN,
      'Email address must be verified to access this resource',
      authContext.requestId,
      403
    );
    return false;
  }
  return true;
}

/**
 * Validates that the application account is ACTIVE in Firestore.
 * Reads users/{uid} and strictly rejects PENDING, SUSPENDED, or non-existent accounts.
 * Never trusts client-supplied status.
 */
export async function requireActiveUser(
  res: Response,
  authContext: AuthenticatedContext
): Promise<UserDocument | null> {
  const userDoc = await getUserDocument(authContext.uid);

  if (!userDoc) {
    appLogger
      .forRequest(authContext.requestId)
      .warn('Request rejected: User record not found', { uid: authContext.uid });
    sendError(
      res,
      ErrorCode.FORBIDDEN,
      'User account record not found',
      authContext.requestId,
      403
    );
    return null;
  }

  if (userDoc.status !== 'ACTIVE') {
    appLogger.forRequest(authContext.requestId).warn('Request rejected: Inactive account status', {
      uid: authContext.uid,
      status: userDoc.status,
    });
    sendError(
      res,
      ErrorCode.FORBIDDEN,
      `Account is not active (status: ${userDoc.status})`,
      authContext.requestId,
      403,
      { status: userDoc.status }
    );
    return null;
  }

  authContext.status = userDoc.status;
  authContext.user = userDoc;
  return userDoc;
}

/**
 * Validates that the user has one of the allowed authoritative roles.
 * Verifies custom claims and/or the server-side users/{uid} document.
 * Never accepts role from request body, query parameters, or frontend state.
 */
export async function requireRole(
  res: Response,
  authContext: AuthenticatedContext,
  allowedRoles: readonly UserRole[]
): Promise<boolean> {
  // Authoritative role check: custom claims first, falling back to verified Firestore doc
  let userRole: UserRole | undefined = authContext.role;

  if (!userRole) {
    const userDoc = authContext.user || (await getUserDocument(authContext.uid));
    if (userDoc) {
      userRole = userDoc.role;
      authContext.role = userRole;
      authContext.user = userDoc;
    }
  }

  if (!userRole || !allowedRoles.includes(userRole)) {
    appLogger.forRequest(authContext.requestId).warn('Request rejected: Unauthorized role', {
      uid: authContext.uid,
      userRole: userRole || 'NONE',
      allowedRoles,
    });
    sendError(
      res,
      ErrorCode.FORBIDDEN,
      'Insufficient permissions for requested resource',
      authContext.requestId,
      403
    );
    return false;
  }

  return true;
}

export interface ProtectedHandlerOptions {
  requireVerifiedEmail?: boolean;
  requireActive?: boolean;
  allowedRoles?: readonly UserRole[];
}

/**
 * Helper to wrap protected HTTP Cloud Functions with full authentication and authorization pipeline.
 */
export function wrapProtectedHttpFunction(
  handler: AuthenticatedHttpHandler,
  options: ProtectedHandlerOptions = {}
) {
  return async (req: Request, res: Response, baseContext: RequestContext): Promise<void> => {
    // 1. Require Authentication
    const authContext = await requireAuth(req, res, baseContext);
    if (!authContext) return;

    // 2. Require Verified Email (if configured)
    if (options.requireVerifiedEmail) {
      const isVerified = requireVerifiedEmail(res, authContext);
      if (!isVerified) return;
    }

    // 3. Require Active User (if configured)
    if (options.requireActive) {
      const activeUser = await requireActiveUser(res, authContext);
      if (!activeUser) return;
    }

    // 4. Require Role (if configured)
    if (options.allowedRoles && options.allowedRoles.length > 0) {
      const hasRole = await requireRole(res, authContext, options.allowedRoles);
      if (!hasRole) return;
    }

    // 5. Execute Handler
    await handler(req, res, authContext);
  };
}
