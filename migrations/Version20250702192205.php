<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250702192205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE auth ADD CONSTRAINT FK_F8DEB059A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item DROP FOREIGN KEY FK_cart_item_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_cart_item_user_id ON cart_item
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment CHANGE status status VARCHAR(20) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment RENAME INDEX uniq_payment_session_id TO UNIQ_6D28840D613FECDF
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE auth DROP FOREIGN KEY FK_F8DEB059A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item ADD CONSTRAINT FK_cart_item_user FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_cart_item_user_id ON cart_item (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment CHANGE status status VARCHAR(20) DEFAULT 'pending' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment RENAME INDEX uniq_6d28840d613fecdf TO UNIQ_payment_session_id
        SQL);
    }
}
