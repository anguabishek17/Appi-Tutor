/**
 * UK Tutoring Platform - Shared Backend Types
 * Foundation Phase (F01)
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
}
