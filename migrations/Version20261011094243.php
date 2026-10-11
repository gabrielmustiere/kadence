<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011094243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create time_off_request and time_off_entitlement tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE time_off_entitlement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(10) NOT NULL, year SMALLINT NOT NULL, half_days INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_FD3E8CB8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_time_off_entitlement ON time_off_entitlement (user_id, type, year)');
        $this->addSql('CREATE INDEX IDX_FD3E8CB8A76ED395 ON time_off_entitlement (user_id)');
        $this->addSql('CREATE TABLE time_off_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(10) NOT NULL, start_day DATE NOT NULL, start_half VARCHAR(10) NOT NULL, end_day DATE NOT NULL, end_half VARCHAR(10) NOT NULL, comment VARCHAR(500) DEFAULT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, decided_at DATETIME DEFAULT NULL, refusal_reason VARCHAR(500) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, decided_by_id INTEGER DEFAULT NULL, cancelled_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_AFE5EA69A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFE5EA69E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFE5EA69187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_time_off_user_status ON time_off_request (user_id, status)');
        $this->addSql('CREATE INDEX idx_time_off_status_end ON time_off_request (status, end_day)');
        $this->addSql('CREATE INDEX IDX_AFE5EA69A76ED395 ON time_off_request (user_id)');
        $this->addSql('CREATE INDEX IDX_AFE5EA69E26B496B ON time_off_request (decided_by_id)');
        $this->addSql('CREATE INDEX IDX_AFE5EA69187B2D12 ON time_off_request (cancelled_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE time_off_entitlement');
        $this->addSql('DROP TABLE time_off_request');
    }
}
