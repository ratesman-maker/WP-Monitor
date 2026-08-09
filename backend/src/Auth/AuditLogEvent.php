<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

/**
 * Event dispatched for every security-relevant action (login, logout, credential access, etc.).
 *
 * Carried by Symfony EventDispatcher and consumed by AuditLogSubscriber which
 * persists it to the `audit_log` table.
 */
final class AuditLogEvent
{
    /**
     * @param array<string,mixed>|null $details
     */
    public function __construct(
        public readonly string $action,
        public readonly string $status,
        public readonly ?int $userId = null,
        public readonly ?int $siteId = null,
        public readonly ?string $module = null,
        public readonly ?array $details = null,
        public readonly string $ipAddress = '',
        public readonly string $userAgent = '',
        public readonly string $requestId = ''
    ) {
    }
}
