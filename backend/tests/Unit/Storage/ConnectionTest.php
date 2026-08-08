<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Storage;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Result;
use WPMonitor\Storage\Connection;
use WPMonitor\Tests\TestCase;

final class ConnectionTest extends TestCase
{
    public function testGetConnectionReturnsInjectedInstance(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $connection = new Connection($dbal);

        $this->assertSame($dbal, $connection->getConnection());
    }

    public function testExecuteQueryReturnsAssociativeRows(): void
    {
        $expected = [['id' => 1, 'name' => 'test'], ['id' => 2, 'name' => 'other']];
        $result = \Mockery::mock(Result::class);
        $result->expects('fetchAllAssociative')->andReturn($expected);

        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('executeQuery')->with('SELECT * FROM sites', [])->andReturn($result);

        $connection = new Connection($dbal);
        $rows = $connection->executeQuery('SELECT * FROM sites');

        $this->assertSame($expected, $rows);
    }

    public function testExecuteQueryPassesParams(): void
    {
        $params = [1, 'online'];
        $result = \Mockery::mock(Result::class);
        $result->expects('fetchAllAssociative')->andReturn([]);

        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('executeQuery')->with('SELECT * FROM sites WHERE id = ? AND status = ?', $params)->andReturn($result);

        $connection = new Connection($dbal);
        $connection->executeQuery('SELECT * FROM sites WHERE id = ? AND status = ?', $params);
    }

    public function testExecuteStatementReturnsInt(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('executeStatement')->with('DELETE FROM sites WHERE id = ?', [1])->andReturn(3);

        $connection = new Connection($dbal);
        $affected = $connection->executeStatement('DELETE FROM sites WHERE id = ?', [1]);

        $this->assertSame(3, $affected);
    }

    public function testExecuteStatementCastsStringReturnToInt(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('executeStatement')->with('UPDATE sites SET status = ?', ['online'])->andReturn('5');

        $connection = new Connection($dbal);
        $affected = $connection->executeStatement('UPDATE sites SET status = ?', ['online']);

        $this->assertSame(5, $affected);
    }

    public function testBeginTransactionDelegatesToDbal(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('beginTransaction')->once();

        $connection = new Connection($dbal);
        $connection->beginTransaction();

    }

    public function testCommitDelegatesToDbal(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('commit')->once();

        $connection = new Connection($dbal);
        $connection->commit();

    }

    public function testRollBackDelegatesToDbal(): void
    {
        $dbal = \Mockery::mock(DBALConnection::class);
        $dbal->expects('rollBack')->once();

        $connection = new Connection($dbal);
        $connection->rollBack();

    }
}
