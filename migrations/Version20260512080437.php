<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512080437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ticket_scan (id INT AUTO_INCREMENT NOT NULL, ticket_index INT NOT NULL, scanned_at DATETIME NOT NULL, reservation_id INT NOT NULL, scanned_by_id INT DEFAULT NULL, INDEX IDX_F668A696EBBC642F (scanned_by_id), INDEX idx_ticket_scan_reservation (reservation_id), UNIQUE INDEX uniq_ticket_scan (reservation_id, ticket_index), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ticket_scan ADD CONSTRAINT FK_F668A696B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ticket_scan ADD CONSTRAINT FK_F668A696EBBC642F FOREIGN KEY (scanned_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket_scan DROP FOREIGN KEY FK_F668A696B83297E7');
        $this->addSql('ALTER TABLE ticket_scan DROP FOREIGN KEY FK_F668A696EBBC642F');
        $this->addSql('DROP TABLE ticket_scan');
    }
}
