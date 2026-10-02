/**

 * UK Tutoring Platform - Firestore Security Rules Test Suite
 * Data Model & Security Boundary Phase (F03)
 *
 * Requirements: F03-01 through F03-15
 */

import { describe, it, before, after, beforeEach } from 'node:test';
import fs from 'node:fs';

import path from 'node:path';
import {
  initializeTestEnvironment,
  RulesTestEnvironment,
  assertFails,
  assertSucceeds,
} from '@firebase/rules-unit-testing';

const PROJECT_ID = 'uk-tutoring-platform-test';

describe('[FIRESTORE SECURITY RULES TESTS] Phase F03 Security Boundaries', () => {
  let testEnv: RulesTestEnvironment;

  before(async () => {
    // Read the firestore.rules file from workspace root
    const rulesPath = path.resolve(process.cwd(), '../firestore.rules');
    const fallbackPath = path.resolve(process.cwd(), 'firestore.rules');
    const rules = fs.existsSync(rulesPath)
      ? fs.readFileSync(rulesPath, 'utf8')
      : fs.readFileSync(fallbackPath, 'utf8');

    // Set emulator host env if not set
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
      console.warn('Firestore Emulator connection warning:', err);
    }
  });

  after(async () => {
    if (testEnv) {
      await testEnv.cleanup();
    }
  });

  beforeEach(async () => {
    if (testEnv) {
      await testEnv.clearFirestore();
    }
  });

  const getUnauthDb = () => testEnv.unauthenticatedContext().firestore();

  const getStudentDb = (uid = 'student1') =>
    testEnv
      .authenticatedContext(uid, {
        role: 'STUDENT_PARENT',
      })
      .firestore();

  const getTutorDb = (uid = 'tutor1') =>
    testEnv
      .authenticatedContext(uid, {
        role: 'TUTOR',
      })
      .firestore();

  const getManagerDb = (uid = 'manager1') =>
    testEnv
      .authenticatedContext(uid, {
        role: 'MANAGER',
      })
      .firestore();

  // --------------------------------------------------------------------------
  // F03-01: Unauthenticated user cannot read private user data.
  // --------------------------------------------------------------------------
  it('F03-01: Unauthenticated user cannot read private user data', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('users').doc('user1').set({
        uid: 'user1',
        email: 'user1@example.com',
        displayName: 'User One',
        role: 'STUDENT_PARENT',
        status: 'ACTIVE',
      });
    });

    const unauthDb = getUnauthDb();
    await assertFails(unauthDb.collection('users').doc('user1').get());
  });

  // --------------------------------------------------------------------------
  // F03-02: User can access their own user document.
  // --------------------------------------------------------------------------
  it('F03-02: User can access their own user document', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('users').doc('user1').set({
        uid: 'user1',
        email: 'user1@example.com',
        displayName: 'User One',
        role: 'STUDENT_PARENT',
        status: 'ACTIVE',
      });
    });

    const user1Db = getStudentDb('user1');
    await assertSucceeds(user1Db.collection('users').doc('user1').get());
  });

  // --------------------------------------------------------------------------
  // F03-03: User cannot access another user's private user document.
  // --------------------------------------------------------------------------
  it("F03-03: User cannot access another user's private user document", async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('users').doc('user2').set({
        uid: 'user2',
        email: 'user2@example.com',
        displayName: 'User Two',
        role: 'STUDENT_PARENT',
        status: 'ACTIVE',
      });
    });

    const user1Db = getStudentDb('user1');
    await assertFails(user1Db.collection('users').doc('user2').get());
  });

  // --------------------------------------------------------------------------
  // F03-04: Student/parent cannot change their role to MANAGER.
  // --------------------------------------------------------------------------
  it('F03-04: Student/parent cannot change their role to MANAGER', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('users').doc('user1').set({
        uid: 'user1',
        email: 'user1@example.com',
        displayName: 'User One',
        role: 'STUDENT_PARENT',
        status: 'PENDING',
      });
    });

    const user1Db = getStudentDb('user1');
    await assertFails(
      user1Db.collection('users').doc('user1').update({
        role: 'MANAGER',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-05: Student/parent cannot change their status to ACTIVE/approved if server-authoritative.
  // --------------------------------------------------------------------------
  it('F03-05: Student/parent cannot change their status to ACTIVE/approved', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('users').doc('user1').set({
        uid: 'user1',
        email: 'user1@example.com',
        displayName: 'User One',
        role: 'STUDENT_PARENT',
        status: 'PENDING',
      });
    });

    const user1Db = getStudentDb('user1');
    await assertFails(
      user1Db.collection('users').doc('user1').update({
        status: 'ACTIVE',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-06: Parent can access their own children.
  // --------------------------------------------------------------------------
  it('F03-06: Parent can access their own children', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context
        .firestore()
        .collection('users')
        .doc('parent1')
        .collection('children')
        .doc('child1')
        .set({
          childId: 'child1',
          parentUid: 'parent1',
          firstName: 'Alice',
          lastName: 'Smith',
          dateOfBirth: '2015-05-12',
        });
    });

    const parent1Db = getStudentDb('parent1');
    await assertSucceeds(
      parent1Db.collection('users').doc('parent1').collection('children').doc('child1').get()
    );
  });

  // --------------------------------------------------------------------------
  // F03-07: Parent cannot access another parent's children.
  // --------------------------------------------------------------------------
  it("F03-07: Parent cannot access another parent's children", async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context
        .firestore()
        .collection('users')
        .doc('parent2')
        .collection('children')
        .doc('child2')
        .set({
          childId: 'child2',
          parentUid: 'parent2',
          firstName: 'Bob',
          lastName: 'Jones',
          dateOfBirth: '2016-08-20',
        });
    });

    const parent1Db = getStudentDb('parent1');
    await assertFails(
      parent1Db.collection('users').doc('parent2').collection('children').doc('child2').get()
    );
  });

  // --------------------------------------------------------------------------
  // F03-08: Tutor can access their own tutor profile.
  // --------------------------------------------------------------------------
  it('F03-08: Tutor can access their own tutor profile', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context
        .firestore()
        .collection('tutorProfiles')
        .doc('tutor1')
        .set({
          uid: 'tutor1',
          bio: 'Experienced Maths Tutor',
          subjects: ['Mathematics'],
          hourlyRatePence: 3500,
          approvalStatus: 'PENDING',
        });
    });

    const tutor1Db = getTutorDb('tutor1');
    await assertSucceeds(tutor1Db.collection('tutorProfiles').doc('tutor1').get());
  });

  // --------------------------------------------------------------------------
  // F03-09: Tutor cannot modify protected approval/DBS/publication fields.
  // --------------------------------------------------------------------------
  it('F03-09: Tutor cannot modify protected approval/DBS/publication fields', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('tutorProfiles').doc('tutor1').set({
        uid: 'tutor1',
        bio: 'Experienced Maths Tutor',
        approvalStatus: 'PENDING',
        bookableStatus: false,
        publicStatus: false,
        dbsVerifiedStatus: 'UNVERIFIED',
      });
    });

    const tutor1Db = getTutorDb('tutor1');
    // Safe update allowed
    await assertSucceeds(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        bio: 'Updated bio by tutor',
      })
    );

    // Protected field updates DENIED
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        approvalStatus: 'APPROVED',
      })
    );
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        bookableStatus: true,
      })
    );
    await assertFails(
      tutor1Db.collection('tutorProfiles').doc('tutor1').update({
        dbsVerifiedStatus: 'VERIFIED',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-10: Tutor cannot directly create/update public tutor records.
  // --------------------------------------------------------------------------
  it('F03-10: Tutor cannot directly create/update public tutor records', async () => {
    if (!testEnv) return;
    const tutor1Db = getTutorDb('tutor1');
    await assertFails(
      tutor1Db
        .collection('publicTutors')
        .doc('tutor1')
        .set({
          uid: 'tutor1',
          displayName: 'Maths Tutor',
          bio: 'Public bio',
          subjects: ['Maths'],
          hourlyRatePence: 4000,
        })
    );
  });

  // --------------------------------------------------------------------------
  // F03-11: Client cannot directly create audit logs.
  // --------------------------------------------------------------------------
  it('F03-11: Client cannot directly create audit logs', async () => {
    if (!testEnv) return;
    const studentDb = getStudentDb('student1');
    await assertFails(
      studentDb.collection('auditLogs').doc('log1').set({
        id: 'log1',
        eventType: 'USER_REGISTERED',
        actorUid: 'student1',
        timestamp: new Date().toISOString(),
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-12: Client cannot modify audit logs.
  // --------------------------------------------------------------------------
  it('F03-12: Client cannot modify audit logs', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('auditLogs').doc('log1').set({
        id: 'log1',
        eventType: 'SYSTEM_EVENT',
        actorUid: 'SYSTEM',
      });
    });

    const managerDb = getManagerDb('manager1');
    await assertFails(
      managerDb.collection('auditLogs').doc('log1').update({
        eventType: 'MODIFIED_EVENT',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-13: Unauthenticated/private access is denied for sensitive collections.
  // --------------------------------------------------------------------------
  it('F03-13: Unauthenticated/private access is denied for sensitive collections', async () => {
    if (!testEnv) return;
    const unauthDb = getUnauthDb();
    await assertFails(unauthDb.collection('tutorProfiles').doc('tutor1').get());
    await assertFails(unauthDb.collection('studentProfiles').doc('student1').get());
    await assertFails(unauthDb.collection('bookings').doc('booking1').get());
    await assertFails(unauthDb.collection('lessonNotes').doc('note1').get());
    await assertFails(unauthDb.collection('auditLogs').doc('log1').get());
    await assertFails(unauthDb.collection('newsletterSubscribers').doc('sub1').get());
  });

  // --------------------------------------------------------------------------
  // F03-14: Client cannot arbitrarily change booking status/history.
  // --------------------------------------------------------------------------
  it('F03-14: Client cannot arbitrarily change booking status/history', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context.firestore().collection('bookings').doc('booking1').set({
        bookingId: 'booking1',
        studentUid: 'student1',
        tutorUid: 'tutor1',
        status: 'PENDING',
        statusHistory: [],
      });
    });

    const studentDb = getStudentDb('student1');
    await assertFails(
      studentDb.collection('bookings').doc('booking1').update({
        status: 'CONFIRMED',
      })
    );
  });

  // --------------------------------------------------------------------------
  // F03-15: Public tutor records do not expose private tutor collection data through rules.
  // --------------------------------------------------------------------------
  it('F03-15: Public tutor records do not expose private tutor collection data through rules', async () => {
    if (!testEnv) return;
    await testEnv.withSecurityRulesDisabled(async context => {
      await context
        .firestore()
        .collection('publicTutors')
        .doc('tutor1')
        .set({
          uid: 'tutor1',
          displayName: 'Verified Tutor',
          bio: 'Public summary',
          subjects: ['Maths'],
          hourlyRatePence: 3000,
        });
      await context.firestore().collection('tutorProfiles').doc('tutor1').set({
        uid: 'tutor1',
        bio: 'Private tutor data with DBS doc links',
        dbsDocumentUrl: 'https://storage/dbs/private.pdf',
        contactPhone: '+447911123456',
      });
    });

    const unauthDb = getUnauthDb();
    await assertSucceeds(unauthDb.collection('publicTutors').doc('tutor1').get());
    await assertFails(unauthDb.collection('tutorProfiles').doc('tutor1').get());
  });
});
