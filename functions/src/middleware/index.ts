/**
 * UK Tutoring Platform - Backend Middleware
 * Foundation Phase (F01)
 */

import { Request, Response } from 'express';
import { extractOrGenerateRequestId } from '../helpers/requestId';
import { sendError } from '../helpers/response';
import { appLogger } from '../helpers/logger';
import { AppValidationError } from '../helpers/validation';
import { ErrorCode, RequestContext } from '../types';

export type HttpHandler = (
  req: Request,
  res: Response,
  context: RequestContext
) => Promise<void> | void;

/**
 * Higher-order wrapper for 2nd Gen HTTP Cloud Functions.
 * Handles request correlation IDs, timing, safe error handling, and structured logging.
 */
export function wrapHttpFunction(handler: HttpHandler) {
  return async (req: Request, res: Response): Promise<void> => {
    const startTime = Date.now();
    const requestId = extractOrGenerateRequestId(req.headers);
    const log = appLogger.forRequest(requestId);

    const context: RequestContext = {
      requestId,
      timestamp: new Date().toISOString(),
      ip: req.ip || (req.headers['x-forwarded-for'] as string),
      userAgent: req.headers['user-agent'],
    };

    res.setHeader('x-request-id', requestId);

    log.info('Incoming request', {
      method: req.method,
      path: req.path,
      ip: context.ip,
    });

    try {
      await handler(req, res, context);
      const durationMs = Date.now() - startTime;
      log.info('Request completed', { durationMs, statusCode: res.statusCode });
    } catch (error: unknown) {
      const durationMs = Date.now() - startTime;

      if (error instanceof AppValidationError) {
        log.warn('Validation error occurred', {
          durationMs,
          details: error.details,
        });
        sendError(res, ErrorCode.VALIDATION_ERROR, error.message, requestId, 400, error.details);
        return;
      }

      log.error('Unhandled server error in HTTP function', error, { durationMs });

      // Return a safe generic error response without exposing internal stack traces
      sendError(
        res,
        ErrorCode.INTERNAL_ERROR,
        'An unexpected internal server error occurred',
        requestId,
        500
      );
    }
  };
}
