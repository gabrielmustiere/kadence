<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930123737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create lot_member table, add lot.start_date';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lot_member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, share SMALLINT NOT NULL, lot_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_972A9F12A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_972A9F12A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_lot_member_lot_user ON lot_member (lot_id, user_id)');
        $this->addSql('CREATE INDEX IDX_972A9F12A8CBA5F7 ON lot_member (lot_id)');
        $this->addSql('CREATE INDEX IDX_972A9F12A76ED395 ON lot_member (user_id)');
        $this->addSql('ALTER TABLE lot ADD COLUMN start_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE lot_member');
        $this->addSql('CREATE TEMPORARY TABLE __temp__lot AS SELECT id, title, description, estimate_days, initial_estimate_days, project_id, parent_id, owner_id FROM lot');
        $this->addSql('DROP TABLE lot');
        $this->addSql('CREATE TABLE lot (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL, estimate_days INTEGER DEFAULT NULL, initial_estimate_days INTEGER DEFAULT NULL, project_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, owner_id INTEGER DEFAULT NULL, CONSTRAINT FK_B81291B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B727ACA70 FOREIGN KEY (parent_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO lot (id, title, description, estimate_days, initial_estimate_days, project_id, parent_id, owner_id) SELECT id, title, description, estimate_days, initial_estimate_days, project_id, parent_id, owner_id FROM __temp__lot');
        $this->addSql('DROP TABLE __temp__lot');
        $this->addSql('CREATE INDEX IDX_B81291B166D1F9C ON lot (project_id)');
        $this->addSql('CREATE INDEX IDX_B81291B727ACA70 ON lot (parent_id)');
        $this->addSql('CREATE INDEX IDX_B81291B7E3C61F9 ON lot (owner_id)');
    }
}
