<?php
declare(strict_types=1);

namespace App\Auth;

use Exception;

/**
 * Cryptographic Firebase ID Token Verification Service
 * Validates Google/Firebase ID Tokens:
 * 1. RS256 Cryptographic Signature against Google public x509 certificates
 * 2. Issuer matching https://securetoken.google.com/<projectId>
 * 3. Audience matching <projectId>
 * 4. Expiration timestamp (exp > now)
 * 5. Issued-at timestamp (auth_time / iat <= now)
 * 6. Non-empty subject (sub / uid)
 */
class FirebaseVerifier
{
    private string $projectId;
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function __construct(string $projectId)
    {
        $this->projectId = trim($projectId);
    }

    /**
     * Cryptographically verify a Firebase ID token.
     * Never accepts an unverified or forged token.
     */
    public function verifyIdToken(string $idToken): ?array
    {
        $idToken = trim($idToken);
        if (empty($idToken) || empty($this->projectId) || $this->projectId === 'appitutors-demo') {
            // When using demo placeholder or empty token, reject strictly
            return null;
        }

        // 1. If Kreait SDK is installed and configured:
        if (class_exists('Kreait\Firebase\Factory')) {
            try {
                $credentialsPath = (require dirname(__DIR__, 2) . '/config/app.php')['firebase']['credentials_path'];
                $factory = (new \Kreait\Firebase\Factory());
                if (file_exists($credentialsPath)) {
                    $factory = $factory->withServiceAccount($credentialsPath);
                }
                $auth = $factory->createAuth();
                $verifiedIdToken = $auth->verifyIdToken($idToken);
                return [
                    'uid' => $verifiedIdToken->claims()->get('sub'),
                    'email' => $verifiedIdToken->claims()->get('email'),
                    'name' => $verifiedIdToken->claims()->get('name', ''),
                    'picture' => $verifiedIdToken->claims()->get('picture', null),
                ];
            } catch (Exception $e) {
                error_log('[Firebase Kreait Verification Failed]: ' . $e->getMessage());
                // Fall through to strict OpenSSL verification against Google Public JWKs
            }
        }

        // 2. Cryptographic OpenSSL RS256 Verification against Google's Public Certificates
        return $this->verifyWithGooglePublicCerts($idToken);
    }

    /**
     * Parse and cryptographically verify RS256 signature and standard claims
     */
    private function verifyWithGooglePublicCerts(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headB64, $bodyB64, $sigB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headB64), true);
        $payload = json_decode($this->base64UrlDecode($bodyB64), true);
        $signature = $this->base64UrlDecode($sigB64);

        if (!$header || !$payload || empty($signature)) {
            return null;
        }

        // A. Algorithm must be RS256
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            return null;
        }

        // B. Issuer check
        $expectedIssuer = 'https://securetoken.google.com/' . $this->projectId;
        if (($payload['iss'] ?? '') !== $expectedIssuer) {
            return null;
        }

        // C. Audience check
        if (($payload['aud'] ?? '') !== $this->projectId) {
            return null;
        }

        // D. Expiration & Time checks (with 60s clock skew tolerance)
        $now = time();
        if (!isset($payload['exp']) || ($payload['exp'] + 60) < $now) {
            return null;
        }
        if (!isset($payload['iat']) || ($payload['iat'] - 60) > $now) {
            return null;
        }

        // E. Subject must not be empty
        if (empty($payload['sub'])) {
            return null;
        }

        // F. Cryptographic RS256 Signature Verification via OpenSSL
        $publicKey = $this->getGooglePublicKey((string)$header['kid']);
        if (!$publicKey) {
            return null;
        }

        $signedData = $headB64 . '.' . $bodyB64;
        $verified = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            error_log('[Firebase Signature Failed]: Signature did not match Google public key.');
            return null;
        }

        return [
            'uid' => $payload['sub'],
            'email' => $payload['email'] ?? '',
            'name' => $payload['name'] ?? '',
            'picture' => $payload['picture'] ?? null,
        ];
    }

    /**
     * Fetch and cache Google's public x509 certificates
     */
    private function getGooglePublicKey(string $kid): ?string
    {
        $cacheFile = sys_get_temp_dir() . '/firebase_google_certs.json';
        $certs = null;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $certs = json_decode(file_get_contents($cacheFile), true);
        }

        if (!$certs) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'AppiTutors-AuthVerifier/1.0'
                ]
            ]);
            $raw = @file_get_contents(self::GOOGLE_CERTS_URL, false, $context);
            if ($raw) {
                $certs = json_decode($raw, true);
                if ($certs) {
                    @file_put_contents($cacheFile, $raw);
                }
            }
        }

        return $certs[$kid] ?? null;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }
}
