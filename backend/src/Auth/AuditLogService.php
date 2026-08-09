<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches {@see AuditLogEvent} via Symfony EventDispatcher.
 *
 * Services (AuthService, future modules) call AuditLogService::log() to record
 * security-relevant actions. The actual persistence is handled by
 * {@see AuditLogSubscriber}, keeping this service thin and decoupled.
 */
final class AuditLogService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @param array<string,mixed>|null $details
     */
    public function log(
        string $action,
        string $status,
        ?int $userId = null,
        ?int $siteId = null,
        ?string $module = null,
        ?array $details = null,
        string $ipAddress = '',
        string $userAgent = '',
        string $requestId = ''
    ): void {
        $this->dispatcher->dispatch(new AuditLogEvent(
            $action,
            $status,
            $userId,
            $siteId,
            $module,
            $details,
            $ipAddress,
            $userAgent,
            $requestId
        ));
    }
}
