<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use WPMonitor\Security\CryptoService;
use WPMonitor\Security\DecryptionException;
use WPMonitor\Security\KeyDerivationService;
use WPMonitor\Storage\Connection;

/**
 * Authentication service — login, logout, first setup.
 *
 * Login flow (per docs/03-security-model.md):
 *   1. Load user by username (including salt + verification token)
 *   2. Check account lockout
 *   3. Derive encryption key from password + salt (Argon2id, interactive)
 *   4. Attempt to decrypt the verification token
 *   5. On success: create session, issue JWT + CSRF, log audit, reset fail count
 *   6. On failure: increment fail count, maybe lock, log audit
 *
 * The master password is NEVER stored. The encryption key is wrapped (encrypted
 * with APP_KEY) and stored in the session for the duration of its lifetime.
 */
final class AuthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KeyDerivationService $keyDerivation,
        private readonly JwtService $jwtService,
        private readonly SessionService $sessionService,
        private readonly AuditLogService $auditLog,
        private readonly int $lockoutThreshold,
        private readonly int $lockoutDuration
    ) {
    }

    /**
     * First setup — create the initial admin user.
     *
     * @return array{id: int}
     *
     * @throws AuthException if users already exist or input is invalid
     */
    public function setup(string $username, string $password, ?string $email = null): array
    {
        $userCount = (int) $this->connection->executeQuery('SELECT COUNT(*) AS c FROM users', [])[0]['c'];
        if ($userCount > 0) {
            throw AuthException::setupAlreadyDone();
        }

        $this->validateUsername($username);
        $this->validatePassword($password);
        if ($email !== null) {
            $this->validateEmail($email);
        }

        $salt = KeyDerivationService::generateSalt();
        $key = $this->keyDerivation->deriveKey(
            $password,
            $salt,
            KeyDerivationService::PROFILE_INTERACTIVE
        );

        $crypto = new CryptoService($key);
        $verificationAad = pack('NN', 0, 0);
        $verificationToken = $crypto->encrypt('WPMONITOR_VERIFY', $verificationAad);

        $this->connection->executeStatement(
            'INSERT INTO users (username, email, role, password_salt, verification_token, verification_aad, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$username, $email, 'admin', $salt, $verificationToken, $verificationAad]
        );

        $userId = (int) $this->connection->getConnection()->lastInsertId();

        $this->auditLog->log(
            'auth.setup',
            'success',
            $userId,
            null,
            null,
            ['username' => $username]
        );

        return ['id' => $userId];
    }

    /**
     * Login with username + master password.
     *
     * @return array{token: string, refreshToken: string, csrfToken: string, sessionId: string, user: array{id: int, username: string, role: string, email: ?string}}
     *
     * @throws AuthException if credentials are invalid or account is locked
     */
    public function login(
        string $username,
        string $password,
        string $ipAddress,
        string $userAgent
    ): array {
        $users = $this->connection->executeQuery(
            'SELECT id, username, email, role, password_salt, verification_token, verification_aad,
                    failed_login_count, locked_until, is_active
             FROM users WHERE username = ?',
            [$username]
        );

        if (count($users) === 0) {
            $this->auditLog->log('auth.login', 'failed', null, null, null, [
                'username' => $username,
                'reason' => 'user_not_found',
            ], $ipAddress, $userAgent);

            throw AuthException::invalidCredentials();
        }

        $user = $users[0];

        if ((int) $user['is_active'] !== 1) {
            $this->auditLog->log('auth.login', 'failed', (int) $user['id'], null, null, [
                'reason' => 'account_inactive',
            ], $ipAddress, $userAgent);

            throw AuthException::accountInactive();
        }

        $this->checkLockout($user, $ipAddress, $userAgent);

        // Derive key and attempt to decrypt verification token
        $salt = (string) $user['password_salt'];
        $key = $this->keyDerivation->deriveKey(
            $password,
            $salt,
            KeyDerivationService::PROFILE_INTERACTIVE
        );

        $crypto = new CryptoService($key);

        try {
            $crypto->decrypt(
                (string) $user['verification_token'],
                (string) $user['verification_aad']
            );
        } catch (DecryptionException) {
            $this->handleFailedLogin((int) $user['id'], $username, $ipAddress, $userAgent);

            throw AuthException::invalidCredentials();
        }

        // Success — reset fail count, update last login
        $this->connection->executeStatement(
            'UPDATE users SET failed_login_count = 0, locked_until = NULL,
                    last_login_at = CURRENT_TIMESTAMP, last_login_ip = ?
             WHERE id = ?',
            [$ipAddress, $user['id']]
        );

        // Issue JWT
        $access = $this->jwtService->issueAccessToken((int) $user['id'], (string) $user['role']);
        $refresh = $this->jwtService->issueRefreshToken((int) $user['id'], (string) $user['role']);

        // Create session with wrapped encryption key
        $session = $this->sessionService->create(
            (int) $user['id'],
            $ipAddress,
            $userAgent,
            $key,
            $access['jti'],
            900 // session timeout — will be overridden by caller via settings
        );

        $this->auditLog->log('auth.login', 'success', (int) $user['id'], null, null, [
            'username' => $username,
        ], $ipAddress, $userAgent);

        return [
            'token' => $access['token'],
            'refreshToken' => $refresh['token'],
            'csrfToken' => $session['csrfToken'],
            'sessionId' => $session['id'],
            'user' => [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'role' => (string) $user['role'],
                'email' => $user['email'] !== null ? (string) $user['email'] : null,
            ],
        ];
    }

    /**
     * Logout — destroy the session.
     */
    public function logout(string $sessionId, int $userId, string $ipAddress, string $userAgent): void
    {
        $this->sessionService->destroy($sessionId);

        $this->auditLog->log('auth.logout', 'success', $userId, null, null, [
            'session_id' => $sessionId,
        ], $ipAddress, $userAgent);
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @return array{token: string, refreshToken: string}
     *
     * @throws AuthException
     */
    public function refresh(string $refreshToken): array
    {
        try {
            $claims = $this->jwtService->verify($refreshToken, JwtService::TYPE_REFRESH);
        } catch (JwtException) {
            throw AuthException::invalidCredentials();
        }

        $userId = (int) $claims['sub'];
        $role = (string) $claims['role'];

        $users = $this->connection->executeQuery(
            'SELECT is_active FROM users WHERE id = ?',
            [$userId]
        );

        if (count($users) === 0 || (int) $users[0]['is_active'] !== 1) {
            throw AuthException::accountInactive();
        }

        $access = $this->jwtService->issueAccessToken($userId, $role);
        $newRefresh = $this->jwtService->issueRefreshToken($userId, $role);

        return [
            'token' => $access['token'],
            'refreshToken' => $newRefresh['token'],
        ];
    }

    /**
     * Get user info by ID.
     *
     * @return array{id: int, username: string, role: string, email: ?string}|null
     */
    public function getUser(int $userId): ?array
    {
        $rows = $this->connection->executeQuery(
            'SELECT id, username, email, role FROM users WHERE id = ? AND is_active = 1',
            [$userId]
        );

        if (count($rows) === 0) {
            return null;
        }

        $row = $rows[0];

        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'role' => (string) $row['role'],
            'email' => $row['email'] !== null ? (string) $row['email'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $user
     */
    private function checkLockout(array $user, string $ipAddress, string $userAgent): void
    {
        $lockedUntil = $user['locked_until'];
        if ($lockedUntil !== null) {
            $lockedTs = strtotime((string) $lockedUntil);
            if ($lockedTs !== false && $lockedTs > time()) {
                $this->auditLog->log('auth.login', 'failed', (int) $user['id'], null, null, [
                    'reason' => 'account_locked',
                    'locked_until' => $lockedUntil,
                ], $ipAddress, $userAgent);

                throw AuthException::accountLocked();
            }
        }
    }

    private function handleFailedLogin(
        int $userId,
        string $username,
        string $ipAddress,
        string $userAgent
    ): void {
        $this->connection->executeStatement(
            'UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?',
            [$userId]
        );

        $rows = $this->connection->executeQuery(
            'SELECT failed_login_count FROM users WHERE id = ?',
            [$userId]
        );

        $failCount = count($rows) === 0 ? 0 : (int) $rows[0]['failed_login_count'];

        if ($failCount >= $this->lockoutThreshold) {
            $this->connection->executeStatement(
                'UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?',
                [$this->lockoutDuration, $userId]
            );
        }

        $this->auditLog->log('auth.login', 'failed', $userId, null, null, [
            'username' => $username,
            'reason' => 'wrong_password',
            'fail_count' => $failCount,
        ], $ipAddress, $userAgent);
    }

    private function validateUsername(string $username): void
    {
        $len = strlen($username);
        if ($len < 3 || $len > 50) {
            throw AuthException::invalidInput('Username must be 3-50 characters.');
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12) {
            throw AuthException::invalidInput('Password must be at least 12 characters.');
        }
    }

    private function validateEmail(string $email): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw AuthException::invalidInput('Email address is not valid.');
        }
    }
}
