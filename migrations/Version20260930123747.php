<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930123747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Put the owner of each leaf in its team, at 100 %';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO lot_member (lot_id, user_id, share) SELECT l.id, l.owner_id, 100 FROM lot l WHERE l.owner_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM lot c WHERE c.parent_id = l.id)');
    }

    public function down(Schema $schema): void
    {
        // Nothing to undo: the team rows of the owners are dropped with the lot_member table by the previous migration.
    }
}
