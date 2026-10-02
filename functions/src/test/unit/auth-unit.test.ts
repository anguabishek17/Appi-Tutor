/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * UK Tutoring Platform - Unit / Mock Test Suite
 * Authentication Phase (F02)
 *
 * SCOPE: UNIT / MOCK TESTS
 * Validates middleware logic, schema parsers, error payloads, and helper functions in isolation.
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  requireAuth,
  requireVerifiedEmail,
  requireActiveUser,
  requireRole,
} from '../../middleware/auth';
import { RegisterUserSchema } from '../../endpoints/auth';
import { AuthenticatedContext, ErrorCode, RequestContext, UserDocument } from '../../types';
import * as rolesModule from '../../helpers/roles';
import { provisionManager } from '../../scripts/provision-manager';
import * as adminAuth from 'firebase-admin/auth';
import * as adminFirestore from 'firebase-admin/firestore';

function createMockReqRes(
  options: {
    headers?: Record<string, string>;
    body?: unknown;
    method?: string;
  } = {}
) {
  let statusCode = 200;
  let responseData: any = null;
  const resHeaders: Record<string, string> = {};

  const req: any = {
    headers: options.headers || {},
    body: options.body || {},
    method: options.method || 'POST',
    ip: '127.0.0.1',
    path: '/api/test',
  };

  const res: any = {
    statusCode: 200,
    setHeader: (k: string, v: string) => {
      resHeaders[k.toLowerCase()] = v;
    },
    status: (code: number) => {
      statusCode = code;
      res.statusCode = code;
      return res;
    },
    json: (data: unknown) => {
      responseData = data;
      return res;
    },
  };

  return {
    req,
    res,
    getStatus: () => statusCode,
    getData: () => responseData,
    getHeaders: () => resHeaders,
  };
}

describe('[UNIT / MOCK TESTS] Phase F02 Authentication & Authorization', () => {
  const baseContext: RequestContext = {
    requestId: 'unit-test-req-100',
    timestamp: new Date().toISOString(),
    ip: '127.0.0.1',
  };

  it('AUTH-01 (Unit): requireAuth rejects missing Bearer token with 401 UNAUTHENTICATED', async () => {
    const { req, res, getStatus, getData } = createMockReqRes({ headers: {} });
    const authContext = await requireAuth(req, res, baseContext);

    assert.equal(authContext, null);
    assert.equal(getStatus(), 401);
    const data = getData();
    assert.equal(data.success, false);
    assert.equal(data.error.code, ErrorCode.UNAUTHENTICATED);
    assert.match(data.error.message, /Authentication required/i);
    assert.equal(data.request_id, 'unit-test-req-100');
  });

  it('AUTH-02 (Unit): STUDENT_PARENT role check passes for STUDENT_PARENT context', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'student-uid-01',
      email: 'student@example.co.uk',
      emailVerified: true,
      role: 'STUDENT_PARENT',
    };

    const { res, getStatus } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['STUDENT_PARENT']);
    assert.equal(hasRole, true);
    assert.equal(getStatus(), 200);
  });

  it('AUTH-03 (Unit): STUDENT_PARENT context is rejected from TUTOR role requirement (403)', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'student-uid-01',
      email: 'student@example.co.uk',
      emailVerified: true,
      role: 'STUDENT_PARENT',
    };

    const { res, getStatus, getData } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['TUTOR']);
    assert.equal(hasRole, false);
    assert.equal(getStatus(), 403);
    assert.equal(getData().error.code, ErrorCode.FORBIDDEN);
  });

  it('AUTH-04 (Unit): STUDENT_PARENT context is rejected from MANAGER role requirement (403)', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'student-uid-01',
      email: 'student@example.co.uk',
      emailVerified: true,
      role: 'STUDENT_PARENT',
    };

    const { res, getStatus, getData } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['MANAGER']);
    assert.equal(hasRole, false);
    assert.equal(getStatus(), 403);
    assert.equal(getData().error.code, ErrorCode.FORBIDDEN);
  });

  it('AUTH-05 (Unit): TUTOR role check passes for TUTOR context', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'tutor-uid-01',
      email: 'tutor@example.co.uk',
      emailVerified: true,
      role: 'TUTOR',
    };

    const { res, getStatus } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['TUTOR']);
    assert.equal(hasRole, true);
    assert.equal(getStatus(), 200);
  });

  it('AUTH-06 (Unit): TUTOR context is rejected from MANAGER role requirement (403)', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'tutor-uid-01',
      email: 'tutor@example.co.uk',
      emailVerified: true,
      role: 'TUTOR',
    };

    const { res, getStatus, getData } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['MANAGER']);
    assert.equal(hasRole, false);
    assert.equal(getStatus(), 403);
    assert.equal(getData().error.code, ErrorCode.FORBIDDEN);
  });

  it('AUTH-07 (Unit): MANAGER role check passes for MANAGER context', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'manager-uid-01',
      email: 'manager@tutoring.co.uk',
      emailVerified: true,
      role: 'MANAGER',
    };

    const { res, getStatus } = createMockReqRes();
    const hasRole = await requireRole(res, authContext, ['MANAGER']);
    assert.equal(hasRole, true);
    assert.equal(getStatus(), 200);
  });

  it('AUTH-08 (Unit): requireVerifiedEmail rejects unverified email context with 403', () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'unverified-uid',
      email: 'unverified@example.co.uk',
      emailVerified: false,
      role: 'STUDENT_PARENT',
    };

    const { res, getStatus, getData } = createMockReqRes();
    const isVerified = requireVerifiedEmail(res, authContext);

    assert.equal(isVerified, false);
    assert.equal(getStatus(), 403);
    assert.equal(getData().error.code, ErrorCode.FORBIDDEN);
    assert.match(getData().error.message, /Email address must be verified/i);
  });

  it('AUTH-09 (Unit): requireActiveUser rejects PENDING status user record with 403', async () => {
    const authContext: AuthenticatedContext = {
      ...baseContext,
      uid: 'pending-uid',
      email: 'pending@example.co.uk',
      emailVerified: true,
      role: 'TUTOR',
    };

    const originalGetUserDocument = rolesModule.getUserDocument;
    (rolesModule as any).getUserDocument = async (uid: string): Promise<UserDocument> => ({
      uid,
      email: 'pending@example.co.uk',
      displayName: 'Pending User',
      role: 'TUTOR',
      status: 'PENDING',
      emailVerifiedAt: new Date().toISOString(),
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    });

    try {
      const { res, getStatus, getData } = createMockReqRes();
      const activeUser = await requireActiveUser(res, authContext);

      assert.equal(activeUser, null);
      assert.equal(getStatus(), 403);
      assert.equal(getData().error.code, ErrorCode.FORBIDDEN);
      assert.match(getData().error.message, /Account is not active/i);
    } finally {
      (rolesModule as any).getUserDocument = originalGetUserDocument;
    }
  });

  it('AUTH-10 (Unit): RegisterUserSchema strictly rejects requestedRole="MANAGER"', () => {
    const result = RegisterUserSchema.safeParse({
      requestedRole: 'MANAGER',
      displayName: 'Attempter',
    });

    assert.equal(result.success, false);
    if (!result.success) {
      assert.match(result.error.issues[0].message, /Invalid requestedRole|MANAGER/i);
    }
  });

  it('AUTH-11 (Unit): RegisterUserSchema accepts only STUDENT_PARENT and TUTOR', () => {
    assert.equal(RegisterUserSchema.safeParse({ requestedRole: 'STUDENT_PARENT' }).success, true);
    assert.equal(RegisterUserSchema.safeParse({ requestedRole: 'TUTOR' }).success, true);
    assert.equal(RegisterUserSchema.safeParse({ requestedRole: 'ADMIN' }).success, false);
  });

  it('AUTH-12 (Unit): provisionManager executes privileged claim assignment and Firestore record creation', async () => {
    let assignedClaims: any = null;
    let writtenDoc: any = null;

    const mockAuth = {
      getUserByEmail: async () => {
        const err: any = new Error('User not found');
        err.code = 'auth/user-not-found';
        throw err;
      },
      createUser: async (p: any) => ({ uid: 'mock-mgr-101', email: p.email }),
      updateUser: async () => {},
      setCustomUserClaims: async (_uid: string, claims: any) => {
        assignedClaims = claims;
      },
    };

    const mockFirestore = {
      collection: () => ({
        doc: () => ({
          get: async () => ({ exists: false }),
          set: async (d: any) => {
            writtenDoc = d;
          },
          update: async (d: any) => {
            writtenDoc = d;
          },
        }),
      }),
    };

    const originalGetAuth = adminAuth.getAuth;
    const originalGetFirestore = adminFirestore.getFirestore;

    (adminAuth as any).getAuth = () => mockAuth;
    (adminFirestore as any).getFirestore = () => mockFirestore;

    try {
      const result = await provisionManager({
        email: 'manager.unit@tutoring.co.uk',
        password: 'Pass123!Secret',
        displayName: 'Unit Manager',
        status: 'ACTIVE',
      });

      assert.equal(result.uid, 'mock-mgr-101');
      assert.deepEqual(assignedClaims, { role: 'MANAGER' });
      assert.equal(writtenDoc?.role, 'MANAGER');
      assert.equal(writtenDoc?.status, 'ACTIVE');
    } finally {
      (adminAuth as any).getAuth = originalGetAuth;
      (adminFirestore as any).getFirestore = originalGetFirestore;
    }
  });
});
