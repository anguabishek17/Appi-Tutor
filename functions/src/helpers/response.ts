/**
 * UK Tutoring Platform - Standard Response Helper
 * Foundation Phase (F01)
 */

import { Response } from 'express';
import { ApiErrorResponse, ApiSuccessResponse, ErrorCode } from '../types';
import { generateRequestId } from './requestId';

/**
 * Creates a standard success response payload.
 */
export function createSuccessResponse<T>(data: T): ApiSuccessResponse<T> {
  return {
    success: true,
    data,
  };
}

/**
 * Creates a standard sanitized error response payload.
 * Guarantee: Never leaks internal stack traces, tokens, passwords, or system internals.
 */
export function createErrorResponse(
  code: string | ErrorCode,
  message: string,
  requestId?: string,
  details?: unknown
): ApiErrorResponse {
  return {
    success: false,
    error: {
      code,
      message,
      ...(details ? { details } : {}),
    },
    request_id: requestId || generateRequestId(),
  };
}

/**
 * Sends a standardized success HTTP response.
 */
export function sendSuccess<T>(res: Response, data: T, statusCode = 200): void {
  res.status(statusCode).json(createSuccessResponse(data));
}

/**
 * Sends a standardized error HTTP response.
 */
export function sendError(
  res: Response,
  code: string | ErrorCode,
  message: string,
  requestId?: string,
  statusCode = 400,
  details?: unknown
): void {
  if (requestId) {
    res.setHeader('x-request-id', requestId);
  }
  res.status(statusCode).json(createErrorResponse(code, message, requestId, details));
}
