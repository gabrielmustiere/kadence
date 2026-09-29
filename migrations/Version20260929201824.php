<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929201824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create time_entry and weekly_max tables, add lot.initial_estimate_days';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE time_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, day DATE NOT NULL, quarters SMALLINT NOT NULL, user_id INTEGER NOT NULL, lot_id INTEGER NOT NULL, CONSTRAINT FK_6E537C0CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6E537C0CA8CBA5F7 FOREIGN KEY (lot_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_time_entry_user_day ON time_entry (user_id, day)');
        $this->addSql('CREATE UNIQUE INDEX uniq_time_entry_user_lot_day ON time_entry (user_id, lot_id, day)');
        $this->addSql('CREATE INDEX IDX_6E537C0CA76ED395 ON time_entry (user_id)');
        $this->addSql('CREATE INDEX IDX_6E537C0CA8CBA5F7 ON time_entry (lot_id)');
        $this->addSql('CREATE TABLE weekly_max (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, effective_from DATE NOT NULL, quarters SMALLINT NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_8AFC2D4CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_weekly_max_user_from ON weekly_max (user_id, effective_from)');
        $this->addSql('CREATE INDEX IDX_8AFC2D4CA76ED395 ON weekly_max (user_id)');
        $this->addSql('ALTER TABLE lot ADD COLUMN initial_estimate_days INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE time_entry');
        $this->addSql('DROP TABLE weekly_max');
        $this->addSql('CREATE TEMPORARY TABLE __temp__lot AS SELECT id, title, description, estimate_days, project_id, parent_id, owner_id FROM lot');
        $this->addSql('DROP TABLE lot');
        $this->addSql('CREATE TABLE lot (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL, estimate_days INTEGER DEFAULT NULL, project_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, owner_id INTEGER DEFAULT NULL, CONSTRAINT FK_B81291B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B727ACA70 FOREIGN KEY (parent_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO lot (id, title, description, estimate_days, project_id, parent_id, owner_id) SELECT id, title, description, estimate_days, project_id, parent_id, owner_id FROM __temp__lot');
        $this->addSql('DROP TABLE __temp__lot');
        $this->addSql('CREATE INDEX IDX_B81291B166D1F9C ON lot (project_id)');
        $this->addSql('CREATE INDEX IDX_B81291B727ACA70 ON lot (parent_id)');
        $this->addSql('CREATE INDEX IDX_B81291B7E3C61F9 ON lot (owner_id)');
    }
}
