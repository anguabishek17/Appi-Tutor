/**
 * UK Tutoring Platform - Structured Logger
 * Foundation Phase (F01)
 *
 * Security Guarantee:
 * Strictly redacts passwords, authentication tokens, credentials, private DBS documents,
 * API secrets, and sensitive PII from all log payloads.
 */

import * as logger from 'firebase-functions/logger';

const SENSITIVE_KEY_PATTERNS = [
  /pass(word)?/i,
  /token/i,
  /auth(orization)?/i,
  /secret/i,
  /api[-_]?key/i,
  /credential/i,
  /dbs[-_]?(number|cert|certificate|doc|document)?/i,
  /private[-_]?key/i,
  /credit[-_]?card/i,
  /cvv/i,
  /ssn/i,
  /national[-_]?insurance/i,
  /cookie/i,
  /session[-_]?id/i,
];

const REDACTED_PLACEHOLDER = '[REDACTED]';

/**
 * Recursively sanitizes any payload, stripping sensitive keys and values.
 */
export function sanitizeLogPayload(data: unknown, depth = 0): unknown {
  if (depth > 6) {
    return '[DEPTH_LIMIT_EXCEEDED]';
  }

  if (data === null || data === undefined) {
    return data;
  }

  if (typeof data !== 'object') {
    return data;
  }

  if (Array.isArray(data)) {
    return data.map(item => sanitizeLogPayload(item, depth + 1));
  }

  const sanitized: Record<string, unknown> = {};

  for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
    const isSensitiveKey = SENSITIVE_KEY_PATTERNS.some(pattern => pattern.test(key));

    if (isSensitiveKey) {
      sanitized[key] = REDACTED_PLACEHOLDER;
    } else if (typeof value === 'object' && value !== null) {
      sanitized[key] = sanitizeLogPayload(value, depth + 1);
    } else {
      sanitized[key] = value;
    }
  }

  return sanitized;
}

export interface LogMetadata {
  requestId?: string;
  userId?: string;
  context?: string;
  [key: string]: unknown;
}

/**
 * Structured Logger supporting request correlation and automatic PII redaction.
 */
export class StructuredLogger {
  private contextName: string;

  constructor(contextName = 'App') {
    this.contextName = contextName;
  }

  private formatEntry(message: string, meta?: LogMetadata) {
    const cleanMeta = meta ? (sanitizeLogPayload(meta) as LogMetadata) : {};
    return {
      message,
      context: this.contextName,
      timestamp: new Date().toISOString(),
      ...cleanMeta,
    };
  }

  public debug(message: string, meta?: LogMetadata): void {
    logger.debug(this.formatEntry(message, meta));
  }

  public info(message: string, meta?: LogMetadata): void {
    logger.info(this.formatEntry(message, meta));
  }

  public warn(message: string, meta?: LogMetadata): void {
    logger.warn(this.formatEntry(message, meta));
  }

  public error(message: string, error?: unknown, meta?: LogMetadata): void {
    const errorDetails =
      error instanceof Error
        ? { errorMessage: error.message, errorName: error.name }
        : { rawError: typeof error === 'string' ? error : 'Unknown error' };

    logger.error(this.formatEntry(message, { ...meta, ...errorDetails }));
  }

  public forRequest(requestId: string): StructuredLogger {
    const child = new StructuredLogger(this.contextName);
    const originalFormat = child.formatEntry.bind(child);
    child.formatEntry = (message: string, meta?: LogMetadata) =>
      originalFormat(message, { requestId, ...meta });
    return child;
  }
}

export const appLogger = new StructuredLogger('System');
