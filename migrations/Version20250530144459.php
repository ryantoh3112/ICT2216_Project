<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250530144459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3CFFE9AD6
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, payment_id INT NOT NULL, INDEX IDX_27BA704BA76ED395 (user_id), UNIQUE INDEX UNIQ_27BA704B4C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history ADD CONSTRAINT FK_27BA704BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history ADD CONSTRAINT FK_27BA704B4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `order`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event CHANGE external_id event_organiser VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD total_price DOUBLE PRECISION NOT NULL, DROP transaction_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA3CFFE9AD6 ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD history_id INT NOT NULL, CHANGE orders_id payment_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA34C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA31E058452 FOREIGN KEY (history_id) REFERENCES history (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA34C3A3BB ON ticket (payment_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA31E058452 ON ticket (history_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE venue DROP external_venue_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA31E058452
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, total_price DOUBLE PRECISION NOT NULL, INDEX IDX_F5299398A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP FOREIGN KEY FK_27BA704BA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP FOREIGN KEY FK_27BA704B4C3A3BB
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE history
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE venue ADD external_venue_id VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA34C3A3BB
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA34C3A3BB ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_97A0ADA31E058452 ON ticket
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD orders_id INT NOT NULL, DROP payment_id, DROP history_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3CFFE9AD6 FOREIGN KEY (orders_id) REFERENCES `order` (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_97A0ADA3CFFE9AD6 ON ticket (orders_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD transaction_id VARCHAR(255) NOT NULL, DROP total_price
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event CHANGE event_organiser external_id VARCHAR(255) NOT NULL
        SQL);
    }
}
