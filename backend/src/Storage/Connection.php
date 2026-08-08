<?php

declare(strict_types=1);

namespace WPMonitor\Storage;

use Doctrine\DBAL\Connection as DBALConnection;

final class Connection
{
    public function __construct(
        private readonly DBALConnection $connection
    ) {
    }

    public function getConnection(): DBALConnection
    {
        return $this->connection;
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<array<string, mixed|null>>
     */
    public function executeQuery(string $sql, array $params = []): array
    {
        return $this->connection->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * @param list<mixed> $params
     */
    public function executeStatement(string $sql, array $params = []): int
    {
        return (int) $this->connection->executeStatement($sql, $params);
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }
}
