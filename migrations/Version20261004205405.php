<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004205405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create favorite_lot table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_lot (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, lot_id INTEGER NOT NULL, CONSTRAINT FK_3F93A19A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3F93A19A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_lot_user_lot ON favorite_lot (user_id, lot_id)');
        $this->addSql('CREATE INDEX IDX_3F93A19A76ED395 ON favorite_lot (user_id)');
        $this->addSql('CREATE INDEX IDX_3F93A19A8CBA5F7 ON favorite_lot (lot_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE favorite_lot');
    }
}
