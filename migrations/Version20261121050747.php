<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121050747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workshop sessions and their bookings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, cancelled_at DATETIME DEFAULT NULL, cancellation_reason VARCHAR(300) DEFAULT NULL, seats INTEGER NOT NULL, name VARCHAR(80) NOT NULL, email VARCHAR(180) NOT NULL, notes VARCHAR(500) DEFAULT NULL, session_id VARCHAR(32) NOT NULL, CONSTRAINT FK_E00CEDDE613FECDF FOREIGN KEY (session_id) REFERENCES workshop_session (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE613FECDF ON booking (session_id)');
        $this->addSql('CREATE TABLE workshop_session (id VARCHAR(32) NOT NULL, title VARCHAR(120) NOT NULL, capacity INTEGER NOT NULL, price_per_seat INTEGER NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE workshop_session');
    }
}
