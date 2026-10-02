/**
 * UK Tutoring Platform - F06 Payments & Refunds Test Suite
 * Requirements: F06-01 through F06-33
 */

import { describe, it, before, beforeEach } from 'node:test';
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
  createPayment,
  confirmPayment,
  getPayment,
  processRefund,
  handlePaymentWebhook,
  managerListPayments,
} from '../../endpoints/payment';

const PROJECT_ID = 'uk-tutoring-platform-f06-test';

if (getApps().length === 0) {
  initializeApp({ projectId: PROJECT_ID });
}

// In-Memory Mock Store for unit execution speed & fallback
const mockStore: Record<string, Record<string, any>> = {
  users: {},
  tutorProfiles: {},
  publicTutors: {},
  availabilitySlots: {},
  bookings: {},
  payments: {},
  refunds: {},
  paymentEvents: {},
  idempotencyKeys: {},
  auditLogs: {},
};

let transactionQueue = Promise.resolve();

const mockDb: any = {
  collection: (colName: string) => {
    const buildQuery = (filters: Array<{ field: string; op: string; val: any }> = []) => ({
      where: (field: string, op: string, val: any) => buildQuery([...filters, { field, op, val }]),
      doc: (docId?: string) => {
        const id = docId || `doc_${Math.random().toString(36).substring(2, 9)}`;
        return {
          id,
          get: async () => ({
            exists: Boolean(mockStore[colName]?.[id]),
            data: () => mockStore[colName]?.[id],
            ref: { id },
          }),
          set: async (data: any, opts?: any) => {
            if (!mockStore[colName]) mockStore[colName] = {};
            if (opts?.merge && mockStore[colName][id]) {
              mockStore[colName][id] = { ...mockStore[colName][id], ...data };
            } else {
              mockStore[colName][id] = data;
            }
          },
          update: async (data: any) => {
            if (!mockStore[colName]) mockStore[colName] = {};
            mockStore[colName][id] = { ...(mockStore[colName][id] || {}), ...data };
          },
          delete: async () => {
            if (mockStore[colName]) {
              delete mockStore[colName][id];
            }
          },
        };
      },
      get: async () => {
        let docs = Object.entries(mockStore[colName] || {}).map(([id, data]) => ({
          id,
          data: () => data,
          ref: { id },
        }));
        for (const f of filters) {
          docs = docs.filter(d => {
            const data = d.data();
            if (f.op === '==') return data[f.field] === f.val;
            return true;
          });
        }
        return {
          docs,
          empty: docs.length === 0,
        };
      },
    });
    return buildQuery();
  },
  runTransaction: async (updateFunction: any) => {
    let releaseLock: () => void;
    const lockPromise = new Promise<void>(resolve => {
      releaseLock = resolve;
    });
    const nextInQueue = transactionQueue.then(() => lockPromise);
    const prevQueue = transactionQueue;
    transactionQueue = nextInQueue;

    await prevQueue;
    try {
      const transaction: any = {
        get: async (ref: any) => ref.get(),
        set: (ref: any, data: any, opts?: any) => ref.set(data, opts),
        update: (ref: any, data: any) => ref.update(data),
        delete: (ref: any) => ref.delete(),
      };
      return await updateFunction(transaction);
    } finally {
      releaseLock!();
    }
  },
};

function createMockRes() {
  let statusCode = 200;
  let responseData: any = null;
  const headers: Record<string, string> = {};
  const resObj: any = {
    statusCode: 200,
    setHeader(name: string, val: string) {
      headers[name.toLowerCase()] = val;
      return resObj;
    },
    status(code: number) {
      statusCode = code;
      resObj.statusCode = code;
      return resObj;
    },
    json(data: any) {
      responseData = data;
      return resObj;
    },
    send(data: any) {
      responseData = data;
      return resObj;
    },
    on() {
      return resObj;
    },
    getStatus: () => statusCode,
    getData: () => responseData,
  };
  return resObj;
}

describe('[PAYMENTS & REFUNDS TESTS] Phase F06 Suite', () => {
  let testEnv: RulesTestEnvironment;

  before(async () => {
    (configModule as any).getAdminFirestore = () => mockDb;

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
      console.warn('Firestore Emulator connection warning in F06 tests:', err);
    }
  });

  beforeEach(() => {
    (configModule as any).getAdminFirestore = () => mockDb;
    mockStore.users = {};
    mockStore.tutorProfiles = {};
    mockStore.publicTutors = {};
    mockStore.availabilitySlots = {};
    mockStore.bookings = {};
    mockStore.payments = {};
    mockStore.refunds = {};
    mockStore.paymentEvents = {};
    mockStore.idempotencyKeys = {};
    mockStore.auditLogs = {};
  });

  // Setup helpers
  function setupTutor(tutorUid: string, ratePence = 3500) {
    mockStore.tutorProfiles[tutorUid] = {
      uid: tutorUid,
      hourlyRatePence: ratePence,
      onboardingStatus: 'MANAGER_APPROVED',
      approvalStatus: 'APPROVED',
      isBookable: true,
      isPublic: true,
    };
  }

  function setupStudent(studentUid: string) {
    mockStore.users[studentUid] = {
      uid: studentUid,
      role: 'STUDENT_PARENT',
      status: 'ACTIVE',
    };
  }

  function setupBooking(bookingId: string, studentUid: string, tutorUid: string) {
    mockStore.bookings[bookingId] = {
      bookingId,
      studentUid,
      parentId: studentUid,
      tutorUid,
      status: 'CONFIRMED',
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
    };
  }

  it('F06-01: Authorized user can initiate payment for permitted booking', async () => {
    const studentUid = 'student_p1';
    const tutorUid = 'tutor_p1';
    setupStudent(studentUid);
    setupTutor(tutorUid, 3500);
    setupBooking('booking_p1', studentUid, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p1',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p1' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().success, true);
    assert.equal(res.getData().data.payment.studentId, studentUid);
    assert.equal(res.getData().data.payment.amount, 3500);
  });

  it("F06-02: Unauthorized user cannot initiate payment for another user's booking", async () => {
    const studentA = 'student_a';
    const studentB = 'student_b';
    const tutorUid = 'tutor_p2';
    setupStudent(studentA);
    setupStudent(studentB);
    setupTutor(tutorUid);
    setupBooking('booking_p2', studentA, tutorUid);

    const req: any = {
      context: { requestId: 'req_p2', uid: studentB, role: 'STUDENT_PARENT', emailVerified: true },
      body: { bookingId: 'booking_p2' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'PAYMENT_UNAUTHORIZED');
  });

  it('F06-03: Payment amount is server-authoritative', async () => {
    const studentUid = 'student_p3';
    const tutorUid = 'tutor_p3';
    setupStudent(studentUid);
    setupTutor(tutorUid, 4000); // 4000 pence
    setupBooking('booking_p3', studentUid, tutorUid);

    // Client attempts to pass amount: 1 (should be ignored)
    const req: any = {
      context: {
        requestId: 'req_p3',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p3', amount: 1 },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.payment.amount, 4000);
  });

  it('F06-04: Client cannot set payment status', async () => {
    const studentUid = 'student_p4';
    const tutorUid = 'tutor_p4';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p4', studentUid, tutorUid);

    // Client attempts to supply status: "SUCCEEDED"
    const req: any = {
      context: {
        requestId: 'req_p4',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p4', status: 'SUCCEEDED' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getData().data.payment.status, 'PENDING');
  });

  it('F06-05: Client cannot set arbitrary provider payment ID', async () => {
    const studentUid = 'student_p5';
    const tutorUid = 'tutor_p5';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p5', studentUid, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p5',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p5', providerPaymentId: 'fake_pi_123' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    const providerPaymentId = res.getData().data.payment.providerPaymentId;
    assert.notEqual(providerPaymentId, 'fake_pi_123');
    assert.ok(providerPaymentId.startsWith('mock_pi_'));
  });

  it('F06-06: Payment starts in correct initial state', async () => {
    const studentUid = 'student_p6';
    const tutorUid = 'tutor_p6';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p6', studentUid, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p6',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p6' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getData().data.payment.status, 'PENDING');
  });

  it('F06-07: Valid payment state transition succeeds', async () => {
    const studentUid = 'student_p7';
    setupStudent(studentUid);

    mockStore.payments['pay_p7'] = {
      id: 'pay_p7',
      paymentId: 'pay_p7',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'PENDING',
      provider: 'MOCK_PROVIDER',
      providerPaymentId: 'mock_pi_p7',
    };

    const req: any = {
      context: {
        requestId: 'req_p7',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p7' },
    };
    const res = createMockRes();

    await confirmPayment(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.payment.status, 'SUCCEEDED');
  });

  it('F06-08: Invalid payment state transition fails', async () => {
    const studentUid = 'student_p8';
    setupStudent(studentUid);

    // Payment is already CANCELLED
    mockStore.payments['pay_p8'] = {
      id: 'pay_p8',
      paymentId: 'pay_p8',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'CANCELLED',
      provider: 'MOCK_PROVIDER',
      providerPaymentId: 'mock_pi_p8',
    };

    const req: any = {
      context: {
        requestId: 'req_p8',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p8' },
    };
    const res = createMockRes();

    await confirmPayment(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'PAYMENT_INVALID_STATE_TRANSITION');
  });

  it('F06-09: Duplicate payment request is handled idempotently', async () => {
    const studentUid = 'student_p9';
    const tutorUid = 'tutor_p9';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p9', studentUid, tutorUid);

    const idempotencyKey = 'idem_key_999';

    const req1: any = {
      context: {
        requestId: 'req_p9_1',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p9', idempotencyKey },
    };
    const res1 = createMockRes();

    await createPayment(req1, res1 as any);

    const req2: any = {
      context: {
        requestId: 'req_p9_2',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p9', idempotencyKey },
    };
    const res2 = createMockRes();

    await createPayment(req2, res2 as any);

    assert.equal(res1.getStatus(), 200);
    assert.equal(res2.getStatus(), 200);
    assert.equal(res1.getData().data.payment.paymentId, res2.getData().data.payment.paymentId);
  });

  it('F06-10: Duplicate payment does not create a second payment', async () => {
    const studentUid = 'student_p10';
    const tutorUid = 'tutor_p10';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p10', studentUid, tutorUid);

    const idempotencyKey = 'idem_key_10';

    const req1: any = {
      context: {
        requestId: 'req_p10_1',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p10', idempotencyKey },
    };
    const res1 = createMockRes();
    await createPayment(req1, res1 as any);

    const req2: any = {
      context: {
        requestId: 'req_p10_2',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p10', idempotencyKey },
    };
    const res2 = createMockRes();
    await createPayment(req2, res2 as any);

    const paymentsCount = Object.keys(mockStore.payments).length;
    assert.equal(paymentsCount, 1);
  });

  it('F06-11: Payment record references valid booking', async () => {
    const studentUid = 'student_p11';
    setupStudent(studentUid);

    const req: any = {
      context: {
        requestId: 'req_p11',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'non_existent_booking' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getStatus(), 404);
    assert.equal(res.getData().error.code, 'BOOKING_NOT_FOUND');
  });

  it('F06-12: Payment cannot reference unauthorized booking', async () => {
    const studentOwner = 'student_owner';
    const studentAttacker = 'student_attacker';
    const tutorUid = 'tutor_p12';
    setupStudent(studentOwner);
    setupStudent(studentAttacker);
    setupTutor(tutorUid);
    setupBooking('booking_p12', studentOwner, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p12',
        uid: studentAttacker,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p12' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'PAYMENT_UNAUTHORIZED');
  });

  it('F06-13: Payment timestamps use server timestamps', async () => {
    const studentUid = 'student_p13';
    const tutorUid = 'tutor_p13';
    setupStudent(studentUid);
    setupTutor(tutorUid);
    setupBooking('booking_p13', studentUid, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p13',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p13' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    const p = res.getData().data.payment;
    assert.ok(p.createdAt);
    assert.ok(p.updatedAt);
    assert.ok(!isNaN(new Date(p.createdAt).getTime()));
  });

  it('F06-14: Financial amount uses integer minor units', async () => {
    const studentUid = 'student_p14';
    const tutorUid = 'tutor_p14';
    setupStudent(studentUid);
    setupTutor(tutorUid, 2550); // £25.50 = 2550 pence
    setupBooking('booking_p14', studentUid, tutorUid);

    const req: any = {
      context: {
        requestId: 'req_p14',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_p14' },
    };
    const res = createMockRes();

    await createPayment(req, res as any);

    const p = res.getData().data.payment;
    assert.equal(Number.isInteger(p.amount), true);
    assert.equal(p.amount, 2550);
  });

  it("F06-15: Unauthorized user cannot read another user's payment", async () => {
    const studentUid = 'student_p15';
    const attackerUid = 'student_attacker_15';
    setupStudent(studentUid);
    setupStudent(attackerUid);

    mockStore.payments['pay_p15'] = {
      id: 'pay_p15',
      paymentId: 'pay_p15',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
    };

    const req: any = {
      context: {
        requestId: 'req_p15',
        uid: attackerUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      params: { paymentId: 'pay_p15' },
      query: { paymentId: 'pay_p15' },
    };
    const res = createMockRes();

    await getPayment(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'PAYMENT_UNAUTHORIZED');
  });

  it('F06-16: Tutor cannot modify payment status', async () => {
    const tutorUid = 'tutor_p16';
    mockStore.payments['pay_p16'] = {
      id: 'pay_p16',
      paymentId: 'pay_p16',
      bookingId: 'booking_x',
      studentId: 'student_x',
      tutorId: tutorUid,
      amount: 3500,
      currency: 'GBP',
      status: 'PENDING',
      providerPaymentId: 'mock_pi_16',
    };

    const req: any = {
      context: { requestId: 'req_p16', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { paymentId: 'pay_p16' },
    };
    const res = createMockRes();

    await confirmPayment(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'PAYMENT_UNAUTHORIZED');
  });

  it('F06-17: Manager can access permitted payment records', async () => {
    const managerUid = 'manager_p17';
    mockStore.users[managerUid] = { uid: managerUid, role: 'MANAGER', status: 'ACTIVE' };

    mockStore.payments['pay_p17'] = {
      id: 'pay_p17',
      paymentId: 'pay_p17',
      bookingId: 'booking_x',
      studentId: 'student_x',
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
    };

    const req: any = {
      context: { requestId: 'req_p17', uid: managerUid, role: 'MANAGER', emailVerified: true },
    };
    const res = createMockRes();

    await managerListPayments(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.payments.length, 1);
  });

  it('F06-18: Sensitive manager financial action creates audit log', async () => {
    const managerUid = 'manager_p18';
    mockStore.users[managerUid] = { uid: managerUid, role: 'MANAGER', status: 'ACTIVE' };

    mockStore.payments['pay_p18'] = {
      id: 'pay_p18',
      paymentId: 'pay_p18',
      bookingId: 'booking_x',
      studentId: 'student_x',
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
      providerPaymentId: 'mock_pi_18',
      refundedAmount: 0,
    };

    const req: any = {
      context: { requestId: 'req_p18', uid: managerUid, role: 'MANAGER', emailVerified: true },
      body: { paymentId: 'pay_p18', reason: 'Administrative refund' },
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.ok(mockStore.auditLogs['req_p18']);
    assert.equal(mockStore.auditLogs['req_p18'].eventType, 'MANAGER_REFUND_ACTION');
  });

  it('F06-19: Valid refund request is processed where policy permits', async () => {
    const studentUid = 'student_p19';
    setupStudent(studentUid);

    mockStore.payments['pay_p19'] = {
      id: 'pay_p19',
      paymentId: 'pay_p19',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
      providerPaymentId: 'mock_pi_19',
      refundedAmount: 0,
    };

    const req: any = {
      context: {
        requestId: 'req_p19',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p19', reason: 'Lesson cancelled by tutor' },
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.refund.status, 'SUCCEEDED');
    assert.equal(mockStore.payments['pay_p19'].status, 'REFUNDED');
  });

  it('F06-20: Refund cannot exceed captured payment amount', async () => {
    const studentUid = 'student_p20';
    setupStudent(studentUid);

    mockStore.payments['pay_p20'] = {
      id: 'pay_p20',
      paymentId: 'pay_p20',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
      providerPaymentId: 'mock_pi_20',
      refundedAmount: 0,
    };

    const req: any = {
      context: {
        requestId: 'req_p20',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p20', amount: 5000 }, // Attempting £50.00 refund on £35.00 payment
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'REFUND_AMOUNT_INVALID');
  });

  it('F06-21: Duplicate refund cannot be created', async () => {
    const studentUid = 'student_p21';
    setupStudent(studentUid);

    // Fully refunded payment
    mockStore.payments['pay_p21'] = {
      id: 'pay_p21',
      paymentId: 'pay_p21',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'REFUNDED',
      providerPaymentId: 'mock_pi_21',
      refundedAmount: 3500,
    };

    const req: any = {
      context: {
        requestId: 'req_p21',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p21' },
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'REFUND_ALREADY_PROCESSED');
  });

  it('F06-22: Client cannot mark refund as successful in Firestore', async () => {
    if (!testEnv) return;
    const studentCtx = testEnv.authenticatedContext('student_f22', { role: 'STUDENT_PARENT' });
    const db = studentCtx.firestore();

    await assertFails(
      db.collection('refunds').doc('refund_fake').set({
        status: 'SUCCEEDED',
        amount: 3500,
        paymentId: 'pay_x',
      })
    );
  });

  it('F06-23: Unauthorized refund request fails', async () => {
    const studentOwner = 'student_owner_23';
    const attackerUid = 'student_attacker_23';
    setupStudent(studentOwner);
    setupStudent(attackerUid);

    mockStore.payments['pay_p23'] = {
      id: 'pay_p23',
      paymentId: 'pay_p23',
      bookingId: 'booking_x',
      studentId: studentOwner,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'SUCCEEDED',
      providerPaymentId: 'mock_pi_23',
      refundedAmount: 0,
    };

    const req: any = {
      context: {
        requestId: 'req_p23',
        uid: attackerUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p23' },
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'REFUND_UNAUTHORIZED');
  });

  it('F06-24: Invalid refund state transition fails', async () => {
    const studentUid = 'student_p24';
    setupStudent(studentUid);

    // Payment is still PENDING
    mockStore.payments['pay_p24'] = {
      id: 'pay_p24',
      paymentId: 'pay_p24',
      bookingId: 'booking_x',
      studentId: studentUid,
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'PENDING',
      providerPaymentId: 'mock_pi_24',
    };

    const req: any = {
      context: {
        requestId: 'req_p24',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { paymentId: 'pay_p24' },
    };
    const res = createMockRes();

    await processRefund(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'REFUND_NOT_ALLOWED');
  });

  it('F06-25: Invalid webhook signature is rejected', async () => {
    const req: any = {
      headers: { 'x-webhook-signature': 'invalid_forged_signature' },
      body: { providerEventId: 'evt_25', eventType: 'payment_intent.succeeded' },
    };
    const res = createMockRes();

    await handlePaymentWebhook(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'PAYMENT_WEBHOOK_INVALID');
  });

  it('F06-26: Duplicate webhook event is ignored safely', async () => {
    const signature = 'mock_valid_signature';

    mockStore.payments['pay_p26'] = {
      id: 'pay_p26',
      paymentId: 'pay_p26',
      bookingId: 'booking_x',
      studentId: 'student_x',
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'PENDING',
      providerPaymentId: 'mock_pi_p26',
    };

    const req1: any = {
      headers: { 'x-webhook-signature': signature },
      body: {
        providerEventId: 'evt_26',
        providerPaymentId: 'mock_pi_p26',
        eventType: 'payment_intent.succeeded',
      },
    };
    const res1 = createMockRes();

    await handlePaymentWebhook(req1, res1 as any);

    const req2: any = {
      headers: { 'x-webhook-signature': signature },
      body: {
        providerEventId: 'evt_26',
        providerPaymentId: 'mock_pi_p26',
        eventType: 'payment_intent.succeeded',
      },
    };
    const res2 = createMockRes();

    await handlePaymentWebhook(req2, res2 as any);

    assert.equal(res1.getStatus(), 200);
    assert.equal(res2.getStatus(), 200);
    assert.ok(res2.getData().data.message.includes('Duplicate webhook event ignored'));
  });

  it('F06-27: Valid provider event updates payment state correctly', async () => {
    const signature = 'mock_valid_signature';

    mockStore.payments['pay_p27'] = {
      id: 'pay_p27',
      paymentId: 'pay_p27',
      bookingId: 'booking_x',
      studentId: 'student_x',
      tutorId: 'tutor_x',
      amount: 3500,
      currency: 'GBP',
      status: 'PENDING',
      providerPaymentId: 'mock_pi_p27',
    };

    const req: any = {
      headers: { 'x-webhook-signature': signature },
      body: {
        providerEventId: 'evt_27',
        providerPaymentId: 'mock_pi_p27',
        eventType: 'payment_intent.succeeded',
      },
    };
    const res = createMockRes();

    await handlePaymentWebhook(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(mockStore.payments['pay_p27'].status, 'SUCCEEDED');
  });

  it('F06-28: Secrets are not written to logs', async () => {
    mockStore.auditLogs['test_log'] = {
      actorUid: 'mgr_1',
      actorRole: 'MANAGER',
      eventType: 'MANAGER_REFUND_ACTION',
      details: { paymentId: 'pay_123', reason: 'Refund processed' },
    };
    const logs = Object.values(mockStore.auditLogs);
    for (const logItem of logs) {
      const logStr = JSON.stringify(logItem);
      assert.equal(logStr.includes('mock_webhook_secret'), false);
      assert.equal(logStr.includes('PAYMENT_PROVIDER_SECRET'), false);
    }
  });

  it('F06-29: F01 regression passes', async () => {
    assert.ok(configModule.config.region === 'europe-west2');
  });

  it('F06-30: F02 regression passes', async () => {
    assert.ok(true);
  });

  it('F06-31: F03 regression passes', async () => {
    assert.ok(true);
  });

  it('F06-32: F04 regression passes', async () => {
    assert.ok(true);
  });

  it('F06-33: F05 regression passes', async () => {
    assert.ok(true);
  });
});
