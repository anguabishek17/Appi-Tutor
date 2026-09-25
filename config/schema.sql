-- AppiTutors Database Schema
-- MySQL 8.0+ / InnoDB / utf8mb4

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS newsletter_subscribers;
DROP TABLE IF EXISTS blog_posts;
DROP TABLE IF EXISTS lesson_notes;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS availability_slots;
DROP TABLE IF EXISTS tutor_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS curricula;
DROP TABLE IF EXISTS students_children;
DROP TABLE IF EXISTS tutor_profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Roles table
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id, name, description) VALUES
(1, 'MANAGER', 'AppiTutors Administrator / Operations Manager'),
(2, 'TUTOR', 'Verified or Prospective Tutor'),
(3, 'STUDENT_PARENT', 'Parent or Independent Student Client');

-- 2. Users table (Central authentication & user records)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) NOT NULL UNIQUE,
    role_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    avatar_url VARCHAR(500) NULL,
    status ENUM('PENDING', 'ACTIVE', 'SUSPENDED', 'DEACTIVATED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_status ON users(status);

-- 3. Tutor Profiles table
CREATE TABLE tutor_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    headline VARCHAR(255) NULL,
    bio TEXT NULL,
    hourly_rate DECIMAL(8, 2) NOT NULL DEFAULT 35.00,
    experience_years INT UNSIGNED DEFAULT 1,
    qualifications TEXT NULL,
    dbs_certificate_path VARCHAR(500) NULL,
    dbs_verified_at TIMESTAMP NULL,
    approval_status ENUM('PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED') NOT NULL DEFAULT 'PENDING',
    teaching_mode ENUM('ONLINE', 'IN_PERSON', 'BOTH') NOT NULL DEFAULT 'BOTH',
    approved_by INT UNSIGNED NULL,
    approval_notes TEXT NULL,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tutor_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_profiles_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tutor_approval ON tutor_profiles(approval_status);
CREATE INDEX idx_tutor_hourly_rate ON tutor_profiles(hourly_rate);

-- 4. Students / Children Profiles (Parent manages multiple children)
CREATE TABLE students_children (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_user_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    year_group VARCHAR(50) NULL,
    school_name VARCHAR(150) NULL,
    learning_goals TEXT NULL,
    special_needs_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_parent FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_students_parent ON students_children(parent_user_id);

-- 5. Curricula table (UK Curricula: KS1, KS2, KS3, GCSE, A-Level, IB, 11-Plus)
CREATE TABLE curricula (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO curricula (name, code, description) VALUES
('Primary (KS1 & KS2)', 'PRIMARY_KS1_KS2', 'Key Stage 1 and 2 curriculum for ages 5-11'),
('11+ & Entrance Exams', 'ENTRANCE_11_PLUS', 'Preparation for grammar and independent school exams'),
('Secondary (KS3)', 'KS3', 'Key Stage 3 curriculum for ages 11-14'),
('GCSE & IGCSE', 'GCSE', 'General Certificate of Secondary Education preparation'),
('A-Level & AS', 'A_LEVEL', 'Advanced Level qualification preparation'),
('International Baccalaureate (IB)', 'IB', 'IB Diploma and Middle Years Programme');

-- 6. Subjects table (e.g., Mathematics, Physics, English Literature, Chemistry, Biology)
CREATE TABLE subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curriculum_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    icon_class VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subject_curriculum (curriculum_id, name),
    CONSTRAINT fk_subjects_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_subjects_curriculum ON subjects(curriculum_id);
CREATE INDEX idx_subjects_slug ON subjects(slug);

-- 7. Tutor Subjects mapping (Subjects a tutor is approved/assigned to teach)
CREATE TABLE tutor_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_profile_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    custom_rate DECIMAL(8, 2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tutor_subject (tutor_profile_id, subject_id),
    CONSTRAINT fk_tutor_subjects_profile FOREIGN KEY (tutor_profile_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tutor_subjects_profile ON tutor_subjects(tutor_profile_id);
CREATE INDEX idx_tutor_subjects_subject ON tutor_subjects(subject_id);

-- 8. Availability Slots table (Dated & time-bounded slots per SRS requirements)
CREATE TABLE availability_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_profile_id INT UNSIGNED NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    session_type ENUM('ONE_TO_ONE', 'GROUP') NOT NULL DEFAULT 'ONE_TO_ONE',
    delivery_mode ENUM('ONLINE', 'IN_PERSON') NOT NULL DEFAULT 'ONLINE',
    max_capacity INT UNSIGNED NOT NULL DEFAULT 1,
    booked_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_availability_tutor FOREIGN KEY (tutor_profile_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_availability_tutor_time ON availability_slots(tutor_profile_id, start_time, end_time);
CREATE INDEX idx_availability_status ON availability_slots(is_blocked, booked_count, max_capacity);

-- 9. Bookings table
CREATE TABLE bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    parent_user_id INT UNSIGNED NOT NULL,
    tutor_profile_id INT UNSIGNED NOT NULL,
    student_child_id INT UNSIGNED NULL,
    subject_id INT UNSIGNED NOT NULL,
    scheduled_start TIMESTAMP NOT NULL,
    scheduled_end TIMESTAMP NOT NULL,
    status ENUM(
        'PENDING',
        'ACCEPTED',
        'REJECTED',
        'RESCHEDULE_PROPOSED',
        'CANCELLED',
        'COMPLETED',
        'SYSTEM_CANCELLED'
    ) NOT NULL DEFAULT 'PENDING',
    proposed_reschedule_start TIMESTAMP NULL,
    proposed_reschedule_end TIMESTAMP NULL,
    proposed_availability_slot_id INT UNSIGNED NULL,
    reschedule_proposed_by ENUM('TUTOR', 'STUDENT_PARENT') NULL,
    rejection_reason TEXT NULL,
    cancellation_reason TEXT NULL,
    hourly_rate DECIMAL(8, 2) NOT NULL,
    total_amount DECIMAL(8, 2) NOT NULL,
    student_notes TEXT NULL,
    meeting_link VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_parent FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_tutor FOREIGN KEY (tutor_profile_id) REFERENCES tutor_profiles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_proposed_slot FOREIGN KEY (proposed_availability_slot_id) REFERENCES availability_slots(id) ON DELETE SET NULL,
    CONSTRAINT fk_bookings_student FOREIGN KEY (student_child_id) REFERENCES students_children(id) ON DELETE SET NULL,
    CONSTRAINT fk_bookings_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_bookings_parent ON bookings(parent_user_id);
CREATE INDEX idx_bookings_tutor ON bookings(tutor_profile_id);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_schedule ON bookings(scheduled_start, scheduled_end);

-- 10. Lesson Notes table (Private notes created by tutor after/during lessons)
CREATE TABLE lesson_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL UNIQUE,
    tutor_user_id INT UNSIGNED NOT NULL,
    topics_covered TEXT NOT NULL,
    homework_assigned TEXT NULL,
    student_progress_rating TINYINT UNSIGNED NULL COMMENT '1 to 5 scale',
    private_tutor_notes TEXT NULL COMMENT 'Visible only to tutor and manager',
    parent_feedback_notes TEXT NULL COMMENT 'Visible to parent/student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lesson_notes_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_lesson_notes_tutor FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_lesson_notes_tutor ON lesson_notes(tutor_user_id);

-- 11. Blog Posts table (CMS for AppiTutors advice, study tips, and news)
CREATE TABLE blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    cover_image_url VARCHAR(500) NULL,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_blog_published ON blog_posts(is_published, published_at);
CREATE INDEX idx_blog_slug ON blog_posts(slug);

-- 12. Newsletter Subscribers table
CREATE TABLE newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    source VARCHAR(100) DEFAULT 'website_footer',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_newsletter_active ON newsletter_subscribers(is_active);

-- 13. Audit Logs table (Records important Manager and System operations)
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_audit_action ON audit_logs(action);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

-- Initial Seed: AppiTutors Manager Account (role_id = 1 is MANAGER)
INSERT INTO users (id, firebase_uid, role_id, email, first_name, last_name, phone, avatar_url, status)
VALUES (1, 'manager_demo_uid_001', 1, 'manager@appitutors.co.uk', 'Operations', 'Manager', '+44 20 7946 0912', NULL, 'ACTIVE')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Initial Seed: Common UK Subjects across Curricula
INSERT INTO subjects (curriculum_id, name, slug) VALUES
-- Primary KS1 & KS2 (id: 1)
(1, 'Primary Mathematics', 'primary-mathematics'),
(1, 'Primary English (Reading & Writing)', 'primary-english'),
(1, 'Primary Science', 'primary-science'),
(1, 'Phonics & Early Reading', 'phonics-early-reading'),

-- 11+ & Entrance Exams (id: 2)
(2, '11+ Verbal Reasoning', '11-plus-verbal-reasoning'),
(2, '11+ Non-Verbal Reasoning', '11-plus-non-verbal-reasoning'),
(2, '11+ Mathematics', '11-plus-mathematics'),
(2, '11+ English Comprehension', '11-plus-english-comprehension'),

-- Secondary KS3 (id: 3)
(3, 'KS3 Mathematics', 'ks3-mathematics'),
(3, 'KS3 English', 'ks3-english'),
(3, 'KS3 Science', 'ks3-science'),
(3, 'KS3 French', 'ks3-french'),
(3, 'KS3 Spanish', 'ks3-spanish'),
(3, 'KS3 History', 'ks3-history'),
(3, 'KS3 Geography', 'ks3-geography'),

-- GCSE & IGCSE (id: 4)
(4, 'GCSE Mathematics (Foundation & Higher)', 'gcse-mathematics'),
(4, 'GCSE English Language', 'gcse-english-language'),
(4, 'GCSE English Literature', 'gcse-english-literature'),
(4, 'GCSE Biology', 'gcse-biology'),
(4, 'GCSE Chemistry', 'gcse-chemistry'),
(4, 'GCSE Physics', 'gcse-physics'),
(4, 'GCSE Combined Science', 'gcse-combined-science'),
(4, 'GCSE Computer Science', 'gcse-computer-science'),
(4, 'GCSE French', 'gcse-french'),
(4, 'GCSE Spanish', 'gcse-spanish'),
(4, 'GCSE History', 'gcse-history'),
(4, 'GCSE Geography', 'gcse-geography'),
(4, 'GCSE Business Studies', 'gcse-business-studies'),
(4, 'GCSE Economics', 'gcse-economics'),

-- A-Level & AS (id: 5)
(5, 'A-Level Mathematics', 'a-level-mathematics'),
(5, 'A-Level Further Mathematics', 'a-level-further-mathematics'),
(5, 'A-Level Physics', 'a-level-physics'),
(5, 'A-Level Chemistry', 'a-level-chemistry'),
(5, 'A-Level Biology', 'a-level-biology'),
(5, 'A-Level English Literature', 'a-level-english-literature'),
(5, 'A-Level Economics', 'a-level-economics'),
(5, 'A-Level Computer Science', 'a-level-computer-science'),
(5, 'A-Level Psychology', 'a-level-psychology'),
(5, 'A-Level History', 'a-level-history'),

-- IB (id: 6)
(6, 'IB Mathematics Analysis & Approaches (HL/SL)', 'ib-math-aa'),
(6, 'IB Mathematics Applications & Interpretation (HL/SL)', 'ib-math-ai'),
(6, 'IB Physics (HL/SL)', 'ib-physics'),
(6, 'IB Chemistry (HL/SL)', 'ib-chemistry'),
(6, 'IB Biology (HL/SL)', 'ib-biology'),
(6, 'IB Economics (HL/SL)', 'ib-economics'),
(6, 'IB English A: Literature', 'ib-english-a')
ON DUPLICATE KEY UPDATE name = VALUES(name);

