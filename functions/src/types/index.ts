/**
 * UK Tutoring Platform - Shared Backend Types
 * Foundation Phase (F01) & Authentication Phase (F02)
 */

/**
 * Standard API Success Response format.
 */
export interface ApiSuccessResponse<T = unknown> {
  success: true;
  data: T;
}

/**
 * Standard API Error payload.
 */
export interface ApiErrorDetail {
  code: string;
  message: string;
  details?: unknown;
}

/**
 * Standard API Error Response format.
 * Never exposes stack traces, secrets, internal credentials, or sensitive DBS info.
 */
export interface ApiErrorResponse {
  success: false;
  error: ApiErrorDetail;
  request_id: string;
}

/**
 * Union type for standard API responses.
 */
export type ApiResponse<T = unknown> = ApiSuccessResponse<T> | ApiErrorResponse;

/**
 * Request execution context containing tracking metadata.
 */
export interface RequestContext {
  requestId: string;
  timestamp: string;
  ip?: string;
  userAgent?: string;
  userId?: string;
}

/**
 * Common Standard Error Codes
 */
export enum ErrorCode {
  VALIDATION_ERROR = 'VALIDATION_ERROR',
  UNAUTHENTICATED = 'UNAUTHENTICATED',
  UNAUTHORIZED = 'UNAUTHORIZED',
  FORBIDDEN = 'FORBIDDEN',
  NOT_FOUND = 'NOT_FOUND',
  ALREADY_EXISTS = 'ALREADY_EXISTS',
  RATE_LIMITED = 'RATE_LIMITED',
  INTERNAL_ERROR = 'INTERNAL_ERROR',
  BAD_REQUEST = 'BAD_REQUEST',
  TUTOR_PROFILE_INCOMPLETE = 'TUTOR_PROFILE_INCOMPLETE',
  TUTOR_NOT_FOUND = 'TUTOR_NOT_FOUND',
  INVALID_STATE_TRANSITION = 'INVALID_STATE_TRANSITION',
  DBS_SUBMISSION_REQUIRED = 'DBS_SUBMISSION_REQUIRED',
  DBS_ACCESS_DENIED = 'DBS_ACCESS_DENIED',
  MANAGER_REQUIRED = 'MANAGER_REQUIRED',
  TUTOR_NOT_APPROVED = 'TUTOR_NOT_APPROVED',
  TUTOR_SUSPENDED = 'TUTOR_SUSPENDED',
  ALREADY_APPROVED = 'ALREADY_APPROVED',
  // F05 Lesson Booking Error Codes
  BOOKING_NOT_FOUND = 'BOOKING_NOT_FOUND',
  BOOKING_UNAUTHORIZED = 'BOOKING_UNAUTHORIZED',
  BOOKING_INVALID_STATE_TRANSITION = 'BOOKING_INVALID_STATE_TRANSITION',
  SLOT_NOT_FOUND = 'SLOT_NOT_FOUND',
  SLOT_ALREADY_BOOKED = 'SLOT_ALREADY_BOOKED',
  SLOT_UNAVAILABLE = 'SLOT_UNAVAILABLE',
  TUTOR_NOT_BOOKABLE = 'TUTOR_NOT_BOOKABLE',
  AVAILABILITY_UNAUTHORIZED = 'AVAILABILITY_UNAUTHORIZED',
  INVALID_AVAILABILITY_RANGE = 'INVALID_AVAILABILITY_RANGE',
}

/**
 * User Roles supported by the UK Tutoring Platform.
 */
export type UserRole = 'STUDENT_PARENT' | 'TUTOR' | 'MANAGER';

/**
 * Roles allowed to be requested during public user registration.
 * MANAGER accounts can NEVER be created through public self-registration.
 */
export type AllowedRegistrationRole = 'STUDENT_PARENT' | 'TUTOR';

export const ALLOWED_REGISTRATION_ROLES: readonly AllowedRegistrationRole[] = [
  'STUDENT_PARENT',
  'TUTOR',
] as const;

/**
 * Application Account Statuses.
 */
export type UserStatus = 'PENDING' | 'ACTIVE' | 'SUSPENDED' | 'REJECTED';

/**
 * Firebase Auth Custom Claims format.
 */
export interface CustomClaims {
  role: UserRole;
  [key: string]: unknown;
}

/**
 * Structure of the users/{uid} document in Cloud Firestore.
 */
export interface UserDocument {
  uid: string;
  email: string;
  displayName: string;
  role: UserRole;
  status: UserStatus;
  emailVerifiedAt: unknown | null;
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Authenticated Request Context containing verified user identity and role.
 */
export interface AuthenticatedContext extends RequestContext {
  uid: string;
  email?: string;
  emailVerified: boolean;
  role?: UserRole;
  status?: UserStatus;
  user?: UserDocument;
}

export * from './firestore';
