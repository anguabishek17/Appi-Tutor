<?php declare(strict_types=1);

namespace App\Services;

/**
 * High-level service for sending transactional booking event notifications.
 */
class BookingNotificationService
{
    private static function renderTemplate(string $title, string $intro, array $details, ?string $actionNote = null): string
    {
        $rows = '';
        foreach ($details as $label => $val) {
            $rows .= "<tr>
                <td style='padding: 8px 12px; font-weight: bold; color: #475569; width: 35%; border-bottom: 1px solid #f1f5f9;'>" . htmlspecialchars((string)$label) . "</td>
                <td style='padding: 8px 12px; color: #1e293b; border-bottom: 1px solid #f1f5f9;'>" . htmlspecialchars((string)$val) . "</td>
            </tr>";
        }

        $actionHtml = '';
        if (!empty($actionNote)) {
            $actionHtml = "<div style='margin-top: 20px; padding: 12px 16px; background-color: #f8fafc; border-left: 4px solid #6366f1; border-radius: 4px; font-size: 13px; color: #334155;'>
                " . htmlspecialchars($actionNote) . "
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>" . htmlspecialchars($title) . "</title>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;'>
            <table align='center' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;'>
                <!-- Header -->
                <tr>
                    <td style='background-color: #4f46e5; padding: 24px 32px; text-align: left;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px;'>Appi<span style='color: #a5b4fc;'>Tutors</span></h1>
                        <p style='color: #e0e7ff; margin: 4px 0 0 0; font-size: 13px;'>UK Tutoring & Academic Mentorship</p>
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td style='padding: 32px;'>
                        <h2 style='color: #0f172a; margin: 0 0 12px 0; font-size: 18px; font-weight: 700;'>" . htmlspecialchars($title) . "</h2>
                        <p style='color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;'>" . htmlspecialchars($intro) . "</p>
                        
                        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px;'>
                            {$rows}
                        </table>

                        {$actionHtml}
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style='background-color: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;'>
                        <p style='margin: 0;'>This is an automated message from AppiTutors Ltd.</p>
                        <p style='margin: 4px 0 0 0;'>© " . date('Y') . " AppiTutors Ltd. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * 1. BOOKING_CREATED (Sent to Tutor)
     */
    public static function notifyBookingCreated(array $booking, string $tutorEmail, string $tutorName, string $parentName, string $childName, string $subjectName): bool
    {
        $subject = "New Booking Request: {$booking['booking_reference']} - {$subjectName}";
        $intro = "Hello {$tutorName}, you have received a new lesson booking request. Please log into your portal to review and accept the session.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Subject' => $subjectName,
            'Student' => $childName,
            'Parent' => $parentName,
            'Scheduled Start' => $booking['scheduled_start'] . ' (UK)',
            'Scheduled End' => $booking['scheduled_end'] . ' (UK)',
            'Delivery Mode' => $booking['delivery_mode'] ?? 'ONLINE',
            'Action Deadline' => '24 hours before lesson start'
        ];

        $html = self::renderTemplate('New Lesson Booking Request', $intro, $details, 'Please accept or decline this request at least 24 hours prior to the session.');
        return EmailService::send($tutorEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_CREATED', $tutorName);
    }

    /**
     * 2. BOOKING_ACCEPTED (Sent to Parent)
     */
    public static function notifyBookingAccepted(array $booking, string $parentEmail, string $parentName, string $tutorName, string $childName, string $subjectName): bool
    {
        $subject = "Booking Confirmed: {$booking['booking_reference']} - {$subjectName}";
        $intro = "Hello {$parentName}, great news! Your tutor has accepted the lesson booking.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Tutor' => $tutorName,
            'Student' => $childName,
            'Subject' => $subjectName,
            'Scheduled Start' => $booking['scheduled_start'] . ' (UK)',
            'Scheduled End' => $booking['scheduled_end'] . ' (UK)',
            'Status' => 'Confirmed & Accepted'
        ];

        $html = self::renderTemplate('Lesson Booking Confirmed', $intro, $details, 'Your lesson is confirmed. You can view session details in your parent dashboard.');
        return EmailService::send($parentEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_ACCEPTED', $parentName);
    }

    /**
     * 3. BOOKING_REJECTED (Sent to Parent)
     */
    public static function notifyBookingRejected(array $booking, string $parentEmail, string $parentName, string $tutorName, string $childName, string $subjectName, ?string $reason): bool
    {
        $subject = "Booking Request Declined: {$booking['booking_reference']}";
        $intro = "Hello {$parentName}, tutor {$tutorName} was unable to accept your lesson request.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Tutor' => $tutorName,
            'Student' => $childName,
            'Subject' => $subjectName,
            'Requested Time' => $booking['scheduled_start'] . ' (UK)',
            'Reason' => !empty($reason) ? $reason : 'Tutor unavailable'
        ];

        $html = self::renderTemplate('Lesson Request Declined', $intro, $details, 'You may search for other available tutors or select another time slot.');
        return EmailService::send($parentEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_REJECTED', $parentName);
    }

    /**
     * 4. BOOKING_RESCHEDULE_PROPOSED (Sent to Parent)
     */
    public static function notifyRescheduleProposed(array $booking, string $parentEmail, string $parentName, string $tutorName, string $proposedStart, string $proposedEnd): bool
    {
        $subject = "Reschedule Proposed: {$booking['booking_reference']}";
        $intro = "Hello {$parentName}, your tutor has proposed a new time for your upcoming lesson.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Tutor' => $tutorName,
            'Original Time' => $booking['scheduled_start'] . ' (UK)',
            'Proposed New Time' => $proposedStart . ' to ' . $proposedEnd . ' (UK)'
        ];

        $html = self::renderTemplate('Reschedule Proposal Received', $intro, $details, 'Please log into your parent portal to accept or decline the proposed new time.');
        return EmailService::send($parentEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_RESCHEDULE_PROPOSED', $parentName);
    }

    /**
     * 5. BOOKING_RESCHEDULE_ACCEPTED (Sent to Tutor)
     */
    public static function notifyRescheduleAccepted(array $booking, string $tutorEmail, string $tutorName, string $parentName, string $childName, string $newStart, string $newEnd): bool
    {
        $subject = "Reschedule Accepted: {$booking['booking_reference']}";
        $intro = "Hello {$tutorName}, the parent has accepted your proposed reschedule.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Student' => $childName,
            'Parent' => $parentName,
            'Confirmed New Time' => $newStart . ' to ' . $newEnd . ' (UK)',
            'Status' => 'Confirmed & Accepted'
        ];

        $html = self::renderTemplate('Reschedule Accepted', $intro, $details, 'The lesson schedule has been updated on your calendar.');
        return EmailService::send($tutorEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_RESCHEDULE_ACCEPTED', $tutorName);
    }

    /**
     * 6. BOOKING_RESCHEDULE_DECLINED (Sent to Tutor)
     */
    public static function notifyRescheduleDeclined(array $booking, string $tutorEmail, string $tutorName, string $parentName): bool
    {
        $subject = "Reschedule Declined: {$booking['booking_reference']}";
        $intro = "Hello {$tutorName}, the parent declined the proposed reschedule. The original lesson time remains active.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Parent' => $parentName,
            'Active Schedule' => $booking['scheduled_start'] . ' to ' . $booking['scheduled_end'] . ' (UK)'
        ];

        $html = self::renderTemplate('Reschedule Proposal Declined', $intro, $details, 'Your original confirmed lesson time remains intact.');
        return EmailService::send($tutorEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_RESCHEDULE_DECLINED', $tutorName);
    }

    /**
     * 7. BOOKING_CANCELLED (Sent to opposing party)
     */
    public static function notifyBookingCancelled(
        array $booking,
        string $recipientEmail,
        string $recipientName,
        string $cancelledByRole,
        ?string $reason
    ): bool {
        $subject = "Booking Cancelled: {$booking['booking_reference']}";
        $intro = "Hello {$recipientName}, the lesson booking has been cancelled by the {$cancelledByRole}.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Cancelled By' => $cancelledByRole,
            'Scheduled Time' => $booking['scheduled_start'] . ' (UK)',
            'Cancellation Reason' => !empty($reason) ? $reason : 'Cancelled in accordance with the 24-hour cancellation policy'
        ];

        $html = self::renderTemplate('Lesson Booking Cancelled', $intro, $details, 'The reserved slot capacity has been released back to the tutor schedule.');
        return EmailService::send($recipientEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_CANCELLED', $recipientName);
    }

    /**
     * 8. BOOKING_SYSTEM_CANCELLED (Sent to Parent and Tutor)
     */
    public static function notifySystemCancelled(
        array $booking,
        string $parentEmail,
        string $parentName,
        string $tutorEmail,
        string $tutorName
    ): void {
        $subject = "Booking Automatically Cancelled: {$booking['booking_reference']}";
        $introParent = "Hello {$parentName}, your booking request has been automatically cancelled because the tutor did not respond before the required 24-hour deadline.";
        $introTutor = "Hello {$tutorName}, booking request {$booking['booking_reference']} was automatically cancelled because it was not confirmed before the 24-hour action deadline.";

        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Scheduled Start' => $booking['scheduled_start'] . ' (UK)',
            'Status' => 'SYSTEM_CANCELLED',
            'Reason' => 'Booking was automatically cancelled because the tutor did not respond before the required 24-hour deadline.'
        ];

        $parentHtml = self::renderTemplate('Booking Automatically Cancelled', $introParent, $details, 'No charges were made. You may book another session with an available tutor.');
        $tutorHtml = self::renderTemplate('Booking Request Expired', $introTutor, $details, 'The slot capacity has been released back to your schedule.');

        EmailService::send($parentEmail, $subject, $parentHtml, null, (int)$booking['id'], 'BOOKING_SYSTEM_CANCELLED', $parentName);
        EmailService::send($tutorEmail, $subject, $tutorHtml, null, (int)$booking['id'], 'BOOKING_SYSTEM_CANCELLED', $tutorName);
    }

    /**
     * 9. BOOKING_COMPLETED (Sent to Parent)
     */
    public static function notifyBookingCompleted(
        array $booking,
        string $parentEmail,
        string $parentName,
        string $tutorName,
        string $childName,
        string $subjectName,
        string $attendanceStatus
    ): bool {
        $subject = "Lesson Completed: {$subjectName} with {$tutorName}";
        $intro = "Hello {$parentName}, your lesson for {$childName} with {$tutorName} has been marked as completed. Lesson notes and attendance summary are now available in your portal.";
        $details = [
            'Booking Reference' => $booking['booking_reference'],
            'Student' => $childName,
            'Tutor' => $tutorName,
            'Subject' => $subjectName,
            'Date & Time' => $booking['scheduled_start'] . ' (UK)',
            'Attendance' => $attendanceStatus,
            'Status' => 'COMPLETED'
        ];

        $html = self::renderTemplate('Lesson Completed', $intro, $details, 'Log in to your AppiTutors account to view complete lesson notes and recommendations.');
        return EmailService::send($parentEmail, $subject, $html, null, (int)$booking['id'], 'BOOKING_COMPLETED', $parentName);
    }
}

