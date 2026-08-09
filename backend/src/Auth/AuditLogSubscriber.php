<?php

declare(strict_types=1);

namespace WPMonitor\Auth;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WPMonitor\Storage\Connection;

/**
 * Subscribes to {@see AuditLogEvent} and persists audit entries to the `audit_log` table.
 *
 * This decouples audit logging from the services that trigger it — any module
 * can dispatch AuditLogEvent without knowing how or where it is stored.
 */
final class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AuditLogEvent::class => 'onAuditLog',
        ];
    }

    public function onAuditLog(AuditLogEvent $event): void
    {
        $this->connection->executeStatement(
            'INSERT INTO audit_log
                (user_id, action, site_id, module, status, details, ip_address, user_agent, request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $event->userId,
                $event->action,
                $event->siteId,
                $event->module,
                $event->status,
                $event->details !== null ? json_encode($event->details) ?: null : null,
                $event->ipAddress,
                $event->userAgent,
                $event->requestId,
            ]
        );
    }
}
