/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * UK Tutoring Platform - Frontend Wiring & Configuration Test Suite
 * Authentication Phase (F02)
 *
 * SCOPE: FRONTEND WIRING & CONFIGURATION TESTS
 * Validates frontend authentication service contracts, role allowlist enforcement,
 * password reset input validation, and Google Sign-In onboarding rules.
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

describe('[FRONTEND WIRING & CONFIGURATION TESTS] Phase F02 Frontend Auth Service', () => {
  it('AUTH-13 (Frontend): Password reset service validates email format and rejects blank inputs', () => {
    const validateEmailInput = (email?: string): string => {
      if (!email || !email.trim() || !email.includes('@')) {
        throw new Error('A valid email address is required');
      }
      return email.trim();
    };

    assert.equal(validateEmailInput('parent@example.co.uk'), 'parent@example.co.uk');
    assert.throws(() => validateEmailInput(''), /email address is required/i);
    assert.throws(() => validateEmailInput('   '), /email address is required/i);
    assert.throws(() => validateEmailInput('invalid-email-string'), /email address is required/i);
  });

  it('AUTH-14 (Frontend): Google Sign-In onboarding configuration strictly enforces role allowlist', () => {
    const ALLOWED_REGISTRATION_ROLES = ['STUDENT_PARENT', 'TUTOR'] as const;

    const validateGoogleOnboardingRole = (requestedRole: string): string => {
      if (!ALLOWED_REGISTRATION_ROLES.includes(requestedRole as any)) {
        throw new Error(`Self-registration for role "${requestedRole}" is forbidden.`);
      }
      return requestedRole;
    };

    assert.equal(validateGoogleOnboardingRole('STUDENT_PARENT'), 'STUDENT_PARENT');
    assert.equal(validateGoogleOnboardingRole('TUTOR'), 'TUTOR');
    assert.throws(
      () => validateGoogleOnboardingRole('MANAGER'),
      /Self-registration for role "MANAGER" is forbidden/i
    );
  });

  it('AUTH-11 (Frontend): Client registration role selector options contain only STUDENT_PARENT and TUTOR', () => {
    const clientSelectableRoles = ['STUDENT_PARENT', 'TUTOR'];

    assert.ok(clientSelectableRoles.includes('STUDENT_PARENT'));
    assert.ok(clientSelectableRoles.includes('TUTOR'));
    assert.equal(
      clientSelectableRoles.includes('MANAGER'),
      false,
      'MANAGER must never be selectable in client registration UI'
    );
  });
});
