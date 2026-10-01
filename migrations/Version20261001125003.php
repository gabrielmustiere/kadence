<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261001125003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tag and user_tag tables, add user.manager_id';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tag (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category VARCHAR(20) NOT NULL, label VARCHAR(60) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_category_label ON tag (category, label)');
        $this->addSql('CREATE TABLE user_tag (user_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (user_id, tag_id), CONSTRAINT FK_E89FD608A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E89FD608BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E89FD608A76ED395 ON user_tag (user_id)');
        $this->addSql('CREATE INDEX IDX_E89FD608BAD26311 ON user_tag (tag_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, password, first_name, last_name, role, active, must_change_password, holiday_calendar FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, role VARCHAR(20) NOT NULL, active BOOLEAN NOT NULL, must_change_password BOOLEAN NOT NULL, holiday_calendar VARCHAR(2) DEFAULT \'fr\' NOT NULL, manager_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D93D649783E3463 FOREIGN KEY (manager_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO user (id, email, password, first_name, last_name, role, active, must_change_password, holiday_calendar) SELECT id, email, password, first_name, last_name, role, active, must_change_password, holiday_calendar FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649783E3463 ON user (manager_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE user_tag');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, first_name, last_name, role, holiday_calendar, active, must_change_password, password FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, role VARCHAR(20) NOT NULL, holiday_calendar VARCHAR(2) DEFAULT \'fr\' NOT NULL, active BOOLEAN NOT NULL, must_change_password BOOLEAN NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, email, first_name, last_name, role, holiday_calendar, active, must_change_password, password) SELECT id, email, first_name, last_name, role, holiday_calendar, active, must_change_password, password FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
    }
}
