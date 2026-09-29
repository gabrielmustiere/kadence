<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929190554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create project and lot tables (lots and sub-lots in one self-referencing table)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lot (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL, estimate_days INTEGER DEFAULT NULL, project_id INTEGER NOT NULL, parent_id INTEGER DEFAULT NULL, owner_id INTEGER DEFAULT NULL, CONSTRAINT FK_B81291B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B727ACA70 FOREIGN KEY (parent_id) REFERENCES lot (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B81291B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B81291B166D1F9C ON lot (project_id)');
        $this->addSql('CREATE INDEX IDX_B81291B727ACA70 ON lot (parent_id)');
        $this->addSql('CREATE INDEX IDX_B81291B7E3C61F9 ON lot (owner_id)');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description CLOB DEFAULT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE lot');
        $this->addSql('DROP TABLE project');
    }
}
