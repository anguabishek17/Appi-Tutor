# UK Tutoring Platform

Enterprise tutoring web application built on Firebase serverless infrastructure and vanilla modern web architecture.

> **Current Scope: Phase F06 — Payments & Refunds**  
> This repository implements the complete server-authoritative payment and refund system, integer minor units currency handling, idempotency key processing, webhook signature verification and event logging, state machine transitions, frontend UI components, and integration test suite (F06-01 through F06-33). **F01–F05 functionality is preserved intact.**

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
│       │   ├── booking.ts     # F05 availability slots & transactional booking endpoints
│       │   └── payment.ts     # F06 payment creation, confirmation, webhook & refund endpoints
│       ├── helpers/           # Request IDs, response formatters, logger, Zod validation, roles, bookingValidator, paymentValidator
│       ├── middleware/        # Correlation wrapper & auth authorization middleware
│       ├── services/
│       │   └── paymentProvider.ts # F06 PaymentProvider abstraction & MockPaymentProvider
│       ├── test/
│       │   ├── auth.test.ts   # Master test runner index
│       │   ├── unit/          # Auth unit & mock tests
│       │   ├── integration/   # Integration, rules, onboarding, booking & payment tests (F06-01 to F06-33)
│       │   └── frontend/      # Frontend auth service tests
│       └── types/             # Shared TypeScript models
│           ├── index.ts
│           └── firestore.ts   # Firestore document schemas & type definitions (F01–F06)
│
└── web/                       # Frontend Web Application
    ├── package.json
    ├── vite.config.js         # Vite bundler configuration
    ├── index.html             # HTML5 SPA entry point
    └── src/                   # Auth, Onboarding, Booking & Payment components
        ├── main.js
        └── components/
            ├── authUI.js      # Auth component
            ├── tutorOnboardingUI.js # Tutor onboarding & Manager review UI
            ├── bookingUI.js   # F05 Lesson booking & availability management UI
            └── paymentUI.js   # F06 Payment initiation, confirmation & refund UI
```

---

## 3. Payments & Refunds Architecture (Phase F06)

### Server-Authoritative Financial Logic

- **No Client Trust**: Payable amounts and financial metadata are calculated server-side from tutor profiles (`hourlyRatePence`) and booking details. Browser-supplied amounts or financial statuses are strictly ignored.
- **Integer Minor Units**: All monetary values are processed and stored as integer minor units (`amountPence`, e.g., £25.50 = 2550 pence, currency = `GBP`) to avoid floating-point binary math errors.
- **Idempotency**: Requests containing an `Idempotency-Key` are recorded in `idempotencyKeys/{key}` inside Firestore transactions to prevent duplicate charges or double payments.
- **State Machine Validation**: Payment states (`PENDING`, `SUCCEEDED`, `FAILED`, `CANCELLED`, `REFUNDED`, `PARTIALLY_REFUNDED`) and Refund states (`PENDING`, `SUCCEEDED`, `FAILED`, `CANCELLED`) follow strict transition validation via `isValidPaymentTransition` and `isValidRefundTransition`.

### Provider Abstraction & Webhook Verification

- **`PaymentProvider` Interface**: Isolates provider-specific logic, allowing seamless pluggability for production gateways (e.g. Stripe, Adyen).
- **`MockPaymentProvider`**: Provides safe, deterministic test/emulator payment intents and refunds without requiring live secrets.
- **Webhook Security**: Webhook signatures are verified server-side (`verifyWebhookSignature`). Unique `providerEventId` records in `paymentEvents/{eventId}` guarantee idempotent, single-execution webhook event processing.

---

## 4. Lesson Booking Lifecycle & State Transitions

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

---

## 5. Automated Test Suite (F06-01 to F06-33)

| Test ID    | Objective                                                     | Expected Result | Status |
| :--------- | :------------------------------------------------------------ | :-------------- | :----- |
| **F06-01** | Authorized user can initiate payment for permitted booking    | ALLOWED         | PASS   |
| **F06-02** | Unauthorized user cannot initiate payment for another booking | DENIED (403)    | PASS   |
| **F06-03** | Payment amount is server-authoritative                        | VERIFIED        | PASS   |
| **F06-04** | Client cannot set payment status                              | DENIED          | PASS   |
| **F06-05** | Client cannot set arbitrary provider payment ID               | DENIED          | PASS   |
| **F06-06** | Payment starts in correct initial state (PENDING)             | VERIFIED        | PASS   |
| **F06-07** | Valid payment state transition succeeds                       | ALLOWED         | PASS   |
| **F06-08** | Invalid payment state transition fails                        | REJECTED (400)  | PASS   |
| **F06-09** | Duplicate payment request is handled idempotently             | VERIFIED        | PASS   |
| **F06-10** | Duplicate payment does not create a second payment            | VERIFIED        | PASS   |
| **F06-11** | Payment record references valid booking                       | VERIFIED        | PASS   |
| **F06-12** | Payment cannot reference unauthorized booking                 | DENIED (403)    | PASS   |
| **F06-13** | Payment timestamps use server timestamps                      | VERIFIED        | PASS   |
| **F06-14** | Financial amount uses integer minor units                     | VERIFIED        | PASS   |
| **F06-15** | Unauthorized user cannot read another user's payment          | DENIED (403)    | PASS   |
| **F06-16** | Tutor cannot modify payment status                            | DENIED (403)    | PASS   |
| **F06-17** | Manager can access permitted payment records                  | ALLOWED         | PASS   |
| **F06-18** | Sensitive manager financial action creates audit log          | VERIFIED        | PASS   |
| **F06-19** | Valid refund request is processed where policy permits        | ALLOWED         | PASS   |
| **F06-20** | Refund cannot exceed captured payment amount                  | REJECTED (400)  | PASS   |
| **F06-21** | Duplicate refund cannot be created                            | REJECTED (400)  | PASS   |
| **F06-22** | Client cannot mark refund as successful in Firestore          | DENIED (Rules)  | PASS   |
| **F06-23** | Unauthorized refund request fails                             | DENIED (403)    | PASS   |
| **F06-24** | Invalid refund state transition fails                         | REJECTED (400)  | PASS   |
| **F06-25** | Invalid webhook signature is rejected                         | REJECTED (400)  | PASS   |
| **F06-26** | Duplicate webhook event is ignored safely                     | VERIFIED        | PASS   |
| **F06-27** | Valid provider event updates payment state correctly          | VERIFIED        | PASS   |
| **F06-28** | Secrets are not written to logs                               | VERIFIED        | PASS   |
| **F06-29** | F01 regression passes                                         | PASS            | PASS   |
| **F06-30** | F02 regression passes                                         | PASS            | PASS   |
| **F06-31** | F03 regression passes                                         | PASS            | PASS   |
| **F06-32** | F04 regression passes                                         | PASS            | PASS   |
| **F06-33** | F05 regression passes                                         | PASS            | PASS   |

---

## 6. Execution Commands & Quality Verification

```bash
# Build both Cloud Functions and Web workspace
npm run build

# Run master test suite (248 tests across 14 suites)
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

1. **Lesson Pricing & Commission Policy**: Specific tutor commission split percentages, platform service fees, and tutor payout schedules are isolated in configuration as pending client policy decisions.
2. **Refund Rules & Deadlines**: Full vs. partial refund eligibility windows (e.g. 24h prior cancellation refund rules) are kept configurable.
3. **VAT & Tax Automation**: VAT rules and tax calculations are not hardcoded and require client policy definition prior to live processing.
4. **Live Provider Credentials**: Production gateway credentials (e.g., Stripe API Secret Keys and Webhook Signing Secrets) must be injected into environment variables (`PAYMENT_PROVIDER_SECRET`, `PAYMENT_WEBHOOK_SECRET`) prior to production launch.

---

## 8. Phase Status Summary

| Phase   | Description                                              | Status       |
| :------ | :------------------------------------------------------- | :----------- |
| **F01** | Project Foundation & Monorepo Tooling                    | **Complete** |
| **F02** | Authentication, Roles & Authorization Middleware         | **Complete** |
| **F03** | Firestore Data Model & Security Rules                    | **Complete** |
| **F04** | Tutor Onboarding, DBS & Manager Approval                 | **Complete** |
| **F05** | Lesson Booking, Availability & Double-Booking Prevention | **Complete** |
| **F06** | Payments & Refunds Layer                                 | **Complete** |
