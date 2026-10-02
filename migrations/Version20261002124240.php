<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002124240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create lot_progress table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lot_progress (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, declared_on DATE NOT NULL, percent SMALLINT NOT NULL, entered_quarters INTEGER NOT NULL, remaining_quarters INTEGER DEFAULT NULL, lot_id INTEGER NOT NULL, author_id INTEGER NOT NULL, CONSTRAINT FK_80CB0372A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_80CB0372F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_lot_progress_lot_day ON lot_progress (lot_id, declared_on)');
        $this->addSql('CREATE INDEX IDX_80CB0372A8CBA5F7 ON lot_progress (lot_id)');
        $this->addSql('CREATE INDEX IDX_80CB0372F675F31B ON lot_progress (author_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE lot_progress');
    }
}
