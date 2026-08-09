<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use RuntimeException;

/**
 * Thrown when a JWT cannot be verified (invalid signature, expired, wrong issuer/type).
 */
final class JwtException extends RuntimeException
{
    public const REASON_INVALID = 'invalid';
    public const REASON_EXPIRED = 'expired';

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function invalid(string $detail): self
    {
        return new self($detail, self::REASON_INVALID);
    }

    public static function expired(): self
    {
        return new self('Token has expired.', self::REASON_EXPIRED);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
