<?php
declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Core Authentication and Session Service
 */
class AuthService
{
    public const SESSION_USER_KEY = 'auth_user';

    /**
     * Authenticate and establish a session for a user using verified Firebase UID
     *
     * @param string $firebaseUid Verified UID from Firebase
     * @param string $email Verified Email from Firebase
     * @param string $firstName User's first name
     * @param string $lastName User's last name
     * @param string|null $requestedRole Role requested ONLY during initial registration ('TUTOR' or 'STUDENT_PARENT')
     * @param string|null $avatarUrl Profile picture URL
     * @return array Established session user array
     */
    public static function loginWithFirebase(
        string $firebaseUid,
        string $email,
        string $firstName,
        string $lastName,
        ?string $requestedRole = null,
        ?string $avatarUrl = null
    ): array {
        $db = Connection::getInstance();

        // 1. Check if user already exists by firebase_uid or email
        $stmt = $db->prepare('
            SELECT u.*, r.name AS role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.firebase_uid = :uid OR u.email = :email
            LIMIT 1
        ');
        $stmt->execute([
            ':uid' => $firebaseUid,
            ':email' => $email,
        ]);
        $user = $stmt->fetch();

        if ($user) {
            // Existing user: NEVER override existing database role with client-supplied role
            $updateStmt = $db->prepare('
                UPDATE users 
                SET firebase_uid = :uid, avatar_url = COALESCE(:avatar, avatar_url), updated_at = NOW()
                WHERE id = :id
            ');
            $updateStmt->execute([
                ':uid' => $firebaseUid,
                ':avatar' => $avatarUrl,
                ':id' => $user['id'],
            ]);

            // Re-fetch to get any updated info
            $fetchStmt = $db->prepare('
                SELECT u.*, r.name AS role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id
                LIMIT 1
            ');
            $fetchStmt->execute([':id' => $user['id']]);
            $user = $fetchStmt->fetch();
        } else {
            // New Registration: Validate requested role. Never allow public registration as MANAGER
            $roleToAssign = 'STUDENT_PARENT';
            if ($requestedRole === 'TUTOR') {
                $roleToAssign = 'TUTOR';
            }

            // Resolve Role ID
            $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :role_name LIMIT 1');
            $roleStmt->execute([':role_name' => $roleToAssign]);
            $role = $roleStmt->fetch();

            if (!$role) {
                throw new RuntimeException("Invalid role specified: {$roleToAssign}");
            }

            // Create new user
            $insertStmt = $db->prepare('
                INSERT INTO users (firebase_uid, role_id, email, first_name, last_name, avatar_url, status)
                VALUES (:uid, :role_id, :email, :first_name, :last_name, :avatar, "ACTIVE")
            ');
            $insertStmt->execute([
                ':uid' => $firebaseUid,
                ':role_id' => $role['id'],
                ':email' => $email,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':avatar' => $avatarUrl,
            ]);

            $userId = (int)$db->lastInsertId();

            // If tutor role, initialize a tutor profile in PENDING status
            if ($roleToAssign === 'TUTOR') {
                $tutorProfileStmt = $db->prepare('
                    INSERT INTO tutor_profiles (user_id, approval_status, hourly_rate)
                    VALUES (:user_id, "PENDING", 35.00)
                ');
                $tutorProfileStmt->execute([':user_id' => $userId]);
            }

            // Fetch newly created user
            $fetchStmt = $db->prepare('
                SELECT u.*, r.name AS role_name 
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id
                LIMIT 1
            ');
            $fetchStmt->execute([':id' => $userId]);
            $user = $fetchStmt->fetch();
        }

        // Check tutor profile approval status if user is a TUTOR
        $approvalStatus = null;
        $tutorProfileId = null;
        if ($user['role_name'] === 'TUTOR') {
            $tutorStmt = $db->prepare('SELECT id, approval_status FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
            $tutorStmt->execute([':uid' => $user['id']]);
            $tutorProfile = $tutorStmt->fetch();
            if ($tutorProfile) {
                $approvalStatus = $tutorProfile['approval_status'];
                $tutorProfileId = (int)$tutorProfile['id'];
            }
        }

        // Regenerate session ID to prevent session fixation attacks
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        // Store sanitized user payload in session
        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => (int)$user['id'],
            'firebase_uid' => $user['firebase_uid'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role_id' => (int)$user['role_id'],
            'role' => $user['role_name'],
            'status' => $user['status'],
            'avatar_url' => $user['avatar_url'],
            'tutor_profile_id' => $tutorProfileId,
            'tutor_approval_status' => $approvalStatus,
        ];

        return $_SESSION[self::SESSION_USER_KEY];
    }

    /**
     * Get currently logged-in user from session
     */
    public static function user(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::SESSION_USER_KEY] ?? null;
    }

    /**
     * Refresh session user data directly from database
     */
    public static function refreshSession(): ?array
    {
        $currentUser = self::user();
        if (!$currentUser) {
            return null;
        }

        $db = Connection::getInstance();
        $stmt = $db->prepare('
            SELECT u.*, r.name AS role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logout();
            return null;
        }

        $approvalStatus = null;
        $tutorProfileId = null;
        if ($user['role_name'] === 'TUTOR') {
            $tutorStmt = $db->prepare('SELECT id, approval_status FROM tutor_profiles WHERE user_id = :uid LIMIT 1');
            $tutorStmt->execute([':uid' => $user['id']]);
            $tutorProfile = $tutorStmt->fetch();
            if ($tutorProfile) {
                $approvalStatus = $tutorProfile['approval_status'];
                $tutorProfileId = (int)$tutorProfile['id'];
            }
        }

        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => (int)$user['id'],
            'firebase_uid' => $user['firebase_uid'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role_id' => (int)$user['role_id'],
            'role' => $user['role_name'],
            'status' => $user['status'],
            'avatar_url' => $user['avatar_url'],
            'tutor_profile_id' => $tutorProfileId,
            'tutor_approval_status' => $approvalStatus,
        ];

        return $_SESSION[self::SESSION_USER_KEY];
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Check if current user has a specific role
     */
    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return in_array($user['role'], $roles, true);
    }

    /**
     * Destroy user session
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_USER_KEY]);
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
