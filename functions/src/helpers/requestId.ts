/**
 * UK Tutoring Platform - Request ID Helper
 * Foundation Phase (F01)
 */

import { randomUUID } from 'crypto';

const REQUEST_ID_HEADER = 'x-request-id';

/**
 * Generates a clean, safe, unique Request ID.
 * Format: req_<uuid_without_hyphens>
 */
export function generateRequestId(): string {
  const uuid = randomUUID().replace(/-/g, '');
  return `req_${uuid}`;
}

/**
 * Extracts an existing request ID from HTTP headers or generates a new one.
 * Ensures the returned ID is sanitized to prevent header injection or malformed strings.
 */
export function extractOrGenerateRequestId(
  headers?: Record<string, string | string[] | undefined>
): string {
  if (!headers) {
    return generateRequestId();
  }

  const incomingId = headers[REQUEST_ID_HEADER] || headers[REQUEST_ID_HEADER.toLowerCase()];

  if (typeof incomingId === 'string' && incomingId.trim().length > 0) {
    // Sanitize: allow only alphanumeric characters, dashes, and underscores (max 64 chars)
    const sanitized = incomingId
      .trim()
      .replace(/[^a-zA-Z0-9_-]/g, '')
      .slice(0, 64);
    if (sanitized.length >= 8) {
      return sanitized;
    }
  }

  return generateRequestId();
}
