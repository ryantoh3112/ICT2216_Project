<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250609150213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE event_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE jwtblacklist (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, INDEX IDX_4C1567BFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ticket_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, price DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jwtblacklist ADD CONSTRAINT FK_4C1567BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD name VARCHAR(255) NOT NULL, ADD capacity INT NOT NULL, ADD purchase_start_date DATETIME NOT NULL, ADD purchase_end_date DATETIME NOT NULL, ADD organiser VARCHAR(255) NOT NULL, DROP event_name, DROP event_purchase_start_date, DROP event_purchase_end_date, DROP event_organiser, DROP event_category, CHANGE event_capacity category_id INT NOT NULL, CHANGE event_description description LONGTEXT DEFAULT NULL, CHANGE event_image image VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA712469DE2 FOREIGN KEY (category_id) REFERENCES event_category (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3BAE0AA712469DE2 ON event (category_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP INDEX UNIQ_27BA704B4C3A3BB, ADD INDEX IDX_27BA704B4C3A3BB (payment_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history ADD ticket_id INT DEFAULT NULL, ADD action VARCHAR(255) DEFAULT NULL, ADD timestamp DATETIME DEFAULT NULL, CHANGE payment_id payment_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history ADD CONSTRAINT FK_27BA704B700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_27BA704B700047D2 ON history (ticket_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA31E058452
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA3A76ED395 ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA31E058452 ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD ticket_type_id INT NOT NULL, DROP user_id, DROP history_id, DROP ticket_price, DROP ticket_type
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3C980D5C1 FOREIGN KEY (ticket_type_id) REFERENCES ticket_type (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA3C980D5C1 ON ticket (ticket_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD role VARCHAR(255) NOT NULL, ADD created_at DATETIME NOT NULL, ADD last_login_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD failed_login_count INT DEFAULT NULL, ADD account_status VARCHAR(255) DEFAULT NULL, ADD locked_at DATETIME DEFAULT NULL, CHANGE user_name name VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE venue CHANGE venue_image image VARCHAR(255) DEFAULT NULL, CHANGE venue_description description LONGTEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA712469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3C980D5C1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jwtblacklist DROP FOREIGN KEY FK_4C1567BFA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event_category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE jwtblacklist
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE ticket_type
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE venue CHANGE image venue_image VARCHAR(255) DEFAULT NULL, CHANGE description venue_description LONGTEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD user_name VARCHAR(255) NOT NULL, DROP name, DROP role, DROP created_at, DROP last_login_at, DROP updated_at, DROP failed_login_count, DROP account_status, DROP locked_at
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_3BAE0AA712469DE2 ON event
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD event_name VARCHAR(255) NOT NULL, ADD event_capacity INT NOT NULL, ADD event_purchase_start_date DATETIME NOT NULL, ADD event_purchase_end_date DATETIME NOT NULL, ADD event_organiser VARCHAR(255) NOT NULL, ADD event_category VARCHAR(255) NOT NULL, DROP category_id, DROP name, DROP capacity, DROP purchase_start_date, DROP purchase_end_date, DROP organiser, CHANGE description event_description LONGTEXT DEFAULT NULL, CHANGE image event_image VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA3C980D5C1 ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD history_id INT NOT NULL, ADD ticket_price DOUBLE PRECISION NOT NULL, ADD ticket_type VARCHAR(100) NOT NULL, CHANGE ticket_type_id user_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA31E058452 FOREIGN KEY (history_id) REFERENCES history (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA3A76ED395 ON ticket (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA31E058452 ON ticket (history_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP INDEX IDX_27BA704B4C3A3BB, ADD UNIQUE INDEX UNIQ_27BA704B4C3A3BB (payment_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP FOREIGN KEY FK_27BA704B700047D2
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_27BA704B700047D2 ON history
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP ticket_id, DROP action, DROP timestamp, CHANGE payment_id payment_id INT NOT NULL
        SQL);
    }
}
