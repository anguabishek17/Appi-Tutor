# UK Tutoring Platform

Enterprise tutoring web application built on Firebase serverless infrastructure and vanilla modern web architecture.

> **Current Scope: Phase F05 — Lesson Booking, Availability & Double-Booking Prevention**  
> This repository implements the complete lesson booking system, availability slot creation/management, Firestore transactional double-booking prevention, central status transition validator, booking history tracking, role-based access control, frontend UI, and comprehensive test suite (F05-01 through F05-36). **F06 payments and notifications belong to subsequent phases.**

---

## 1. Project Overview

The UK Tutoring Platform provides a bespoke matching and lesson management solution for UK students, parents, and verified tutors. The platform is architected around serverless cloud infrastructure in region `europe-west2` for maximum reliability, auditability, and data security under UK GDPR and safeguarding requirements.

---

## 2. Architecture & File Structure

```text
/
├── firebase.json              # Firebase services, Hosting, Cloud Storage & Emulator Suite config
├── .firebaserc                # Active Firebase project alias
├── firestore.rules            # Firestore security rules (Strict RBAC & Least Privilege)
├── firestore.indexes.json     # Firestore composite index definitions
├── storage.rules              # Cloud Storage security rules (Strict access controls & size/type limits)
├── .gitignore                 # Monorepo git exclusion rules
├── .prettierrc                # Monorepo code formatter config
├── package.json               # Root monorepo script runner
├── README.md                  # Project documentation
│
├── functions/                 # Backend Cloud Functions (2nd Gen)
│   ├── package.json
│   ├── tsconfig.json          # Strict TypeScript configuration
│   ├── eslint.config.js       # ESLint configuration
│   ├── .env.example           # Environment template
│   ├── scripts/
│   │   └── provision-manager.ts # Privileged manager provisioning script
│   └── src/
│       ├── index.ts           # 2nd Gen entry point (region: europe-west2)
│       ├── config/            # Region, environment, and services config
│       ├── endpoints/
│       │   ├── auth.ts        # registerUser & protected role example endpoints
│       │   ├── tutorOnboarding.ts # F04 profile update, DBS upload, manager review endpoints
│       │   └── booking.ts     # F05 availability slots & transactional booking endpoints
│       ├── helpers/           # Request IDs, response formatters, logger, Zod validation, roles, bookingValidator
│       ├── middleware/        # Correlation wrapper & auth authorization middleware
│       ├── test/
│       │   ├── auth.test.ts   # Master test runner index
│       │   ├── unit/          # Auth unit & mock tests
│       │   ├── integration/   # HTTP integration, firestore rules, F04 onboarding & F05 booking tests (F05-01 to F05-36)
│       │   └── frontend/      # Frontend auth service tests
│       └── types/             # Shared TypeScript models (UserRole, UserStatus, BookingStatus, AvailabilitySlot)
│           ├── index.ts
│           └── firestore.ts   # F03/F04/F05 Firestore document schemas & type definitions
│
└── web/                       # Frontend Web Application
    ├── package.json
    ├── vite.config.js         # Vite bundler configuration
    ├── index.html             # HTML5 SPA entry point
    └── src/                   # Auth, Onboarding & Booking components
        ├── main.js
        └── components/
            ├── authUI.js      # Auth component
            ├── tutorOnboardingUI.js # Tutor onboarding & Manager review UI
            └── bookingUI.js   # F05 Lesson booking & availability management UI
```

---

## 3. Lesson Booking Lifecycle & State Transitions

Bookings follow an explicit, server-verified lifecycle:

```text
               ┌──────────────────────────────┐
               │           PENDING            │
               └──────────────┬───────────────┘
                              │
         ┌────────────────────┼────────────────────┬────────────────────┐
         │                    │                    │                    │
         ▼                    ▼                    ▼                    ▼
   [CONFIRMED]            [REJECTED]     [RESCHEDULE_PROPOSED]     [CANCELLED]
         │                                         │
    ┌────┴────┐                             ┌──────┴──────┐
    │         │                             │             │
    ▼         ▼                             ▼             ▼
[COMPLETED] [CANCELLED]                 [PENDING]    [CANCELLED]/[REJECTED]
```

### Transition Rules

- `PENDING` → `CONFIRMED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `SYSTEM_CANCELLED`
- `CONFIRMED` → `COMPLETED`, `CANCELLED`, `SYSTEM_CANCELLED`
- `RESCHEDULE_PROPOSED` → `PENDING`, `CANCELLED`, `REJECTED`, `SYSTEM_CANCELLED`

All invalid status transitions are rejected with error `BOOKING_INVALID_STATE_TRANSITION`.

---

## 4. Double-Booking Prevention & Concurrency Authority

- **Firestore Concurrency Authority**: Double-booking prevention uses Firestore transactions (`db.runTransaction`).
- **Atomic Operations**:
  1. Read availability slot (`availabilitySlots/{slotId}`).
  2. Verify slot existence & `status === 'AVAILABLE'`.
  3. Verify tutor `approvalStatus === 'APPROVED' / 'MANAGER_APPROVED'`, `isBookable === true`, `isPublic === true`.
  4. Write `bookings/{bookingId}` document with status `'PENDING'`.
  5. Atomically update slot status to `'BOOKED'` and attach `bookingId`.
- **Race Condition Prevention**: If two concurrent booking requests target the same slot, exactly one transaction succeeds and the second transaction fails safely with `SLOT_ALREADY_BOOKED` (409).

---

## 5. Automated Test Suite (F05-01 to F05-36)

| Test ID    | Objective                                                   | Expected Result | Status |
| :--------- | :---------------------------------------------------------- | :-------------- | :----- |
| **F05-01** | Tutor can create availability                               | ALLOWED         | PASS   |
| **F05-02** | Non-tutor cannot create tutor availability                  | DENIED          | PASS   |
| **F05-03** | Unapproved tutor cannot create bookable availability        | DENIED (403)    | PASS   |
| **F05-04** | Approved/bookable tutor can create availability             | ALLOWED         | PASS   |
| **F05-05** | Tutor cannot modify another tutor's availability            | DENIED (403)    | PASS   |
| **F05-06** | Student can retrieve available slots                        | ALLOWED         | PASS   |
| **F05-07** | Private tutor data is not exposed through availability      | VERIFIED        | PASS   |
| **F05-08** | Student can create booking                                  | ALLOWED         | PASS   |
| **F05-09** | Booking starts in PENDING                                   | VERIFIED        | PASS   |
| **F05-10** | Booking contains initial statusHistory entry                | VERIFIED        | PASS   |
| **F05-11** | Student cannot directly modify booking status               | DENIED          | PASS   |
| **F05-12** | Student cannot modify statusHistory                         | DENIED          | PASS   |
| **F05-13** | Tutor can view their own bookings                           | ALLOWED         | PASS   |
| **F05-14** | Tutor cannot view another tutor's private bookings          | DENIED (403)    | PASS   |
| **F05-15** | Valid PENDING → CONFIRMED transition succeeds               | ALLOWED         | PASS   |
| **F05-16** | Valid PENDING → REJECTED transition succeeds                | ALLOWED         | PASS   |
| **F05-17** | Valid PENDING → RESCHEDULE_PROPOSED transition succeeds     | ALLOWED         | PASS   |
| **F05-18** | Valid booking cancellation succeeds where permitted         | ALLOWED         | PASS   |
| **F05-19** | Valid CONFIRMED → COMPLETED transition succeeds             | ALLOWED         | PASS   |
| **F05-20** | Invalid booking transition is rejected                      | REJECTED (400)  | PASS   |
| **F05-21** | Booking history is appended on status change                | VERIFIED        | PASS   |
| **F05-22** | Previous status history cannot be rewritten by client       | VERIFIED        | PASS   |
| **F05-23** | Already reserved slot cannot be booked again                | REJECTED (409)  | PASS   |
| **F05-24** | Concurrent booking attempts cannot double-book a slot       | VERIFIED        | PASS   |
| **F05-25** | Exactly one booking wins the concurrent race                | VERIFIED        | PASS   |
| **F05-26** | Tutor suspension prevents new booking activity              | DENIED (403)    | PASS   |
| **F05-27** | Server checks tutor bookable state                          | VERIFIED        | PASS   |
| **F05-28** | Booking timestamps are stored using server timestamps       | VERIFIED        | PASS   |
| **F05-29** | UTC timestamps are correctly represented as Europe/London   | VERIFIED        | PASS   |
| **F05-30** | Manager can access permitted booking administration         | ALLOWED         | PASS   |
| **F05-31** | Unauthorized user cannot access manager booking functions   | DENIED (403)    | PASS   |
| **F05-32** | Sensitive manager action creates audit log where applicable | VERIFIED        | PASS   |
| **F05-33** | F01 regression passes                                       | PASS            | PASS   |
| **F05-34** | F02 regression passes                                       | PASS            | PASS   |
| **F05-35** | F03 regression passes                                       | PASS            | PASS   |
| **F05-36** | F04 regression passes                                       | PASS            | PASS   |

---

## 6. Execution Commands & Quality Verification

```bash
# Build both Cloud Functions and Web workspace
npm run build

# Run master test suite (182 tests across 12 suites)
npm test

# Run ESLint across all workspaces
npm run lint

# Verify code formatting with Prettier
npm run format:check

# Auto-format codebase
npm run format
```

---

## 7. Open Business Decisions

1. **Cancellation Windows & Fees**: Does the platform impose a minimum notice window (e.g., 24 hours prior to lesson start) before a student can cancel without penalty? Isolated as configurable business setting.
2. **Reschedule Limits**: How many times can a booking transition between `RESCHEDULE_PROPOSED` and `PENDING` before requiring manager review or automatic cancellation? Isolated as configurable business setting.
3. **Slot Duration Constraints**: Should slots enforce a fixed duration (e.g. 60 mins), or allow arbitrary duration windows set by tutors? Currently supports arbitrary ranges with `startAt < endAt` validation.

---

## 8. Phase Status Summary

| Phase    | Description                                              | Status       |
| :------- | :------------------------------------------------------- | :----------- |
| **F01**  | Project Foundation & Monorepo Tooling                    | **Complete** |
| **F02**  | Authentication, Roles & Authorization Middleware         | **Complete** |
| **F03**  | Firestore Data Model & Security Rules                    | **Complete** |
| **F04**  | Tutor Onboarding, DBS & Manager Approval                 | **Complete** |
| **F05**  | Lesson Booking, Availability & Double-Booking Prevention | **Complete** |
| **F06+** | Payments, Email Notifications & Advanced Features        | _Pending_    |
