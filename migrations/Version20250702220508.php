<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250702220508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, payment_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, unit_price DOUBLE PRECISION NOT NULL, quantity INT NOT NULL, INDEX IDX_3C60BA32A76ED395 (user_id), INDEX IDX_3C60BA324C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE purchase_history ADD CONSTRAINT FK_3C60BA32A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE purchase_history ADD CONSTRAINT FK_3C60BA324C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE purchase_history DROP FOREIGN KEY FK_3C60BA32A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE purchase_history DROP FOREIGN KEY FK_3C60BA324C3A3BB
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE purchase_history
        SQL);
    }
}
