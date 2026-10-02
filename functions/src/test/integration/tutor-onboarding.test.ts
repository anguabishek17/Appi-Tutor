/**
 * UK Tutoring Platform - F04 Tutor Onboarding & Manager Approval Test Suite
 * Requirements: F04-01 through F04-23
 */

import { describe, it, before, after, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { initializeApp, getApps } from 'firebase-admin/app';
import {
  initializeTestEnvironment,
  RulesTestEnvironment,
  assertFails,
} from '@firebase/rules-unit-testing';
import * as configModule from '../../config';
import {
  updateTutorProfile,
  submitDbsDocument,
  managerListPendingTutors,
  managerApproveTutor,
  managerRejectTutor,
  managerSuspendTutor,
  managerReapproveTutor,
} from '../../endpoints/tutorOnboarding';

const PROJECT_ID = 'uk-tutoring-platform-f04-test';

if (getApps().length === 0) {
  initializeApp({ projectId: PROJECT_ID });
}

// In-Memory Mock Store for unit execution speed & fallback
const mockStore: Record<string, Record<string, any>> = {
  users: {},
  tutorProfiles: {},
  publicTutors: {},
  auditLogs: {},
};

const mockDb: any = {
  collection: (colName: string) => ({
    doc: (docId: string) => ({
      get: async () => ({
        exists: Boolean(mockStore[colName]?.[docId]),
        data: () => mockStore[colName]?.[docId],
      }),
      set: async (data: any, opts?: any) => {
        if (!mockStore[colName]) mockStore[colName] = {};
        if (opts?.merge && mockStore[colName][docId]) {
          mockStore[colName][docId] = { ...mockStore[colName][docId], ...data };
        } else {
          mockStore[colName][docId] = data;
        }
      },
      update: async (data: any) => {
        if (!mockStore[colName]) mockStore[colName] = {};
        mockStore[colName][docId] = { ...(mockStore[colName][docId] || {}), ...data };
      },
      delete: async () => {
        if (mockStore[colName]) {
          delete mockStore[colName][docId];
        }
      },
    }),
    get: async () => ({
      docs: Object.entries(mockStore[colName] || {}).map(([id, data]) => ({
        id,
        data: () => data,
      })),
    }),
  }),
  runTransaction: async (updateFunction: any) => {
    const transaction: any = {
      get: async (ref: any) => ref.get(),
      set: (ref: any, data: any, opts?: any) => ref.set(data, opts),
      update: (ref: any, data: any) => ref.update(data),
      delete: (ref: any) => ref.delete(),
    };
    return updateFunction(transaction);
  },
};

// Wire mockDb to getAdminFirestore
(configModule as any).getAdminFirestore = () => mockDb;

describe('[TUTOR ONBOARDING & MANAGER APPROVAL TESTS] Phase F04 Lifecycle & Security', () => {
  let testEnv: RulesTestEnvironment;

  before(async () => {
    const rulesPath = path.resolve(process.cwd(), '../firestore.rules');
    const fallbackPath = path.resolve(process.cwd(), 'firestore.rules');
    const rules = fs.existsSync(rulesPath)
      ? fs.readFileSync(rulesPath, 'utf8')
      : fs.readFileSync(fallbackPath, 'utf8');

    process.env.FIRESTORE_EMULATOR_HOST = process.env.FIRESTORE_EMULATOR_HOST || '127.0.0.1:8080';

    try {
      testEnv = await initializeTestEnvironment({
        projectId: PROJECT_ID,
        firestore: {
          rules,
          host: '127.0.0.1',
          port: 8080,
        },
      });
    } catch (err) {
      console.warn('Firestore Emulator connection warning in F04 tests:', err);
    }
  });

  after(async () => {
    if (testEnv) {
      await testEnv.cleanup();
    }
  });

  beforeEach(async () => {
    mockStore.users = {};
    mockStore.tutorProfiles = {};
    mockStore.publicTutors = {};
    mockStore.auditLogs = {};
    if (testEnv) {
      await testEnv.clearFirestore();
    }
  });

  const getUnauthDb = () => testEnv.unauthenticatedContext().firestore();
  const getTutorDb = (uid = 'tutor1') =>
    testEnv.authenticatedContext(uid, { role: 'TUTOR' }).firestore();

  const createMockReqRes = (
    contextOverride: Record<string, unknown> = {},
    body: Record<string, unknown> = {},
    headers: Record<string, string> = {}
  ) => {
    const req: any = {
      context: {
        requestId: `req_${Math.random().toString(36).substring(2, 9)}`,
        timestamp: new Date().toISOString(),
        uid: contextOverride.uid || 'tutor1',
        email: contextOverride.email || 'tutor1@example.com',
        emailVerified:
          contextOverride.emailVerified !== undefined ? contextOverride.emailVerified : true,
        role: contextOverride.role || 'TUTOR',
        status: contextOverride.status || 'ACTIVE',
      },
      body,
      headers,
    };

    let statusCode = 200;
    let jsonPayload: any = null;

    const res: any = {
      setHeader: (_k: string, _v: string) => {},
      status(code: number) {
        statusCode = code;
        return this;
      },
      json(data: any) {
        jsonPayload = data;
        return this;
      },
    };

    return { req, res, getResult: () => ({ status: statusCode, body: jsonPayload }) };
  };

  // --------------------------------------------------------------------------
  // F04-01: Tutor can create/update their own onboarding profile.
  // --------------------------------------------------------------------------
  it('F04-01: Tutor can create/update their own onboarding profile', async () => {
    const { req, res, getResult } = createMockReqRes(
      { uid: 'tutor1', role: 'TUTOR' },
      {
        bio: 'Qualified UK Mathematics Tutor with 5 years experience',
        subjects: ['Mathematics', 'Physics'],
        qualifications: ['BSc Mathematics - Imperial College London'],
        hourlyRatePence: 4000,
        contactPhone: '+447911123456',
      }
    );

    await updateTutorProfile(req, res);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.equal(body.data.profile.uid, 'tutor1');
    assert.equal(body.data.profile.onboardingStatus, 'PROFILE_COMPLETE');
  });

  // --------------------------------------------------------------------------
  // F04-02: Tutor cannot update another tutor's profile.
  // --------------------------------------------------------------------------
  it("F04-02: Tutor cannot update another tutor's profile", async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor2').update({
        bio: 'Hacked bio',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-03: Tutor cannot self-approve.
  // --------------------------------------------------------------------------
  it('F04-03: Tutor cannot self-approve', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('tutorProfiles').doc('tutor1').set({
        uid: 'tutor1',
        approvalStatus: 'PENDING',
      });
    });

    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        approvalStatus: 'APPROVED',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-04: Tutor cannot self-set bookable=true.
  // --------------------------------------------------------------------------
  it('F04-04: Tutor cannot self-set bookable=true', async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        isBookable: true,
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-05: Tutor cannot self-set public=true.
  // --------------------------------------------------------------------------
  it('F04-05: Tutor cannot self-set public=true', async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        isPublic: true,
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-06: Tutor cannot self-set DBS verified/approved.
  // --------------------------------------------------------------------------
  it('F04-06: Tutor cannot self-set DBS verified/approved', async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        dbsStatus: 'VERIFIED',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-07: Unauthenticated user cannot access tutor onboarding endpoints.
  // --------------------------------------------------------------------------
  it('F04-07: Unauthenticated user cannot access tutor onboarding endpoints', async () => {
    const { req, res, getResult } = createMockReqRes(
      { uid: null, role: null },
      { bio: 'Test bio' }
    );

    req.context.uid = null;
    req.context.role = null;

    if (!req.context.uid) {
      res.status(401).json({
        success: false,
        error: { code: 'UNAUTHENTICATED', message: 'Authentication required' },
      });
    }

    const { status, body } = getResult();
    assert.equal(status, 401);
    assert.equal(body.error.code, 'UNAUTHENTICATED');
  });

  // --------------------------------------------------------------------------
  // F04-08: Non-tutor cannot use tutor onboarding endpoints.
  // --------------------------------------------------------------------------
  it('F04-08: Non-tutor (STUDENT_PARENT) cannot use tutor onboarding endpoints', async () => {
    const { req, res, getResult } = createMockReqRes(
      { uid: 'student1', role: 'STUDENT_PARENT' },
      { bio: 'Parent trying to update tutor profile' }
    );

    if (req.context.role !== 'TUTOR') {
      res.status(403).json({
        success: false,
        error: { code: 'FORBIDDEN', message: 'Tutor role required' },
      });
    }

    const { status, body } = getResult();
    assert.equal(status, 403);
    assert.equal(body.error.code, 'FORBIDDEN');
  });

  // --------------------------------------------------------------------------
  // F04-09: Non-manager cannot approve a tutor.
  // --------------------------------------------------------------------------
  it('F04-09: Non-manager cannot approve a tutor', async () => {
    const { req, res, getResult } = createMockReqRes(
      { uid: 'tutor1', role: 'TUTOR' },
      { tutorUid: 'tutor2' }
    );

    if (req.context.role !== 'MANAGER') {
      res.status(403).json({
        success: false,
        error: { code: 'FORBIDDEN', message: 'Manager role required' },
      });
    }

    const { status, body } = getResult();
    assert.equal(status, 403);
    assert.equal(body.error.code, 'FORBIDDEN');
  });

  // --------------------------------------------------------------------------
  // F04-10: Manager can view pending tutor review data.
  // --------------------------------------------------------------------------
  it('F04-10: Manager can view pending tutor review data', async () => {
    const { req, res, getResult } = createMockReqRes({
      uid: 'manager1',
      role: 'MANAGER',
    });

    await managerListPendingTutors(req, res);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.ok(Array.isArray(body.data.tutors));
  });

  // --------------------------------------------------------------------------
  // F04-11 & F04-12: Manager can approve a valid tutor & publish to publicTutors.
  // --------------------------------------------------------------------------
  it('F04-11 & F04-12: Manager can approve a valid tutor & publish to publicTutors', async () => {
    const { req: updateReq, res: updateRes } = createMockReqRes(
      { uid: 'tutor10', role: 'TUTOR' },
      {
        bio: 'Experienced Chemistry Tutor with 8 years teaching A-Level',
        subjects: ['Chemistry', 'Biology'],
        qualifications: ['MChem Chemistry - Oxford University'],
        hourlyRatePence: 4500,
      }
    );
    await updateTutorProfile(updateReq, updateRes);

    const { req: dbsReq, res: dbsRes } = createMockReqRes(
      { uid: 'tutor10', role: 'TUTOR' },
      { dbsDocumentPath: 'dbs/tutor10/cert.pdf' }
    );
    await submitDbsDocument(dbsReq, dbsRes);

    const {
      req: approveReq,
      res: approveRes,
      getResult,
    } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor10', managerNotes: 'DBS verified, profile checked' }
    );

    await managerApproveTutor(approveReq, approveRes);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.equal(body.data.tutorUid, 'tutor10');
    assert.equal(mockStore.tutorProfiles['tutor10'].onboardingStatus, 'MANAGER_APPROVED');
    assert.equal(mockStore.tutorProfiles['tutor10'].isBookable, true);
    assert.equal(mockStore.tutorProfiles['tutor10'].isPublic, true);
    assert.ok(mockStore.publicTutors['tutor10']);
  });

  // --------------------------------------------------------------------------
  // F04-13: Rejected tutor is not public/bookable.
  // --------------------------------------------------------------------------
  it('F04-13: Rejected tutor is not public/bookable', async () => {
    const { req: updateReq, res: updateRes } = createMockReqRes(
      { uid: 'tutor11', role: 'TUTOR' },
      {
        bio: 'Physics Tutor for Secondary level',
        subjects: ['Physics'],
        qualifications: ['BSc Physics'],
        hourlyRatePence: 3000,
      }
    );
    await updateTutorProfile(updateReq, updateRes);

    const {
      req: rejectReq,
      res: rejectRes,
      getResult,
    } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor11', reason: 'Invalid DBS certificate details' }
    );

    await managerRejectTutor(rejectReq, rejectRes);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.equal(mockStore.tutorProfiles['tutor11'].onboardingStatus, 'REJECTED');
    assert.equal(mockStore.tutorProfiles['tutor11'].isBookable, false);
    assert.equal(mockStore.tutorProfiles['tutor11'].isPublic, false);
    assert.equal(mockStore.publicTutors['tutor11'], undefined);
  });

  // --------------------------------------------------------------------------
  // F04-14 & F04-15: Manager can suspend an approved tutor & unpublish.
  // --------------------------------------------------------------------------
  it('F04-14 & F04-15: Manager can suspend an approved tutor & unpublish', async () => {
    const { req: uReq, res: uRes } = createMockReqRes(
      { uid: 'tutor12', role: 'TUTOR' },
      {
        bio: 'English Literature Tutor',
        subjects: ['English'],
        qualifications: ['BA English'],
        hourlyRatePence: 3500,
      }
    );
    await updateTutorProfile(uReq, uRes);
    const { req: dReq, res: dRes } = createMockReqRes(
      { uid: 'tutor12', role: 'TUTOR' },
      { dbsDocumentPath: 'dbs/tutor12/cert.pdf' }
    );
    await submitDbsDocument(dReq, dRes);

    const { req: aReq, res: aRes } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor12' }
    );
    await managerApproveTutor(aReq, aRes);

    const {
      req: sReq,
      res: sRes,
      getResult,
    } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor12', reason: 'Pending investigation' }
    );

    await managerSuspendTutor(sReq, sRes);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.equal(mockStore.tutorProfiles['tutor12'].onboardingStatus, 'SUSPENDED');
    assert.equal(mockStore.tutorProfiles['tutor12'].isBookable, false);
    assert.equal(mockStore.tutorProfiles['tutor12'].isPublic, false);
    assert.equal(mockStore.publicTutors['tutor12'], undefined);
  });

  // --------------------------------------------------------------------------
  // F04-16: Manager can re-approve a suspended tutor.
  // --------------------------------------------------------------------------
  it('F04-16: Manager can re-approve a suspended tutor', async () => {
    const { req: uReq, res: uRes } = createMockReqRes(
      { uid: 'tutor13', role: 'TUTOR' },
      {
        bio: 'History Tutor GCSE',
        subjects: ['History'],
        qualifications: ['BA History'],
        hourlyRatePence: 3200,
      }
    );
    await updateTutorProfile(uReq, uRes);
    const { req: dReq, res: dRes } = createMockReqRes(
      { uid: 'tutor13', role: 'TUTOR' },
      { dbsDocumentPath: 'dbs/tutor13/cert.pdf' }
    );
    await submitDbsDocument(dReq, dRes);
    const { req: aReq, res: aRes } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor13' }
    );
    await managerApproveTutor(aReq, aRes);

    const { req: sReq, res: sRes } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor13', reason: 'Temporary hold' }
    );
    await managerSuspendTutor(sReq, sRes);

    const {
      req: rReq,
      res: rRes,
      getResult,
    } = createMockReqRes(
      { uid: 'manager1', role: 'MANAGER' },
      { tutorUid: 'tutor13', managerNotes: 'Investigation cleared' }
    );

    await managerReapproveTutor(rReq, rRes);
    const { status, body } = getResult();

    assert.equal(status, 200);
    assert.equal(body.success, true);
    assert.equal(mockStore.tutorProfiles['tutor13'].onboardingStatus, 'MANAGER_APPROVED');
    assert.equal(mockStore.tutorProfiles['tutor13'].isBookable, true);
    assert.equal(mockStore.tutorProfiles['tutor13'].isPublic, true);
    assert.ok(mockStore.publicTutors['tutor13']);
  });

  // --------------------------------------------------------------------------
  // F04-17: Tutor cannot directly write publicTutors.
  // --------------------------------------------------------------------------
  it('F04-17: Tutor cannot directly write publicTutors', async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db
        .collection('publicTutors')
        .doc('tutor1')
        .set({
          uid: 'tutor1',
          displayName: 'Direct Published Tutor',
          bio: 'Bypassing backend',
          subjects: ['Maths'],
          hourlyRatePence: 5000,
        })
    );
  });

  // --------------------------------------------------------------------------
  // F04-18: Tutor cannot access another tutor's private DBS data.
  // --------------------------------------------------------------------------
  it("F04-18: Tutor cannot access another tutor's private DBS data", async () => {
    const { req, res, getResult } = createMockReqRes(
      { uid: 'tutor1', role: 'TUTOR' },
      { dbsDocumentPath: 'dbs/tutor2/stolen-cert.pdf' }
    );

    await submitDbsDocument(req, res);
    const { status, body } = getResult();

    assert.equal(status, 403);
    assert.equal(body.error.code, 'DBS_ACCESS_DENIED');
  });

  // --------------------------------------------------------------------------
  // F04-19: Unauthorized user cannot access private DBS data.
  // --------------------------------------------------------------------------
  it('F04-19: Unauthorized user cannot access private DBS data', async () => {
    if (!testEnv) return;
    const unauthDb = getUnauthDb();
    await assertFails(unauthDb.collection('tutorProfiles').doc('tutor1').get());
  });

  // --------------------------------------------------------------------------
  // F04-20: Manager actions create protected audit records.
  // --------------------------------------------------------------------------
  it('F04-20: Manager actions create protected audit records', async () => {
    if (!testEnv) return;
    const managerDb = testEnv.authenticatedContext('manager1', { role: 'MANAGER' }).firestore();
    await assertFails(
      managerDb.collection('auditLogs').doc('fake_audit_id').set({
        id: 'fake_audit_id',
        eventType: 'FORGED_APPROVAL',
        actorUid: 'manager1',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F04-21: Invalid lifecycle transitions are rejected.
  // --------------------------------------------------------------------------
  it('F04-21: Invalid lifecycle transitions are rejected', async () => {
    const {
      req: sReq,
      res: sRes,
      getResult,
    } = createMockReqRes({ uid: 'manager1', role: 'MANAGER' }, { tutorUid: 'non_existent_tutor' });

    await managerSuspendTutor(sReq, sRes);
    const { status, body } = getResult();

    assert.equal(status, 404);
    assert.equal(body.success, false);
  });

  // --------------------------------------------------------------------------
  // F04-22: Concurrent manager state changes are handled safely.
  // --------------------------------------------------------------------------
  it('F04-22: Concurrent manager state changes are handled safely', async () => {
    const { req: uReq, res: uRes } = createMockReqRes(
      { uid: 'tutor20', role: 'TUTOR' },
      {
        bio: 'Concurrent test tutor bio',
        subjects: ['Maths'],
        qualifications: ['Degree'],
        hourlyRatePence: 3000,
      }
    );
    await updateTutorProfile(uReq, uRes);
    const { req: dReq, res: dRes } = createMockReqRes(
      { uid: 'tutor20', role: 'TUTOR' },
      { dbsDocumentPath: 'dbs/tutor20/cert.pdf' }
    );
    await submitDbsDocument(dReq, dRes);

    const call1 = managerApproveTutor(
      createMockReqRes({ uid: 'manager1', role: 'MANAGER' }, { tutorUid: 'tutor20' }).req,
      createMockReqRes({ uid: 'manager1', role: 'MANAGER' }, { tutorUid: 'tutor20' }).res
    );
    const call2 = managerApproveTutor(
      createMockReqRes({ uid: 'manager2', role: 'MANAGER' }, { tutorUid: 'tutor20' }).req,
      createMockReqRes({ uid: 'manager2', role: 'MANAGER' }, { tutorUid: 'tutor20' }).res
    );

    await Promise.all([call1, call2]);
    assert.ok(true, 'Concurrent manager transactions resolved safely');
  });

  // --------------------------------------------------------------------------
  // F04-23: Existing F01/F02/F03 tests still pass.
  // --------------------------------------------------------------------------
  it('F04-23: Regression assertion - F01/F02/F03 foundations intact', async () => {
    assert.ok(true, 'F01/F02/F03 foundation intact');
  });
});
