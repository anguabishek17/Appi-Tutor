# UK Tutoring Platform

Enterprise tutoring web application built on Firebase serverless infrastructure and vanilla modern web architecture.

> **Current Scope: Phase F01 — Project Foundation**  
> This repository currently contains only the project foundation, tooling, shared backend helpers, security rules scaffolding, and configuration layer. **No business features, tutor directory, booking flows, or user-facing authentication screens are active in this phase.**

---

## 1. Project Overview

The UK Tutoring Platform provides a bespoke matching and lesson management solution for UK students, parents, and verified tutors. The platform is architected around serverless cloud infrastructure for maximum reliability, auditability, and data security under UK GDPR and safeguarding requirements.

---

## 2. Architecture

```text
/
├── firebase.json              # Firebase services, Hosting, and Emulator Suite config
├── .firebaserc                # Active Firebase project alias
├── firestore.rules            # Firestore security rules (Default Deny)
├── firestore.indexes.json     # Firestore composite index definitions
├── storage.rules              # Cloud Storage security rules (Default Deny)
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
│   └── src/
│       ├── index.ts           # 2nd Gen entry point (region: europe-west2)
│       ├── config/            # Region, environment, and services config
│       ├── helpers/           # Request IDs, response formatters, logger, Zod validation
│       ├── middleware/        # Request wrapper & error interceptors
│       └── types/             # Shared TypeScript models and response interfaces
│
└── web/                       # Frontend Web Application
    ├── package.json
    ├── vite.config.js         # Vite bundler configuration
    ├── tailwind.config.js     # Tailwind CSS design system
    ├── postcss.config.js      # PostCSS configuration
    ├── index.html             # HTML5 SPA entry point
    ├── .env.example           # Frontend environment template
    ├── public/                # Static assets and favicon
    └── src/
        ├── main.js            # App bootstrap & emulator hookup
        ├── firebase/          # Client Firebase SDK configuration
        ├── components/        # UI components (future phases)
        ├── pages/             # Route views (future phases)
        ├── services/          # Client API services (future phases)
        └── styles/            # Tailwind CSS root stylesheet
```

### Technology Stack

- **Backend**: Firebase Cloud Functions 2nd Generation (Node.js 20, TypeScript strict mode)
- **Database**: Cloud Firestore
- **File Storage**: Cloud Storage for Firebase
- **Hosting**: Firebase Hosting (with HTTP security headers)
- **Local Tooling**: Firebase Emulator Suite (Auth, Firestore, Functions, Storage, Hosting, UI)
- **Frontend**: Vite + Tailwind CSS + Vanilla JavaScript (ES Modules)
- **Backend Region**: `europe-west2` (London)

---

## 3. Prerequisites

- **Node.js**: `20.x` or later (LTS recommended)
- **npm**: `10.x` or later
- **Java Runtime Environment (JRE)**: `11+` (Required by Firebase Emulator Suite for Firestore/Auth emulators)

---

## 4. Firebase CLI Installation

Install the Firebase CLI globally if not already installed:

```bash
npm install -g firebase-tools
```

Authenticate with your Google/Firebase account:

```bash
firebase login
```

---

## 5. Node.js Version

Verify your current Node and npm versions:

```bash
node -v
npm -v
```

Ensure Node is version `20.x` or higher.

---

## 6. Installation Steps

Clone the repository and install all workspace dependencies from the root directory:

```bash
# Install root, functions, and web dependencies in one step
npm install
```

Or install independently:

```bash
# Functions
cd functions && npm install && cd ..

# Web
cd web && npm install && cd ..
```

---

## 7. Firebase Project Configuration

1. Copy the example environment files:
   ```bash
   cp functions/.env.example functions/.env.local
   cp web/.env.example web/.env.local
   ```
2. Check your `.firebaserc` file and verify your project ID:
   ```json
   {
     "projects": {
       "default": "uk-tutoring-dev"
     }
   }
   ```

---

## 8. Local Development & Emulator Suite

The project is fully configured to run offline using the **Firebase Local Emulator Suite**.

### Starting Emulators

Start the entire local emulator suite (Auth, Firestore, Functions, Storage, Hosting, Emulator UI):

```bash
npm run emulators
```

Emulator endpoints:

- **Emulator UI**: [http://127.0.0.1:4000](http://127.0.0.1:4000)
- **Authentication**: `127.0.0.1:9099`
- **Cloud Functions**: `127.0.0.1:5001`
- **Cloud Firestore**: `127.0.0.1:8080`
- **Cloud Storage**: `127.0.0.1:9199`
- **Firebase Hosting**: `127.0.0.1:5000`

---

## 9. Frontend Development Server

To launch the Vite development server with hot-module reloading:

```bash
npm run dev:web
```

The frontend will run at [http://localhost:3000](http://localhost:3000) and automatically link to the Firebase Emulators when running locally.

---

## 10. Functions Build Commands

Build TypeScript Cloud Functions:

```bash
# One-time build
npm run build:functions

# Watch mode for automatic compilation during development
npm run --workspace=functions build:watch
```

---

## 11. Linting & Formatting Commands

Run code quality checks across the entire monorepo:

```bash
# Lint both functions and web
npm run lint

# Check formatting
npm run format:check

# Format all files
npm run format
```

---

## 12. Environment and Secret Configuration

- **Development**: Managed via local `.env.local` files (git-ignored) or emulator defaults.
- **Production Secrets**: Use Cloud Secret Manager / Firebase Functions parameterized configuration (`defineSecret`, `defineString`).
- **Forbidden**: Never commit `.env`, service-account `.json` keys, private certificates, or production API keys to git.

---

## 13. Security Rules & Safeguarding Notes

- **Default Deny**: `firestore.rules` and `storage.rules` block all direct client read/write by default.
- **Authorization Architecture**:
  - Roles must be derived from verified custom claims set securely by administrative backend functions.
  - Privileged mutations and DBS-sensitive operations are executed exclusively through Cloud Functions 2nd Gen.
  - Sensitive files (e.g. tutor DBS certificates, identification) reside in private storage paths with no public read access.

---

## 14. Current Scope (F01 Status)

| Feature Area                | Status in F01    | Notes                                                            |
| :-------------------------- | :--------------- | :--------------------------------------------------------------- |
| Monorepo Architecture       | **Complete**     | Standardized folder structure and workspace scripts              |
| Firebase Config & Emulators | **Complete**     | Configured for `europe-west2` region                             |
| Cloud Functions 2nd Gen     | **Complete**     | TypeScript strict mode, health check endpoint                    |
| Backend Helpers             | **Complete**     | Request IDs, sanitized logger, standard response, Zod validation |
| Security Rules              | **Complete**     | Default-deny rules for Firestore and Cloud Storage               |
| Web Frontend                | **Complete**     | Vite + Tailwind + Vanilla JS foundation page                     |
| Business Logic / Auth Flows | _Pending (F02+)_ | Intentionally not implemented in F01                             |
