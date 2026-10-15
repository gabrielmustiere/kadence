<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015115637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the entered and remaining quarters frozen on lot_progress: the remaining is now computed from the time entered';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__lot_progress AS SELECT id, declared_on, percent, lot_id, author_id FROM lot_progress');
        $this->addSql('DROP TABLE lot_progress');
        $this->addSql('CREATE TABLE lot_progress (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, declared_on DATE NOT NULL, percent SMALLINT NOT NULL, lot_id INTEGER NOT NULL, author_id INTEGER NOT NULL, CONSTRAINT FK_80CB0372A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_80CB0372F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO lot_progress (id, declared_on, percent, lot_id, author_id) SELECT id, declared_on, percent, lot_id, author_id FROM __temp__lot_progress');
        $this->addSql('DROP TABLE __temp__lot_progress');
        $this->addSql('CREATE INDEX IDX_80CB0372F675F31B ON lot_progress (author_id)');
        $this->addSql('CREATE INDEX IDX_80CB0372A8CBA5F7 ON lot_progress (lot_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_lot_progress_lot_day ON lot_progress (lot_id, declared_on)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Irreversible data: the quarters dropped by up() are not restored, and a NULL remaining_quarters makes the former code ignore every declaration.
        $this->addSql('ALTER TABLE lot_progress ADD COLUMN entered_quarters INTEGER DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE lot_progress ADD COLUMN remaining_quarters INTEGER DEFAULT NULL');
    }
}
