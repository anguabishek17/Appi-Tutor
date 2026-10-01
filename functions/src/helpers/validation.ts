/**
 * UK Tutoring Platform - Validation Helper
 * Foundation Phase (F01)
 */

import { z, ZodError, ZodType } from 'zod';
import { ErrorCode } from '../types';

export interface ValidationSuccess<T> {
  success: true;
  data: T;
}

export interface ValidationErrorDetail {
  path: string;
  message: string;
}

export interface ValidationFailure {
  success: false;
  error: {
    code: ErrorCode.VALIDATION_ERROR;
    message: string;
    details: ValidationErrorDetail[];
  };
}

export type ValidationResult<T> = ValidationSuccess<T> | ValidationFailure;

/**
 * Custom application validation error.
 */
export class AppValidationError extends Error {
  public readonly code = ErrorCode.VALIDATION_ERROR;
  public readonly details: ValidationErrorDetail[];
  public readonly requestId?: string;

  constructor(message: string, details: ValidationErrorDetail[] = [], requestId?: string) {
    super(message);
    this.name = 'AppValidationError';
    this.details = details;
    this.requestId = requestId;
  }
}

/**
 * Format Zod error issues into a clean, safe array of path-message pairs.
 */
export function formatZodError(error: ZodError): ValidationErrorDetail[] {
  return error.issues.map(issue => ({
    path: issue.path.join('.') || 'root',
    message: issue.message,
  }));
}

/**
 * Safely validates data against a Zod schema returning a result object.
 */
export function validateData<T>(schema: ZodType<T>, input: unknown): ValidationResult<T> {
  const result = schema.safeParse(input);

  if (!result.success) {
    const details = formatZodError(result.error);
    return {
      success: false,
      error: {
        code: ErrorCode.VALIDATION_ERROR,
        message: 'Invalid request data',
        details,
      },
    };
  }

  return {
    success: true,
    data: result.data,
  };
}

/**
 * Validates data against a Zod schema or throws a typed AppValidationError.
 */
export function validateOrThrow<T>(schema: ZodType<T>, input: unknown, requestId?: string): T {
  const result = schema.safeParse(input);

  if (!result.success) {
    const details = formatZodError(result.error);
    const firstMessage = details[0]?.message || 'Validation failed';
    throw new AppValidationError(`Validation error: ${firstMessage}`, details, requestId);
  }

  return result.data;
}

export { z };
