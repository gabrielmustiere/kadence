<?php

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;

/**
 * SQLite alters a table by rebuilding it: with foreign keys enforced, dropping a table that other rows reference fails
 * unless the check waits for the commit of the migration, by which time the table is rebuilt. The pragma lasts until
 * that commit, so a migration that is not transactional is not covered.
 */
#[AsDoctrineListener(event: Events::onMigrationsVersionExecuting)]
final class DeferForeignKeysInMigrations
{
    public function onMigrationsVersionExecuting(MigrationsVersionEventArgs $args): void
    {
        $args->getConnection()->executeStatement('PRAGMA defer_foreign_keys = ON');
    }
}
