<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\DeferForeignKeysInMigrations;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class DeferForeignKeysInMigrationsTest extends TestCase
{
    public function testAMigrationCanRebuildATableThatOtherRowsReference(): void
    {
        $connection = self::connectionWithAReferencedProject();

        new DeferForeignKeysInMigrations()->onMigrationsVersionExecuting(self::versionExecuting($connection));
        $connection->transactional(self::rebuildProject(...));

        self::assertSame('Kadence', $connection->fetchOne('SELECT title FROM project WHERE id = 1'));
    }

    public function testForeignKeysAreEnforcedAgainOnceTheMigrationIsCommitted(): void
    {
        $connection = self::connectionWithAReferencedProject();
        new DeferForeignKeysInMigrations()->onMigrationsVersionExecuting(self::versionExecuting($connection));
        $connection->transactional(self::rebuildProject(...));

        $this->expectException(ForeignKeyConstraintViolationException::class);

        $connection->executeStatement('DELETE FROM project');
    }

    private static function connectionWithAReferencedProject(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([new EnableForeignKeys()]));
        $connection->executeStatement('CREATE TABLE project (id INTEGER PRIMARY KEY NOT NULL, title VARCHAR(150) NOT NULL)');
        $connection->executeStatement('CREATE TABLE lot (id INTEGER PRIMARY KEY NOT NULL, project_id INTEGER NOT NULL REFERENCES project (id))');
        $connection->executeStatement("INSERT INTO project (id, title) VALUES (1, 'Kadence')");
        $connection->executeStatement('INSERT INTO lot (id, project_id) VALUES (1, 1)');

        return $connection;
    }

    /**
     * The way Doctrine alters a table on SQLite.
     */
    private static function rebuildProject(Connection $connection): void
    {
        $connection->executeStatement('CREATE TEMPORARY TABLE __temp__project AS SELECT id, title FROM project');
        $connection->executeStatement('DROP TABLE project');
        $connection->executeStatement('CREATE TABLE project (id INTEGER PRIMARY KEY NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL)');
        $connection->executeStatement('INSERT INTO project (id, title) SELECT id, title FROM __temp__project');
        $connection->executeStatement('DROP TABLE __temp__project');
    }

    private static function versionExecuting(Connection $connection): MigrationsVersionEventArgs
    {
        $migration = new class($connection, new NullLogger()) extends AbstractMigration {
            public function up(Schema $schema): void
            {
            }
        };

        return new MigrationsVersionEventArgs($connection, new MigrationPlan(new Version('Test'), $migration, Direction::UP), new MigratorConfiguration());
    }
}
