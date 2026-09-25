<?php declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

/**
 * Service for transactional email delivery and development logging.
 */
class EmailService
{
    private static ?array $config = null;

    private static function getConfig(): array
    {
        if (self::$config === null) {
            $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
            self::$config = $appConfig['mail'] ?? [
                'mailer' => 'smtp',
                'host' => '127.0.0.1',
                'port' => 2525,
                'username' => null,
                'password' => null,
                'encryption' => null,
                'from_address' => 'noreply@appitutors.co.uk',
                'from_name' => 'AppiTutors',
            ];
        }
        return self::$config;
    }

    /**
     * Send an email with HTML body and optional plain-text fallback.
     * Logs the dispatch in notification_logs.
     * Never throws unhandled exceptions to callers.
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $html HTML email body
     * @param string|null $text Plain-text fallback
     * @param int|null $bookingId Optional booking ID for association
     * @param string $eventType Event name (e.g. BOOKING_CREATED)
     * @param string|null $recipientName Recipient display name
     * @return bool True if sent or successfully logged in dev mode, False on failure
     */
    public static function send(
        string $to,
        string $subject,
        string $html,
        ?string $text = null,
        ?int $bookingId = null,
        string $eventType = 'GENERAL_NOTIFICATION',
        ?string $recipientName = null
    ): bool {
        $config = self::getConfig();
        $text = $text ?? strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));

        $status = 'DEV_LOGGED';
        $errorMessage = null;

        try {
            // Check if live SMTP credentials are provided
            $hasSmtpCreds = !empty($config['host']) && !empty($config['username']) && !empty($config['password']);

            if ($hasSmtpCreds && ($config['mailer'] ?? 'smtp') === 'smtp') {
                $sent = self::sendViaSmtp($to, $subject, $html, $text, $config);
                $status = $sent ? 'SENT' : 'FAILED';
            } else {
                // Development fallback: Record to storage/logs/mail.log safely without crashing
                self::logToDevFile($to, $subject, $html, $eventType);
                $status = 'DEV_LOGGED';
            }
        } catch (\Throwable $e) {
            $status = 'FAILED';
            $errorMessage = $e->getMessage();
            error_log("[EmailService Error] Failed to send email to {$to}: " . $e->getMessage());
        }

        // Record in notification_logs
        self::logNotification($bookingId, $to, $recipientName, $eventType, $subject, $status, $errorMessage);

        return $status === 'SENT' || $status === 'DEV_LOGGED';
    }

    /**
     * Send email via direct SMTP socket connection.
     */
    private static function sendViaSmtp(string $to, string $subject, string $html, string $text, array $config): bool
    {
        $host = $config['host'];
        $port = (int)$config['port'];
        $timeout = 10;
        $from = $config['from_address'];
        $fromName = $config['from_name'];

        $socketHost = ($config['encryption'] === 'ssl') ? 'ssl://' . $host : $host;
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            throw new \RuntimeException("Could not connect to SMTP server {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, $timeout);

        self::readSmtpResponse($socket, 220);

        self::writeSmtpCommand($socket, "EHLO " . gethostname(), 250);

        if ($config['encryption'] === 'tls') {
            self::writeSmtpCommand($socket, "STARTTLS", 220);
            crypto_enable:
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException("STARTTLS negotiation failed");
            }
            self::writeSmtpCommand($socket, "EHLO " . gethostname(), 250);
        }

        if (!empty($config['username']) && !empty($config['password'])) {
            self::writeSmtpCommand($socket, "AUTH LOGIN", 334);
            self::writeSmtpCommand($socket, base64_encode($config['username']), 334);
            self::writeSmtpCommand($socket, base64_encode($config['password']), 235);
        }

        self::writeSmtpCommand($socket, "MAIL FROM:<{$from}>", 250);
        self::writeSmtpCommand($socket, "RCPT TO:<{$to}>", 250);
        self::writeSmtpCommand($socket, "DATA", 354);

        $boundary = "==Multipart_Boundary_x" . md5((string)time()) . "x";

        $headers = [
            "From: {$fromName} <{$from}>",
            "To: <{$to}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date('r'),
            "X-Mailer: AppiTutors Transactional Mailer",
        ];

        $messageBody = implode("\r\n", $headers) . "\r\n\r\n";
        $messageBody .= "--{$boundary}\r\n";
        $messageBody .= "Content-Type: text/plain; charset=utf-8\r\n";
        $messageBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $messageBody .= $text . "\r\n\r\n";
        $messageBody .= "--{$boundary}\r\n";
        $messageBody .= "Content-Type: text/html; charset=utf-8\r\n";
        $messageBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $messageBody .= $html . "\r\n\r\n";
        $messageBody .= "--{$boundary}--\r\n";
        $messageBody .= ".\r\n";

        fwrite($socket, $messageBody);
        self::readSmtpResponse($socket, 250);

        self::writeSmtpCommand($socket, "QUIT", 221);
        fclose($socket);

        return true;
    }

    private static function writeSmtpCommand($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return self::readSmtpResponse($socket, $expectedCode);
    }

    private static function readSmtpResponse($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP Command failed. Expected {$expectedCode} but received: {$response}");
        }

        return $response;
    }

    private static function logToDevFile(string $to, string $subject, string $html, string $eventType): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logEntry = sprintf(
            "[%s] [%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $eventType,
            $to,
            $subject,
            str_repeat('-', 60),
            $html
        );

        @file_put_contents($logDir . '/mail.log', $logEntry, FILE_APPEND);
    }

    private static function logNotification(
        ?int $bookingId,
        string $recipientEmail,
        ?string $recipientName,
        string $eventType,
        string $subject,
        string $status,
        ?string $errorMessage
    ): void {
        try {
            $db = Connection::getInstance();
            $stmt = $db->prepare("
                INSERT INTO notification_logs 
                (booking_id, recipient_email, recipient_name, event_type, subject, status, error_message, created_at)
                VALUES (:bid, :email, :name, :event, :subject, :status, :err, NOW())
            ");
            $stmt->execute([
                ':bid' => $bookingId,
                ':email' => $recipientEmail,
                ':name' => $recipientName,
                ':event' => $eventType,
                ':subject' => $subject,
                ':status' => $status,
                ':err' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            error_log("[EmailService DB Log Error] " . $e->getMessage());
        }
    }
}
