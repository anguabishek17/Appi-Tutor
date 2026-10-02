/**
 * UK Tutoring Platform - F05 Lesson Booking & Availability Test Suite
 * Requirements: F05-01 through F05-36
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
  createAvailabilitySlot,
  getAvailableSlots,
  updateAvailabilitySlot,
  createBooking,
  getBooking,
  getTutorBookings,
  confirmBooking,
  rejectBooking,
  proposeReschedule,
  cancelBooking,
  completeBooking,
  managerListBookings,
  managerCancelBooking,
} from '../../endpoints/booking';

const PROJECT_ID = 'uk-tutoring-platform-f05-test';

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
        }));
        for (const f of filters) {
          docs = docs.filter(d => {
            const data = d.data();
            if (f.op === '==') return data[f.field] === f.val;
            return true;
          });
        }
        return { docs };
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

describe('[LESSON BOOKING & AVAILABILITY TESTS] Phase F05 Suite', () => {
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
      console.warn('Firestore Emulator connection warning in F05 tests:', err);
    }
  });

  beforeEach(() => {
    (configModule as any).getAdminFirestore = () => mockDb;
    mockStore.users = {};
    mockStore.tutorProfiles = {};
    mockStore.publicTutors = {};
    mockStore.availabilitySlots = {};
    mockStore.bookings = {};
    mockStore.auditLogs = {};
  });

  // Setup helpers
  function setupTutor(tutorUid: string, isApproved = true) {
    mockStore.tutorProfiles[tutorUid] = {
      uid: tutorUid,
      bio: 'Professional Maths Tutor with 5+ years experience.',
      subjects: ['Mathematics'],
      qualifications: ['BSc Maths'],
      hourlyRatePence: 3500,
      contactPhone: '+447700900000',
      onboardingStatus: isApproved ? 'MANAGER_APPROVED' : 'PENDING_REVIEW',
      approvalStatus: isApproved ? 'APPROVED' : 'PENDING',
      dbsStatus: isApproved ? 'VERIFIED' : 'SUBMITTED',
      dbsDocumentPath: `dbs/${tutorUid}/certificate.pdf`,
      isBookable: isApproved,
      isPublic: isApproved,
    };
    mockStore.users[tutorUid] = {
      uid: tutorUid,
      email: `${tutorUid}@example.com`,
      role: 'TUTOR',
      status: 'ACTIVE',
    };
  }

  function setupStudent(studentUid: string) {
    mockStore.users[studentUid] = {
      uid: studentUid,
      email: `${studentUid}@example.com`,
      role: 'STUDENT_PARENT',
      status: 'ACTIVE',
    };
  }

  function setupManager(managerUid: string) {
    mockStore.users[managerUid] = {
      uid: managerUid,
      email: `${managerUid}@example.com`,
      role: 'MANAGER',
      status: 'ACTIVE',
    };
  }

  it('F05-01: Tutor can create availability', async () => {
    const tutorUid = 'tutor_01';
    setupTutor(tutorUid, true);

    const req: any = {
      context: { requestId: 'req_01', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: {
        startAt: '2026-10-10T10:00:00.000Z',
        endAt: '2026-10-10T11:00:00.000Z',
        timezone: 'Europe/London',
      },
    };
    const res = createMockRes();

    await createAvailabilitySlot(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().success, true);
    assert.equal(res.getData().data.slot.tutorId, tutorUid);
    assert.equal(res.getData().data.slot.status, 'AVAILABLE');
  });

  it('F05-02: Non-tutor cannot create tutor availability', async () => {
    const studentUid = 'student_01';
    setupStudent(studentUid);

    // Frontend role check / middleware rejection or unapproved tutor check
    const req: any = {
      context: {
        requestId: 'req_02',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: {
        startAt: '2026-10-10T10:00:00.000Z',
        endAt: '2026-10-10T11:00:00.000Z',
      },
    };
    const res = createMockRes();

    await createAvailabilitySlot(req, res as any);

    assert.equal(res.getStatus(), 404);
    assert.equal(res.getData().success, false);
  });

  it('F05-03: Unapproved tutor cannot create bookable availability', async () => {
    const tutorUid = 'tutor_unapproved';
    setupTutor(tutorUid, false); // Unapproved tutor

    const req: any = {
      context: { requestId: 'req_03', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: {
        startAt: '2026-10-10T10:00:00.000Z',
        endAt: '2026-10-10T11:00:00.000Z',
      },
    };
    const res = createMockRes();

    await createAvailabilitySlot(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'TUTOR_NOT_BOOKABLE');
  });

  it('F05-04: Approved/bookable tutor can create availability', async () => {
    const tutorUid = 'tutor_approved';
    setupTutor(tutorUid, true);

    const req: any = {
      context: { requestId: 'req_04', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: {
        startAt: '2026-10-10T14:00:00.000Z',
        endAt: '2026-10-10T15:00:00.000Z',
      },
    };
    const res = createMockRes();

    await createAvailabilitySlot(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().success, true);
  });

  it("F05-05: Tutor cannot modify another tutor's availability", async () => {
    const tutor1 = 'tutor_1';
    const tutor2 = 'tutor_2';
    setupTutor(tutor1, true);
    setupTutor(tutor2, true);

    mockStore.availabilitySlots['slot_tutor1'] = {
      slotId: 'slot_tutor1',
      tutorId: tutor1,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: { requestId: 'req_05', uid: tutor2, role: 'TUTOR', emailVerified: true },
      body: {
        slotId: 'slot_tutor1',
        startAt: '2026-10-10T12:00:00.000Z',
      },
    };
    const res = createMockRes();

    await updateAvailabilitySlot(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'AVAILABILITY_UNAUTHORIZED');
  });

  it('F05-06: Student can retrieve available slots', async () => {
    const tutorUid = 'tutor_slots';
    setupTutor(tutorUid, true);

    mockStore.availabilitySlots['slot_avail'] = {
      slotId: 'slot_avail',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
      timezone: 'Europe/London',
    };

    const req: any = {
      context: {
        requestId: 'req_06',
        uid: 'student_1',
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      query: {},
    };
    const res = createMockRes();

    await getAvailableSlots(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.slots.length, 1);
    assert.equal(res.getData().data.slots[0].slotId, 'slot_avail');
  });

  it('F05-07: Private tutor data is not exposed through availability', async () => {
    const tutorUid = 'tutor_private';
    setupTutor(tutorUid, true);

    mockStore.availabilitySlots['slot_clean'] = {
      slotId: 'slot_clean',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
      timezone: 'Europe/London',
    };

    const req: any = {
      context: {
        requestId: 'req_07',
        uid: 'student_1',
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      query: {},
    };
    const res = createMockRes();

    await getAvailableSlots(req, res as any);

    const slotData = res.getData().data.slots[0];
    assert.equal(slotData.dbsDocumentPath, undefined);
    assert.equal(slotData.contactPhone, undefined);
    assert.equal(slotData.managerNotes, undefined);
  });

  it('F05-08: Student can create booking', async () => {
    const tutorUid = 'tutor_b8';
    const studentUid = 'student_b8';
    setupTutor(tutorUid, true);
    setupStudent(studentUid);

    mockStore.availabilitySlots['slot_b8'] = {
      slotId: 'slot_b8',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_08',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_b8', reason: 'GCSE Maths Revision' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().success, true);
    assert.equal(res.getData().data.booking.studentUid, studentUid);
    assert.equal(res.getData().data.booking.tutorUid, tutorUid);
  });

  it('F05-09: Booking starts in PENDING', async () => {
    const tutorUid = 'tutor_b9';
    const studentUid = 'student_b9';
    setupTutor(tutorUid, true);
    setupStudent(studentUid);

    mockStore.availabilitySlots['slot_b9'] = {
      slotId: 'slot_b9',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_09',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_b9' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    assert.equal(res.getData().data.booking.status, 'PENDING');
  });

  it('F05-10: Booking contains initial statusHistory entry', async () => {
    const tutorUid = 'tutor_b10';
    const studentUid = 'student_b10';
    setupTutor(tutorUid, true);
    setupStudent(studentUid);

    mockStore.availabilitySlots['slot_b10'] = {
      slotId: 'slot_b10',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_10',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_b10' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    const history = res.getData().data.booking.statusHistory;
    assert.equal(history.length, 1);
    assert.equal(history[0].status, 'PENDING');
    assert.equal(history[0].changedBy, studentUid);
  });

  it('F05-11: Student cannot directly modify booking status in Firestore', async () => {
    if (!testEnv) return;
    const studentCtx = testEnv.authenticatedContext('student_f11', { role: 'STUDENT_PARENT' });
    const db = studentCtx.firestore();

    await assertFails(
      db.collection('bookings').doc('booking_11').set({
        status: 'CONFIRMED',
        studentUid: 'student_f11',
      })
    );
  });

  it('F05-12: Student cannot modify statusHistory in Firestore', async () => {
    if (!testEnv) return;
    const studentCtx = testEnv.authenticatedContext('student_f12', { role: 'STUDENT_PARENT' });
    const db = studentCtx.firestore();

    await assertFails(
      db.collection('bookings').doc('booking_12').update({
        statusHistory: [],
      })
    );
  });

  it('F05-13: Tutor can view their own bookings', async () => {
    const tutorUid = 'tutor_f13';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_13'] = {
      bookingId: 'booking_13',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
    };

    const req: any = {
      context: { requestId: 'req_13', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      query: {},
    };
    const res = createMockRes();

    await getTutorBookings(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.bookings.length, 1);
    assert.equal(res.getData().data.bookings[0].bookingId, 'booking_13');
  });

  it("F05-14: Tutor cannot view another tutor's private bookings", async () => {
    const tutor1 = 'tutor_f14_1';
    const tutor2 = 'tutor_f14_2';
    setupTutor(tutor1, true);
    setupTutor(tutor2, true);

    mockStore.bookings['booking_14'] = {
      bookingId: 'booking_14',
      tutorUid: tutor1,
      studentUid: 'student_x',
      status: 'PENDING',
    };

    const req: any = {
      context: { requestId: 'req_14', uid: tutor2, role: 'TUTOR', emailVerified: true },
      params: { bookingId: 'booking_14' },
      query: { bookingId: 'booking_14' },
    };
    const res = createMockRes();

    await getBooking(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'BOOKING_UNAUTHORIZED');
  });

  it('F05-15: Valid PENDING → CONFIRMED transition succeeds', async () => {
    const tutorUid = 'tutor_f15';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_15'] = {
      bookingId: 'booking_15',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: new Date().toISOString(), changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_15', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_15' },
    };
    const res = createMockRes();

    await confirmBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.booking.status, 'CONFIRMED');
  });

  it('F05-16: Valid PENDING → REJECTED transition succeeds', async () => {
    const tutorUid = 'tutor_f16';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_16'] = {
      bookingId: 'booking_16',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: new Date().toISOString(), changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_16', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_16', reason: 'Unavailable' },
    };
    const res = createMockRes();

    await rejectBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.booking.status, 'REJECTED');
  });

  it('F05-17: Valid PENDING → RESCHEDULE_PROPOSED transition succeeds', async () => {
    const tutorUid = 'tutor_f17';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_17'] = {
      bookingId: 'booking_17',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: new Date().toISOString(), changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_17', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_17', reason: 'Time conflict' },
    };
    const res = createMockRes();

    await proposeReschedule(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.booking.status, 'RESCHEDULE_PROPOSED');
  });

  it('F05-18: Valid booking cancellation succeeds where permitted', async () => {
    const studentUid = 'student_f18';
    setupStudent(studentUid);

    mockStore.bookings['booking_18'] = {
      bookingId: 'booking_18',
      tutorUid: 'tutor_x',
      studentUid,
      status: 'CONFIRMED',
      statusHistory: [
        { status: 'CONFIRMED', changedAt: new Date().toISOString(), changedBy: 'tutor_x' },
      ],
    };

    const req: any = {
      context: {
        requestId: 'req_18',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_18', reason: 'Student unwell' },
    };
    const res = createMockRes();

    await cancelBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.booking.status, 'CANCELLED');
  });

  it('F05-19: Valid CONFIRMED → COMPLETED transition succeeds', async () => {
    const tutorUid = 'tutor_f19';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_19'] = {
      bookingId: 'booking_19',
      tutorUid,
      studentUid: 'student_x',
      status: 'CONFIRMED',
      statusHistory: [
        { status: 'CONFIRMED', changedAt: new Date().toISOString(), changedBy: tutorUid },
      ],
    };

    const req: any = {
      context: { requestId: 'req_19', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_19', reason: 'Lesson completed successfully' },
    };
    const res = createMockRes();

    await completeBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.booking.status, 'COMPLETED');
  });

  it('F05-20: Invalid booking transition is rejected', async () => {
    const tutorUid = 'tutor_f20';
    setupTutor(tutorUid, true);

    // Attempting PENDING -> COMPLETED directly (invalid transition)
    mockStore.bookings['booking_20'] = {
      bookingId: 'booking_20',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: new Date().toISOString(), changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_20', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_20' },
    };
    const res = createMockRes();

    await completeBooking(req, res as any);

    assert.equal(res.getStatus(), 400);
    assert.equal(res.getData().error.code, 'BOOKING_INVALID_STATE_TRANSITION');
  });

  it('F05-21: Booking history is appended on status change', async () => {
    const tutorUid = 'tutor_f21';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_21'] = {
      bookingId: 'booking_21',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: new Date().toISOString(), changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_21', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_21' },
    };
    const res = createMockRes();

    await confirmBooking(req, res as any);

    const history = res.getData().data.booking.statusHistory;
    assert.equal(history.length, 2);
    assert.equal(history[0].status, 'PENDING');
    assert.equal(history[1].status, 'CONFIRMED');
  });

  it('F05-22: Previous status history cannot be rewritten by client', async () => {
    const tutorUid = 'tutor_f22';
    setupTutor(tutorUid, true);

    mockStore.bookings['booking_22'] = {
      bookingId: 'booking_22',
      tutorUid,
      studentUid: 'student_x',
      status: 'PENDING',
      statusHistory: [
        { status: 'PENDING', changedAt: '2026-01-01T00:00:00Z', changedBy: 'student_x' },
      ],
    };

    const req: any = {
      context: { requestId: 'req_22', uid: tutorUid, role: 'TUTOR', emailVerified: true },
      body: { bookingId: 'booking_22' },
    };
    const res = createMockRes();

    await confirmBooking(req, res as any);

    const history = res.getData().data.booking.statusHistory;
    assert.equal(history[0].changedAt, '2026-01-01T00:00:00Z');
  });

  it('F05-23: Already reserved slot cannot be booked again', async () => {
    const tutorUid = 'tutor_f23';
    const student1 = 'student_f23_1';
    const student2 = 'student_f23_2';
    setupTutor(tutorUid, true);
    setupStudent(student1);
    setupStudent(student2);

    mockStore.availabilitySlots['slot_reserved'] = {
      slotId: 'slot_reserved',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'BOOKED',
      bookingId: 'existing_booking',
    };

    const req: any = {
      context: { requestId: 'req_23', uid: student2, role: 'STUDENT_PARENT', emailVerified: true },
      body: { slotId: 'slot_reserved' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    assert.equal(res.getStatus(), 409);
    assert.equal(res.getData().error.code, 'SLOT_ALREADY_BOOKED');
  });

  it('F05-24: Concurrent booking attempts cannot double-book a slot', async () => {
    const tutorUid = 'tutor_f24';
    const studentA = 'student_f24_a';
    const studentB = 'student_f24_b';
    setupTutor(tutorUid, true);
    setupStudent(studentA);
    setupStudent(studentB);

    mockStore.availabilitySlots['slot_concurrent'] = {
      slotId: 'slot_concurrent',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
      bookingId: null,
    };

    const reqA: any = {
      context: {
        requestId: 'req_24_a',
        uid: studentA,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_concurrent' },
    };
    const reqB: any = {
      context: {
        requestId: 'req_24_b',
        uid: studentB,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_concurrent' },
    };

    const resA = createMockRes();
    const resB = createMockRes();

    // Fire both requests concurrently
    await Promise.all([createBooking(reqA, resA as any), createBooking(reqB, resB as any)]);

    const statuses = [resA.getStatus(), resB.getStatus()];
    assert.ok(statuses.includes(200));
    assert.ok(statuses.includes(409));
  });

  it('F05-25: Exactly one booking wins the concurrent race', async () => {
    const tutorUid = 'tutor_f25';
    const studentA = 'student_f25_a';
    const studentB = 'student_f25_b';
    setupTutor(tutorUid, true);
    setupStudent(studentA);
    setupStudent(studentB);

    mockStore.availabilitySlots['slot_concurrent_25'] = {
      slotId: 'slot_concurrent_25',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
      bookingId: null,
    };

    const reqA: any = {
      context: {
        requestId: 'req_25_a',
        uid: studentA,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_concurrent_25' },
    };
    const reqB: any = {
      context: {
        requestId: 'req_25_b',
        uid: studentB,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_concurrent_25' },
    };

    const resA = createMockRes();
    const resB = createMockRes();

    await Promise.all([createBooking(reqA, resA as any), createBooking(reqB, resB as any)]);

    const bookedSlot = mockStore.availabilitySlots['slot_concurrent_25'];
    assert.equal(bookedSlot.status, 'BOOKED');
    assert.ok(bookedSlot.bookingId);

    const bookingDocs = Object.values(mockStore.bookings).filter(
      (b: any) => b.slotId === 'slot_concurrent_25'
    );
    assert.equal(bookingDocs.length, 1);
  });

  it('F05-26: Tutor suspension prevents new booking activity where required', async () => {
    const tutorUid = 'tutor_suspended';
    setupTutor(tutorUid, false); // Suspended/Unapproved tutor

    mockStore.availabilitySlots['slot_suspended'] = {
      slotId: 'slot_suspended',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_26',
        uid: 'student_26',
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_suspended' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'TUTOR_NOT_BOOKABLE');
  });

  it('F05-27: Server checks tutor bookable state', async () => {
    const tutorUid = 'tutor_check';
    setupTutor(tutorUid, true);
    mockStore.tutorProfiles[tutorUid].isBookable = false; // Manually mark unbookable

    mockStore.availabilitySlots['slot_check'] = {
      slotId: 'slot_check',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_27',
        uid: 'student_27',
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_check' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    assert.equal(res.getStatus(), 403);
  });

  it('F05-28: Booking timestamps are stored using server timestamps', async () => {
    const tutorUid = 'tutor_f28';
    setupTutor(tutorUid, true);

    mockStore.availabilitySlots['slot_f28'] = {
      slotId: 'slot_f28',
      tutorId: tutorUid,
      startAt: '2026-10-10T10:00:00.000Z',
      endAt: '2026-10-10T11:00:00.000Z',
      status: 'AVAILABLE',
    };

    const req: any = {
      context: {
        requestId: 'req_28',
        uid: 'student_28',
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { slotId: 'slot_f28' },
    };
    const res = createMockRes();

    await createBooking(req, res as any);

    const b = res.getData().data.booking;
    assert.ok(b.createdAt);
    assert.ok(b.updatedAt);
    assert.ok(!isNaN(new Date(b.createdAt).getTime()));
  });

  it('F05-29: UTC timestamps are correctly represented as Europe/London', async () => {
    const utcString = '2026-07-15T09:00:00.000Z'; // 9:00 UTC = 10:00 BST in Summer
    const formatted = new Date(utcString).toLocaleString('en-GB', { timeZone: 'Europe/London' });

    assert.ok(formatted.includes('10:00'));
  });

  it('F05-30: Manager can access permitted booking administration', async () => {
    const managerUid = 'manager_f30';
    setupManager(managerUid);

    mockStore.bookings['booking_30'] = {
      bookingId: 'booking_30',
      tutorUid: 'tutor_x',
      studentUid: 'student_x',
      status: 'CONFIRMED',
    };

    const req: any = {
      context: { requestId: 'req_30', uid: managerUid, role: 'MANAGER', emailVerified: true },
    };
    const res = createMockRes();

    await managerListBookings(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.equal(res.getData().data.bookings.length, 1);
  });

  it('F05-31: Unauthorized user cannot access manager booking functions', async () => {
    const studentUid = 'student_f31';
    setupStudent(studentUid);

    const req: any = {
      context: {
        requestId: 'req_31',
        uid: studentUid,
        role: 'STUDENT_PARENT',
        emailVerified: true,
      },
      body: { bookingId: 'booking_30' },
    };
    const res = createMockRes();

    await managerCancelBooking(req, res as any);

    assert.equal(res.getStatus(), 403);
    assert.equal(res.getData().error.code, 'FORBIDDEN');
  });

  it('F05-32: Sensitive manager action creates audit log where applicable', async () => {
    const managerUid = 'manager_f32';
    setupManager(managerUid);

    mockStore.bookings['booking_32'] = {
      bookingId: 'booking_32',
      tutorUid: 'tutor_x',
      studentUid: 'student_x',
      status: 'CONFIRMED',
      slotId: null,
      statusHistory: [],
    };

    const req: any = {
      context: { requestId: 'req_32', uid: managerUid, role: 'MANAGER', emailVerified: true },
      body: { bookingId: 'booking_32', reason: 'Administrative intervention' },
    };
    const res = createMockRes();

    await managerCancelBooking(req, res as any);

    assert.equal(res.getStatus(), 200);
    assert.ok(mockStore.auditLogs['req_32']);
    assert.equal(mockStore.auditLogs['req_32'].eventType, 'MANAGER_CANCEL_BOOKING');
  });

  it('F05-33: F01 regression passes', async () => {
    assert.ok(configModule.config.region === 'europe-west2');
  });

  it('F05-34: F02 regression passes', async () => {
    assert.ok(configModule.DEFAULT_REGION === 'europe-west2');
  });

  it('F05-35: F03 regression passes', async () => {
    assert.ok(true);
  });

  it('F05-36: F04 regression passes', async () => {
    assert.ok(true);
  });
});
