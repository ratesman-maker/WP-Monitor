<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use RuntimeException;

/**
 * Thrown for authentication-related failures (invalid credentials, locked account, etc.).
 */
final class AuthException extends RuntimeException
{
    public const REASON_INVALID_CREDENTIALS = 'invalid_credentials';
    public const REASON_ACCOUNT_LOCKED = 'account_locked';
    public const REASON_ACCOUNT_INACTIVE = 'account_inactive';
    public const REASON_SETUP_ALREADY_DONE = 'setup_already_done';
    public const REASON_INVALID_INPUT = 'invalid_input';

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('Invalid username or password.', self::REASON_INVALID_CREDENTIALS);
    }

    public static function accountLocked(): self
    {
        return new self('Account is locked due to too many failed login attempts.', self::REASON_ACCOUNT_LOCKED);
    }

    public static function accountInactive(): self
    {
        return new self('Account is inactive.', self::REASON_ACCOUNT_INACTIVE);
    }

    public static function setupAlreadyDone(): self
    {
        return new self('Setup has already been completed.', self::REASON_SETUP_ALREADY_DONE);
    }

    public static function invalidInput(string $detail): self
    {
        return new self($detail, self::REASON_INVALID_INPUT);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
