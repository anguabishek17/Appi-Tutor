/**
 * UK Tutoring Platform - Master Test Suite Index (AUTH-01 through AUTH-14)
 * Authentication Phase (F02)
 *
 * Clearly categorizes:
 * 1. [UNIT / MOCK TESTS]
 * 2. [FIREBASE EMULATOR & HTTP INTEGRATION TESTS]
 * 3. [FRONTEND WIRING & CONFIGURATION TESTS]
 */

import './unit/auth-unit.test';
import './integration/auth-emulator.test';
import './integration/firestore-rules.test';
import './integration/tutor-onboarding.test';
import './frontend/auth-frontend.test';
