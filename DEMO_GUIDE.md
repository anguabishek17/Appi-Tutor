# AppiTutors — Client Demo & Scenario Walkthrough Guide

## 1. Overview & Objectives
This guide outlines the procedure for seeding, resetting, and presenting the **AppiTutors MVP** during a client demonstration. 

The demonstration highlights:
- **UK-Curriculum Tutor Discovery:** Verified tutors with DBS checks, subject expertise, and availability calendar.
- **Parent Workflow:** Student child management, booking requests, reschedule management, and viewing lesson progress notes.
- **Tutor Portal:** Managing availability slots, reviewing booking requests, accepting/rescheduling lessons, taking attendance, and recording lesson progress.
- **Manager Operations:** Platform overview metrics and the tutor verification/approval queue.

---

## 2. Demo Seed & Reset Commands

### Seeding Demo Data
To seed (or update) the realistic client demo dataset, run:
```bash
php bin/seed_demo.php
```
*Note: The seed script is completely **idempotent** and safe to run multiple times without creating duplicate records.*

### Resetting Demo Data
To remove only the demo dataset without affecting any other database records:
```bash
php bin/reset_demo.php
```

---

## 3. Demo Accounts

| Role | Name | Email | Default Status | Key Capabilities |
| :--- | :--- | :--- | :--- | :--- |
| **Manager** | Sarah Mitchell | `demo.manager@appitutors.co.uk` | `ACTIVE` | Operations dashboard, pending tutor reviews, platform metrics. |
| **Tutor** | James Carter | `demo.tutor@appitutors.co.uk` | `APPROVED` | DBS-checked UK Maths & Physics tutor with assigned subjects and availability slots. |
| **Parent** | Emily Wilson | `demo.parent@appitutors.co.uk` | `ACTIVE` | Parent with two registered children (**Oliver Wilson** - Year 10 & **Sophie Wilson** - Year 8). |

---

## 4. Pre-Configured Demo Scenarios

The seed script creates 4 pre-configured bookings demonstrating the full lifecycle:

| Booking Reference | Subject | Student | Status | Description |
| :--- | :--- | :--- | :--- | :--- |
| **`DEMO-BK-PEND-01`** | GCSE Maths | Oliver Wilson (Yr 10) | `PENDING` | Newly requested booking awaiting James Carter's acceptance. |
| **`DEMO-BK-ACPT-02`** | GCSE Physics | Sophie Wilson (Yr 8) | `ACCEPTED` | Confirmed upcoming lesson with meeting link. |
| **`DEMO-BK-RSCH-03`** | A-Level Maths | Oliver Wilson (Yr 10) | `RESCHEDULE_PROPOSED` | Reschedule proposed by tutor to a future date. |
| **`DEMO-BK-CMPL-04`** | GCSE Maths | Sophie Wilson (Yr 8) | `COMPLETED` | Past lesson with `ATTENDED` status, 5-star rating, and full lesson progress notes. |

---

## 5. Step-by-Step Client Demo Walkthrough (20 Steps)

Follow this 20-step walkthrough for an end-to-end client presentation:

### Public Discovery
- **Step 1:** Open the AppiTutors public homepage (`/index.php`).
- **Step 2:** Click **"Find a Tutor"** to open the public marketplace search (`/tutors.php`).
- **Step 3:** Filter by **"GCSE Mathematics"** or search for **"James Carter"**.
- **Step 4:** Click **"View Profile"** on James Carter's card (`/tutor.php?id=...`).
- **Step 5:** Showcase James's headline, First-Class Bristol degrees, verified DBS badge, £45/hr rate, teaching bio, and upcoming availability slots.

### Parent Experience (Emily Wilson)
- **Step 6:** Log in / Switch session to **Emily Wilson** (`demo.parent@appitutors.co.uk`).
- **Step 7:** Navigate to **"My Children"** (`/parent/children.php`) to show Oliver (Year 10) and Sophie (Year 8).
- **Step 8:** Navigate to **"My Bookings"** (`/parent/bookings.php`) to show active and pending bookings.
- **Step 9:** Click **"Find Tutors"** and book a new open slot with James Carter for Oliver.

### Tutor Experience (James Carter)
- **Step 10:** Log in / Switch session to **James Carter** (`demo.tutor@appitutors.co.uk`).
- **Step 11:** On the **Tutor Dashboard** (`/tutor/dashboard.php`), point out the pending requests alert banner and metrics.
- **Step 12:** Navigate to **"Booking Requests"** (`/tutor/bookings.php`) and click **"Accept Booking"** on the pending request (`DEMO-BK-PEND-01`).
- **Step 13:** Navigate to **"Availability"** (`/tutor/availability.php`) to demonstrate creating and blocking slots.

### Rescheduling & Completed Lesson Notes
- **Step 14:** Switch back to **Emily Wilson** (`demo.parent@appitutors.co.uk`).
- **Step 15:** On **"My Bookings"**, demonstrate the **"Reschedule Proposed"** banner on `DEMO-BK-RSCH-03` with Accept / Decline options.
- **Step 16:** Scroll to the **"Completed Lessons"** section and locate `DEMO-BK-CMPL-04`.
- **Step 17:** Click **"View Lesson Details"** to show the parent-visible lesson summary, topics covered (Quadratics & Factorisation), homework assigned, and next lesson focus.

### Manager Operations (Sarah Mitchell)
- **Step 18:** Log in / Switch session to **Sarah Mitchell** (`demo.manager@appitutors.co.uk`).
- **Step 19:** View the **Manager Dashboard** (`/manager/dashboard.php`) showing Total Parents, Approved Tutors, Upcoming Bookings, and Recent Activity.
- **Step 20:** Open **"Tutor Approvals"** (`/manager/tutors.php`) to display the tutor verification queue, review history, and DBS approval flow.

---

## 6. Authentication & Firebase Configuration Note

> **Important Note regarding Browser Authentication:**  
> The AppiTutors platform enforces strict Firebase token verification in `App\Auth\FirebaseVerifier`.
> - If real Firebase project credentials (`FIREBASE_PROJECT_ID`) are configured in `.env`, create the demo user accounts (`demo.manager@appitutors.co.uk`, `demo.tutor@appitutors.co.uk`, `demo.parent@appitutors.co.uk`) inside your Firebase Authentication Console.
> - The database backend and session management (`AuthService::loginWithFirebase`) strictly match by `email` and `firebase_uid`.
