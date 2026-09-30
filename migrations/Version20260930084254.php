<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930084254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create holiday_adjustment table, add user.holiday_calendar (defaults to fr)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE holiday_adjustment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, calendar VARCHAR(2) NOT NULL, day DATE NOT NULL, type VARCHAR(10) NOT NULL, label VARCHAR(100) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_holiday_adjustment_calendar_day ON holiday_adjustment (calendar, day)');
        $this->addSql('ALTER TABLE user ADD COLUMN holiday_calendar VARCHAR(2) DEFAULT \'fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE holiday_adjustment');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, first_name, last_name, role, active, must_change_password, password FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, role VARCHAR(20) NOT NULL, active BOOLEAN NOT NULL, must_change_password BOOLEAN NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, email, first_name, last_name, role, active, must_change_password, password) SELECT id, email, first_name, last_name, role, active, must_change_password, password FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
    }
}
