# UK Tutoring Platform

Enterprise tutoring web application built on Firebase serverless infrastructure and vanilla modern web architecture.

> **Current Scope: Phase F04 — Tutor Onboarding, DBS & Manager Approval**  
> This repository implements the full tutor onboarding lifecycle, DBS document submission & storage security rules, server-side manager approval/rejection/suspension workflows, audit logging, public directory publication engine, and comprehensive security tests. **F05 lesson booking engine, payments, and other business workflows belong to subsequent phases.**

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
│       │   └── tutorOnboarding.ts # F04 profile update, DBS upload, manager review endpoints
│       ├── helpers/           # Request IDs, response formatters, logger, Zod validation, roles
│       ├── middleware/        # Correlation wrapper & auth authorization middleware
│       ├── test/
│       │   ├── auth.test.ts   # Master test runner index
│       │   ├── unit/          # Auth unit & mock tests
│       │   ├── integration/   # HTTP integration, firestore rules & F04 onboarding tests (F04-01 to F04-23)
│       │   └── frontend/      # Frontend auth service tests
│       └── types/             # Shared TypeScript models (UserRole, UserStatus, Onboarding Enum definitions)
│           ├── index.ts
│           └── firestore.ts   # F03/F04 Firestore document schemas & type definitions
│
└── web/                       # Frontend Web Application
    ├── package.json
    ├── vite.config.js         # Vite bundler configuration
    ├── index.html             # HTML5 SPA entry point
    └── src/                   # Auth, Onboarding & Manager Review components
        ├── main.js
        └── components/
            ├── authUI.js      # Auth component
            └── tutorOnboardingUI.js # Tutor onboarding & Manager review UI
```

---

## 3. Tutor Onboarding Lifecycle & State Transitions

Tutors progress through an explicit, server-verified lifecycle:

```text
  [REGISTER]
      │
      ▼
[EMAIL_VERIFIED] ──(Tutor completes bio, subjects, rate)──► [PROFILE_COMPLETE]
                                                                  │
                                                        (Submits DBS document)
                                                                  │
                                                                  ▼
                                                          [DBS_SUBMITTED]
                                                                  │
                                                        (Transitions on submit)
                                                                  │
                                                                  ▼
                                                          [PENDING_REVIEW]
                                                                 │ │
                                                ┌────────────────┘ └──────────────┐
                                                │ (Manager Approves)               │ (Manager Rejects)
                                                ▼                                 ▼
                                       [MANAGER_APPROVED]                    [REJECTED]
                                       (Bookable & Public)              (Not Bookable/Public)
                                                │ ▲
                            (Manager Suspends)  │ │ (Manager Re-approves)
                                                ▼ │
                                           [SUSPENDED]
                                     (Not Bookable/Public)
```

### Supported State Transitions

- `PENDING_REVIEW` → `MANAGER_APPROVED` (Manager approval)
- `PENDING_REVIEW` → `REJECTED` (Manager rejection)
- `MANAGER_APPROVED` → `SUSPENDED` (Manager suspension)
- `SUSPENDED` → `MANAGER_APPROVED` (Manager re-approval)

Invalid state transitions (e.g. `REJECTED` → `MANAGER_APPROVED` directly, or client-driven state modification) are rejected by server-side state transition guards.

---

## 4. DBS Submission & Storage Architecture

### File Storage Location
DBS document files are stored privately in Cloud Storage under:
`dbs/{uid}/{filename}`

### Access Control (`storage.rules`)
- **Default Deny**: All unmapped storage paths default to deny.
- **Tutor Access**: Tutors may only read and write files within `dbs/{uid}/*` where `request.auth.uid == uid`.
- **Manager Access**: Managers (`request.auth.token.role == 'MANAGER'`) can read files in `dbs/{uid}/*` for verification.
- **Client Restrictions**: Cross-tutor read/write is strictly denied. Unauthenticated access is denied.

### Technical File Validation
- Maximum file size: **10 MB** (`10 * 1024 * 1024` bytes).
- Allowed MIME types: `application/pdf`, `image/jpeg`, `image/png`.
- File content and path ownership are verified prior to updating profile state to `DBS_SUBMITTED` / `PENDING_REVIEW`.

> **Safeguarding & Legal Notice**: The DBS submission workflow is a technical data collection and review pipeline. It does **not** constitute automated legal verification or background validation against the UK Disclosure and Barring Service database. Legal verification remains the responsibility of authorized platform managers during review.

---

## 5. Manager Approval & Security Boundaries

### Server-Side Authority
- All manager operations (`managerApproveTutor`, `managerRejectTutor`, `managerSuspendTutor`, `managerReapproveTutor`) require Firebase Auth custom claim `role == 'MANAGER'`.
- All status transitions, public tutor record creation/deletion, and audit logging execute within atomic **Firestore Transactions** using the Firebase Admin SDK.
- The client cannot directly write to `publicTutors/{uid}` or update authoritative fields (`approvalStatus`, `onboardingStatus`, `dbsStatus`, `isBookable`, `isPublic`) in `tutorProfiles/{uid}`.

### Audit Trail (`auditLogs/{id}`)
Every manager lifecycle action automatically generates an immutable audit document in `auditLogs/{id}` containing:
- `actorUid`: Manager UID
- `action`: `TUTOR_APPROVAL`, `TUTOR_REJECTION`, `TUTOR_SUSPENSION`, or `TUTOR_REAPPROVAL`
- `targetTutorUid`: Tutor UID
- `timestamp`: ISO timestamp
- `requestId`: Request correlation ID
- `reason`: Optional manager notes (for rejections/suspensions)

Sensitive credentials (passwords, tokens, raw DBS file contents) are **never** logged.

---

## 6. Public Directory Model (`publicTutors/{uid}`)

- **Read Model**: `publicTutors/{uid}` is readable by the public (`allow read: if true`).
- **Data Boundaries**: Contains only public display fields (`uid`, `displayName`, `bio`, `subjects`, `hourlyRatePounds`, `profileImageUrl`, `updatedAt`).
- **Exclusions**: Private contact details, phone numbers, DBS document links, manager notes, and internal audit metadata are strictly excluded.
- **Publication Lifecycle**:
  - `publicTutors/{uid}` document is created/updated **only** when a tutor achieves `MANAGER_APPROVED` status.
  - When a tutor is `REJECTED` or `SUSPENDED`, the `publicTutors/{uid}` document is **immediately deleted** by server transaction.

---

## 7. Automated Test Suite (F04-01 to F04-23)

| Test ID | Objective | Expected Result | Status |
| :--- | :--- | :--- | :--- |
| **F04-01** | Tutor can create/update their own onboarding profile | ALLOWED | PASS |
| **F04-02** | Tutor cannot update another tutor's profile | DENIED | PASS |
| **F04-03** | Tutor cannot self-approve | DENIED | PASS |
| **F04-04** | Tutor cannot self-set `isBookable = true` | DENIED | PASS |
| **F04-05** | Tutor cannot self-set `isPublic = true` | DENIED | PASS |
| **F04-06** | Tutor cannot self-set DBS verified/approved | DENIED | PASS |
| **F04-07** | Unauthenticated user cannot access tutor onboarding endpoints | DENIED (401) | PASS |
| **F04-08** | Non-tutor (`STUDENT_PARENT`) cannot use tutor onboarding endpoints | DENIED (403) | PASS |
| **F04-09** | Non-manager cannot approve a tutor | DENIED (403) | PASS |
| **F04-10** | Manager can view pending tutor review data | ALLOWED | PASS |
| **F04-11/12**| Manager can approve a valid tutor & publish to `publicTutors` | ALLOWED | PASS |
| **F04-13** | Rejected tutor is not public/bookable | VERIFIED | PASS |
| **F04-14/15**| Manager can suspend an approved tutor & unpublish | ALLOWED | PASS |
| **F04-16** | Manager can re-approve a suspended tutor | ALLOWED | PASS |
| **F04-17** | Tutor cannot directly write `publicTutors` | DENIED | PASS |
| **F04-18** | Tutor cannot access another tutor's private DBS data | DENIED | PASS |
| **F04-19** | Unauthorized user cannot access private DBS data | DENIED | PASS |
| **F04-20** | Manager actions create protected audit records | VERIFIED | PASS |
| **F04-21** | Invalid lifecycle transitions are rejected | REJECTED | PASS |
| **F04-22** | Concurrent manager state changes are handled safely | VERIFIED | PASS |
| **F04-23** | Regression check: F01/F02/F03 tests remain fully functional | PASS (110/110) | PASS |

---

## 8. Execution Commands & Quality Verification

```bash
# Build both Cloud Functions and Web workspace
npm run build

# Run master test suite (110 tests across 10 suites)
npm test

# Run ESLint across all workspaces
npm run lint

# Verify code formatting with Prettier
npm run format:check

# Auto-format codebase
npm run format
```

---

## 9. Deferred Functionality (F05+)

The following business workflows are intentionally deferred to future phases:

- Booking engine, scheduling calendar, double-booking prevention & availability locks (F05)
- Stripe payment processing & payout engine (F06)
- Transactional email & SMS notification system (F07)
- CMS blog management & public contact form processing (F08)

---

## 10. Open Questions

1. **DBS Expiry Monitoring**: Should future phases implement an automated cron trigger to flag DBS certificates that exceed 3 years from issue date, or rely on manual manager re-review?
2. **Rejection Resubmission**: Can a `REJECTED` tutor submit an updated DBS document to re-enter `PENDING_REVIEW`, or is manager intervention required to reset state to `DBS_SUBMITTED`?

---

## 11. Phase Status Summary

| Phase | Description | Status |
| :--- | :--- | :--- |
| **F01** | Project Foundation & Monorepo Tooling | **Complete** |
| **F02** | Authentication, Roles & Authorization Middleware | **Complete** |
| **F03** | Firestore Data Model & Security Rules | **Complete** |
| **F04** | Tutor Onboarding, DBS & Manager Approval | **Complete** |
| **F05+** | Lesson Booking Engine, Payments, Email & CMS | _Pending_ |

