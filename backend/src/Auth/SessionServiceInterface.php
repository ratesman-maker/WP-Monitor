<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

/**
 * Contract for session management operations.
 *
 * Implemented by {@see SessionService}. Extracted as an interface so that
 * middleware depending on it can be unit-tested with a simple stub instead
 * of mocking the final concrete class.
 */
interface SessionServiceInterface
{
    /**
     * @return array{id: string, userId: int, jwtJti: string, csrfToken: string, encryptionKey: string}|null
     */
    public function get(string $sessionId): ?array;

    public function destroy(string $sessionId): void;

    public function destroyAllForUser(int $userId): void;

    public function cleanup(): int;

    public function touch(string $sessionId): void;

    public function findByJti(string $jti): ?string;
}
