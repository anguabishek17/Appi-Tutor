# AppiTutors MVP — Foundation Architecture & Setup Guide

This repository contains the MVP implementation of the **AppiTutors** UK Tutoring Platform, adhering to the client SRS and UK tutoring business requirements.

---

## 1. Directory Structure

```
appi-tutors-mvp/
├── bin/                       # CLI / Migration utilities
├── config/
│   ├── app.php                # Central application configuration
│   ├── schema.sql             # Full MySQL 8.0 schema & seed data
│   └── firebase_credentials.json # (Ignored) Firebase Admin SDK credentials
├── public/                    # Web document root
│   ├── assets/                # Static JS, CSS, images
│   ├── api/                   # RESTful API endpoints
│   ├── manager/               # Manager portal pages
│   ├── tutor/                 # Tutor portal pages
│   ├── student/               # Student / Parent portal pages
│   └── index.php              # Public landing page
├── src/                       # PSR-4 Autoloaded source code (`App\`)
│   ├── Auth/
│   │   ├── AuthService.php       # PHP session + user authorization service
│   │   └── FirebaseVerifier.php  # Firebase ID token validation
│   ├── Database/
│   │   └── Connection.php        # PDO Singleton connection manager
│   ├── Middleware/
│   │   └── AuthMiddleware.php    # Route guarding & CSRF enforcement
│   ├── Services/
│   │   ├── CsrfService.php       # Anti-CSRF token handling
│   │   └── ResponseService.php   # Standardized JSON response formatting
│   ├── Controllers/           # Request controllers
│   └── bootstrap.php          # Core bootstrap & session initialization
├── storage/                   # File storage (outside web root)
│   ├── dbs_credentials/       # Secure DBS certificate uploads
│   └── logs/                  # System error and audit logs
├── .env.example               # Template environment configuration
├── composer.json              # Dependency definition and PSR-4 mapping
└── README.md
```

---

## 2. Architecture & Design Principles

1. **Modular PHP & PSR-4 Autoloading:** All business logic is encapsulated in `src/` under the `App\` namespace.
2. **Centralized Database Connection:** `App\Database\Connection` delivers a managed PDO singleton instance using prepared statements with `utf8mb4` and strict error modes.
3. **Robust Authentication Architecture:**
   - Frontend authenticates with Firebase Authentication (Email/Password or Google).
   - Firebase ID token is sent to backend `/public/api/auth/login.php`.
   - Backend verifies ID token, provisions or retrieves local `users` and `roles` records, and creates a secure PHP session.
   - Browser role assertions are never trusted; role checks are performed server-side via `App\Middleware\AuthMiddleware`.
4. **Security Hardened:**
   - CSRF protection for state-changing endpoints via `App\Services\CsrfService`.
   - Sensitive DBS certificate uploads stored outside the `public` web root in `storage/dbs_credentials`.
   - Sensitive audit actions logged in `audit_logs`.

---

## 3. Database Schema (13 Core Tables)

1. `roles` (`MANAGER`, `TUTOR`, `STUDENT_PARENT`)
2. `users` (Central user identity, linked to Firebase UID)
3. `tutor_profiles` (Hourly rate, qualifications, DBS path, approval status)
4. `students_children` (Parent/student child profiles, year groups, goals)
5. `curricula` (Primary KS1-KS2, 11+, KS3, GCSE, A-Level, IB)
6. `subjects` (Curriculum-linked subjects: Maths, English, Sciences, etc.)
7. `tutor_subjects` (Subject-specific approval & rates per tutor)
8. `availability_slots` (Weekly recurring availability)
9. `bookings` (Status workflow: `PENDING`, `ACCEPTED`, `REJECTED`, `RESCHEDULE_PROPOSED`, `CANCELLED`, `COMPLETED`, `SYSTEM_CANCELLED`)
10. `lesson_notes` (Private tutor notes, progress ratings, parent feedback)
11. `blog_posts` (AppiTutors study blog CMS)
12. `newsletter_subscribers` (Email subscription list)
13. `audit_logs` (Manager and compliance audit logs)

---

## 4. Setup & Running Locally

### Step 1: Environment Configuration
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Update your MySQL database credentials in `.env`.

### Step 2: Initialize the Database
Import the schema into your MySQL server:
```bash
mysql -u root -p appitutors_db < config/schema.sql
```

### Step 3: Install Composer Dependencies (Optional)
```bash
composer install
```
*(Note: A built-in fallback PSR-4 autoloader is included in `src/bootstrap.php` for seamless operation even before `composer install` is run).*

### Step 4: Run the Local PHP Web Server
Start the PHP built-in server targeting the `public` document root:
```bash
php -S localhost:8000 -t public
```
Open [http://localhost:8000](http://localhost:8000) in your browser.
