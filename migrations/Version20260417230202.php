<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417230202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cash_register (id INT AUTO_INCREMENT NOT NULL, opening_counts JSON NOT NULL, closing_counts JSON DEFAULT NULL, closing_cheques JSON DEFAULT NULL, closing_cb NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(20) NOT NULL, opened_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, representation_id INT NOT NULL, opened_by_id INT DEFAULT NULL, closed_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3D7AB1D946CE82F4 (representation_id), INDEX IDX_3D7AB1D9AB159F5 (opened_by_id), INDEX IDX_3D7AB1D9E1FA7797 (closed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D946CE82F4 FOREIGN KEY (representation_id) REFERENCES representation (id)');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D9AB159F5 FOREIGN KEY (opened_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D9E1FA7797 FOREIGN KEY (closed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D946CE82F4');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D9AB159F5');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D9E1FA7797');
        $this->addSql('DROP TABLE cash_register');
    }
}
