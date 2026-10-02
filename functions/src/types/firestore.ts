/**
 * UK Tutoring Platform - Firestore Data Models & Types
 * Data Model & Security Boundary Phase (F03)
 */

/**
 * Tutor Onboarding Status Lifecycle States.
 */
export type TutorOnboardingStatus =
  | 'REGISTERED'
  | 'EMAIL_VERIFIED'
  | 'PROFILE_COMPLETE'
  | 'DBS_SUBMITTED'
  | 'PENDING_REVIEW'
  | 'MANAGER_APPROVED'
  | 'REJECTED'
  | 'SUSPENDED';

/**
 * Tutor Approval Statuses.
 */
export type TutorApprovalStatus = 'PENDING' | 'APPROVED' | 'REJECTED' | 'SUSPENDED';

/**
 * DBS Verification Statuses.
 */
export type DbsVerifiedStatus =
  'UNVERIFIED' | 'NOT_SUBMITTED' | 'SUBMITTED' | 'VERIFIED' | 'REJECTED';
export type DbsStatus = DbsVerifiedStatus;

/**
 * Availability Slot Statuses.
 */
export type AvailabilitySlotStatus = 'AVAILABLE' | 'BOOKED' | 'BLOCKED';

/**
 * Booking Lifecycle States.
 */
export type BookingStatus =
  | 'PENDING'
  | 'CONFIRMED'
  | 'REJECTED'
  | 'RESCHEDULE_PROPOSED'
  | 'CANCELLED'
  | 'COMPLETED'
  | 'SYSTEM_CANCELLED';

/**
 * Collection: users/{parentUid}/children/{childId}
 * Children associated with a STUDENT_PARENT account.
 */
export interface ChildDocument {
  childId: string;
  parentUid: string;
  firstName: string;
  lastName: string;
  dateOfBirth: string; // ISO 8601 format: YYYY-MM-DD
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Collection: tutorProfiles/{uid}
 * Private tutor profile.
 */
export interface TutorProfileDocument {
  uid: string;
  bio?: string;
  subjects?: string[];
  qualifications?: string[];
  hourlyRatePence?: number;
  contactPhone?: string;
  // Onboarding & Lifecycle fields
  onboardingStatus?: TutorOnboardingStatus;
  approvalStatus?: TutorApprovalStatus;
  dbsStatus?: DbsStatus;
  dbsDocumentPath?: string;
  dbsSubmittedAt?: unknown;
  dbsVerifiedAt?: unknown;
  bookableStatus?: boolean;
  publicStatus?: boolean;
  isBookable?: boolean;
  isPublic?: boolean;
  approvedAt?: unknown;
  approvedBy?: string | null;
  rejectedAt?: unknown;
  rejectedBy?: string | null;
  rejectionReason?: string | null;
  suspendedAt?: unknown;
  suspendedBy?: string | null;
  suspensionReason?: string | null;
  managerNotes?: string;
  createdAt?: unknown;
  updatedAt?: unknown;
}

/**
 * Collection: publicTutors/{uid}
 * Public read model (safe public browsing).
 * Excludes DBS, private contact, identity, manager notes, and auth info.
 */
export interface PublicTutorDocument {
  uid: string;
  displayName: string;
  bio: string;
  subjects: string[];
  hourlyRatePence: number;
  avatarUrl?: string;
  updatedAt: unknown;
}

/**
 * Collection: studentProfiles/{uid}
 * Student/Parent profile data separate from authentication.
 */
export interface StudentProfileDocument {
  uid: string;
  emergencyContact?: {
    name: string;
    phone: string;
    relationship: string;
  };
  educationalStage?: string;
  notes?: string;
  createdAt?: unknown;
  updatedAt?: unknown;
}

/**
 * Collection: availabilitySlots/{slotId}
 * Tutor availability slots stored in UTC.
 */
export interface AvailabilitySlotDocument {
  slotId: string;
  tutorId: string;
  startAt: unknown; // UTC Instant / Firestore Timestamp
  endAt: unknown; // UTC Instant / Firestore Timestamp
  timezone?: string; // e.g. "Europe/London"
  status: AvailabilitySlotStatus;
  bookingId?: string | null;
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Audit entry for Booking Status History.
 */
export interface BookingStatusHistoryEntry {
  status: BookingStatus;
  changedAt: unknown;
  changedBy: string; // User UID or 'SYSTEM'
  changedByRole?: string; // 'STUDENT_PARENT' | 'TUTOR' | 'MANAGER' | 'SYSTEM'
  reason?: string;
}

/**
 * Collection: bookings/{bookingId}
 */
export interface BookingDocument {
  bookingId: string;
  studentUid: string;
  parentId?: string | null;
  tutorUid: string;
  childId?: string | null;
  slotId?: string | null;
  startAt: unknown;
  endAt: unknown;
  status: BookingStatus;
  statusHistory: BookingStatusHistoryEntry[];
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Collection: lessonNotes/{noteId}
 */
export interface LessonNoteDocument {
  noteId: string;
  bookingId: string;
  tutorUid: string;
  studentUid: string;
  content: string;
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Collection: auditLogs/{id}
 * Server write-only security audit log entry.
 */
export interface AuditLogDocument {
  id: string;
  eventType: string;
  actorUid: string;
  targetUid?: string;
  details?: Record<string, unknown>;
  timestamp: unknown;
}

/**
 * Collection: newsletterSubscribers/{id}
 */
export interface NewsletterSubscriberDocument {
  id: string;
  email: string;
  subscribedAt: unknown;
}

/**
 * Collection: blogPosts/{postId}
 */
export interface BlogPostDocument {
  postId: string;
  title: string;
  slug: string;
  content: string;
  isPublished: boolean;
  publishedAt?: unknown | null;
  authorUid: string;
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Collection: testimonials/{id}
 */
export interface TestimonialDocument {
  id: string;
  authorName: string;
  role: string;
  content: string;
  rating: number;
  isApproved: boolean;
  createdAt: unknown;
}

/**
 * Collection: content/{id}
 * Controlled platform configuration or CMS content.
 */
export interface ContentDocument {
  id: string;
  key: string;
  data: Record<string, unknown>;
  updatedAt: unknown;
}

/**
 * Payment Status States.
 */
export type PaymentStatus =
  'PENDING' | 'SUCCEEDED' | 'FAILED' | 'CANCELLED' | 'REFUNDED' | 'PARTIALLY_REFUNDED';

/**
 * Refund Status States.
 */
export type RefundStatus = 'PENDING' | 'SUCCEEDED' | 'FAILED' | 'CANCELLED';

/**
 * Collection: payments/{paymentId}
 * Payment record referencing booking. Stored in integer minor units (e.g. pence).
 */
export interface PaymentDocument {
  id: string;
  paymentId: string;
  bookingId: string;
  studentId: string;
  studentUid?: string;
  parentId?: string | null;
  tutorId: string;
  tutorUid?: string;
  amount: number; // Integer minor units (e.g. 3500 pence)
  currency: string; // e.g. 'GBP'
  status: PaymentStatus;
  provider: string; // e.g. 'MOCK_PROVIDER' | 'STRIPE'
  providerPaymentId: string;
  idempotencyKey?: string | null;
  refundedAmount?: number;
  createdAt: unknown;
  updatedAt: unknown;
  metadata?: Record<string, unknown>;
}

/**
 * Collection: refunds/{refundId}
 * Refund record referencing payment and booking.
 */
export interface RefundDocument {
  id: string;
  refundId: string;
  paymentId: string;
  bookingId: string;
  amount: number; // Integer minor units
  currency: string;
  status: RefundStatus;
  reason?: string | null;
  providerRefundId?: string | null;
  createdBy: string; // User UID or 'SYSTEM'
  createdAt: unknown;
  updatedAt: unknown;
}

/**
 * Collection: paymentEvents/{eventId}
 * Webhook event log preventing duplicate processing.
 */
export interface PaymentEventDocument {
  id: string;
  provider: string;
  providerEventId: string;
  paymentId?: string | null;
  eventType: string;
  receivedAt: unknown;
  processedAt?: unknown | null;
  status: 'PROCESSED' | 'FAILED' | 'IGNORED';
}

/**
 * Collection: idempotencyKeys/{key}
 */
export interface IdempotencyKeyDocument {
  key: string;
  paymentId: string;
  response: Record<string, unknown>;
  createdAt: unknown;
}
