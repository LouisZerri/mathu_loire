<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330085533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, method VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, type VARCHAR(20) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, reservation_id INT NOT NULL, INDEX IDX_6D28840DB83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE representation (id INT AUTO_INCREMENT NOT NULL, datetime DATETIME NOT NULL, status VARCHAR(20) NOT NULL, max_online_reservations INT NOT NULL, venue_capacity INT NOT NULL, adult_price NUMERIC(10, 2) NOT NULL, child_price NUMERIC(10, 2) NOT NULL, group_price NUMERIC(10, 2) DEFAULT NULL, show_id INT NOT NULL, INDEX IDX_29D5499ED0C1FC64 (show_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, nb_adults INT NOT NULL, nb_children INT NOT NULL, nb_invitations INT NOT NULL, is_pmr TINYINT NOT NULL, spectator_last_name VARCHAR(100) NOT NULL, spectator_first_name VARCHAR(100) NOT NULL, spectator_city VARCHAR(100) NOT NULL, spectator_phone VARCHAR(20) NOT NULL, spectator_email VARCHAR(180) NOT NULL, spectator_comment LONGTEXT DEFAULT NULL, admin_comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, representation_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_42C8495546CE82F4 (representation_id), INDEX IDX_42C84955B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seat (id INT AUTO_INCREMENT NOT NULL, `row` VARCHAR(10) NOT NULL, number INT NOT NULL, is_active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seat_assignment (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, seat_id INT NOT NULL, reservation_id INT DEFAULT NULL, representation_id INT NOT NULL, INDEX IDX_4ACEE08C1DAFE35 (seat_id), INDEX IDX_4ACEE08B83297E7 (reservation_id), INDEX IDX_4ACEE0846CE82F4 (representation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `show` (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE representation ADD CONSTRAINT FK_29D5499ED0C1FC64 FOREIGN KEY (show_id) REFERENCES `show` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495546CE82F4 FOREIGN KEY (representation_id) REFERENCES representation (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE seat_assignment ADD CONSTRAINT FK_4ACEE08C1DAFE35 FOREIGN KEY (seat_id) REFERENCES seat (id)');
        $this->addSql('ALTER TABLE seat_assignment ADD CONSTRAINT FK_4ACEE08B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE seat_assignment ADD CONSTRAINT FK_4ACEE0846CE82F4 FOREIGN KEY (representation_id) REFERENCES representation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DB83297E7');
        $this->addSql('ALTER TABLE representation DROP FOREIGN KEY FK_29D5499ED0C1FC64');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495546CE82F4');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955B03A8386');
        $this->addSql('ALTER TABLE seat_assignment DROP FOREIGN KEY FK_4ACEE08C1DAFE35');
        $this->addSql('ALTER TABLE seat_assignment DROP FOREIGN KEY FK_4ACEE08B83297E7');
        $this->addSql('ALTER TABLE seat_assignment DROP FOREIGN KEY FK_4ACEE0846CE82F4');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE representation');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE seat');
        $this->addSql('DROP TABLE seat_assignment');
        $this->addSql('DROP TABLE `show`');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
