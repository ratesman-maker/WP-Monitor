<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use Ramsey\Uuid\Uuid;
use WPMonitor\Security\CryptoService;
use WPMonitor\Storage\Connection;

/**
 * Manages user sessions in the `user_sessions` table.
 *
 * The encryption key (derived from the master password) is wrapped (encrypted)
 * with a key derived from APP_KEY before being stored. This way the encryption
 * key survives across requests (within the session lifetime) without being
 * stored in plaintext.
 */
final class SessionService implements SessionServiceInterface
{
    private CryptoService $wrapperCrypto;

    public function __construct(
        private readonly Connection $connection,
        string $appKey
    ) {
        $wrapperKey = $this->deriveWrapperKey($appKey);
        $this->wrapperCrypto = new CryptoService($wrapperKey);
    }

    /**
     * Create a new session for a user.
     *
     * @return array{id: string, csrfToken: string, expiresAt: int}
     */
    public function create(
        int $userId,
        string $ipAddress,
        string $userAgent,
        string $encryptionKey,
        string $jwtJti,
        int $sessionTimeout
    ): array {
        $sessionId = Uuid::uuid4()->toString();
        $csrfToken = bin2hex(random_bytes(32)); // 64 hex chars
        $expiresAt = time() + $sessionTimeout;

        $userAgentHash = hash('sha256', $userAgent);
        $wrappedKey = $this->wrapperCrypto->encrypt($encryptionKey, $sessionId);

        $this->connection->executeStatement(
            'INSERT INTO user_sessions
                (id, user_id, ip_address, user_agent_hash, encryption_key, jwt_jti, csrf_token, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))',
            [
                $sessionId,
                $userId,
                $ipAddress,
                $userAgentHash,
                $wrappedKey,
                $jwtJti,
                $csrfToken,
                $expiresAt,
            ]
        );

        return [
            'id' => $sessionId,
            'csrfToken' => $csrfToken,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Retrieve a session by ID. Returns null if not found or expired.
     *
     * @return array{id: string, userId: int, jwtJti: string, csrfToken: string, encryptionKey: string}|null
     */
    public function get(string $sessionId): ?array
    {
        $rows = $this->connection->executeQuery(
            'SELECT id, user_id, ip_address, user_agent_hash, encryption_key,
                    jwt_jti, csrf_token, UNIX_TIMESTAMP(expires_at) AS expires_ts
             FROM user_sessions
             WHERE id = ? AND expires_at > NOW()',
            [$sessionId]
        );

        if (count($rows) === 0) {
            return null;
        }

        $row = $rows[0];

        $encryptionKey = $this->wrapperCrypto->decrypt(
            (string) $row['encryption_key'],
            $sessionId
        );

        return [
            'id' => (string) $row['id'],
            'userId' => (int) $row['user_id'],
            'jwtJti' => (string) $row['jwt_jti'],
            'csrfToken' => (string) $row['csrf_token'],
            'encryptionKey' => $encryptionKey,
        ];
    }

    /**
     * Destroy a session by ID (logout / revocation).
     */
    public function destroy(string $sessionId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM user_sessions WHERE id = ?',
            [$sessionId]
        );
    }

    /**
     * Destroy all sessions for a user (e.g. password change).
     */
    public function destroyAllForUser(int $userId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM user_sessions WHERE user_id = ?',
            [$userId]
        );
    }

    /**
     * Delete all expired sessions. Returns the number of deleted rows.
     */
    public function cleanup(): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM user_sessions WHERE expires_at <= NOW()'
        );
    }

    /**
     * Update last_activity timestamp for a session.
     */
    public function touch(string $sessionId): void
    {
        $this->connection->executeStatement(
            'UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE id = ?',
            [$sessionId]
        );
    }

    /**
     * Find a session ID by JWT JTI. Returns null if no active session matches.
     * Used by AuthMiddleware for JTI-based revocation checks.
     */
    public function findByJti(string $jti): ?string
    {
        $rows = $this->connection->executeQuery(
            'SELECT id FROM user_sessions WHERE jwt_jti = ? AND expires_at > NOW() LIMIT 1',
            [$jti]
        );

        if (count($rows) === 0) {
            return null;
        }

        return (string) $rows[0]['id'];
    }

    private function deriveWrapperKey(string $appKey): string
    {
        $raw = str_starts_with($appKey, 'base64:')
            ? (base64_decode(substr($appKey, 7), true) ?: '')
            : $appKey;

        // Derive exactly 32 bytes via SHA-256 of the raw key
        return hash('sha256', $raw . '|session-wrapper', true);
    }
}
