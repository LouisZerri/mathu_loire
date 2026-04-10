<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410232117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_reservation_last_name ON reservation (spectator_last_name)');
        $this->addSql('CREATE INDEX idx_reservation_email ON reservation (spectator_email)');
        $this->addSql('CREATE INDEX idx_reservation_status ON reservation (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_reservation_last_name ON reservation');
        $this->addSql('DROP INDEX idx_reservation_email ON reservation');
        $this->addSql('DROP INDEX idx_reservation_status ON reservation');
    }
}
