<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250702190614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // // this up() migration is auto-generated, please modify it to your needs
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE cart_item DROP INDEX user_id, ADD INDEX IDX_F0FE2527A76ED395 (user_id)
        // SQL);
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE cart_item DROP FOREIGN KEY FK_cart_item_user
        // SQL);
        // $this->addSql(<<<'SQL'
        //     DROP INDEX IDX_cart_item_user_id ON cart_item
        // SQL);
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE history ADD session_id VARCHAR(255) NOT NULL, ADD status VARCHAR(20) NOT NULL
        // SQL);
        // $this->addSql(<<<'SQL'
        //     CREATE UNIQUE INDEX UNIQ_27BA704B613FECDF ON history (session_id)
        // SQL);
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE payment CHANGE status status VARCHAR(20) NOT NULL
        // SQL);
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE payment RENAME INDEX uniq_payment_session_id TO UNIQ_6D28840D613FECDF
        // SQL);
        // $this->addSql(<<<'SQL'
        //     ALTER TABLE user DROP reset_token, DROP reset_token_expires_at
        // SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item ADD CONSTRAINT FK_cart_item_user FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_cart_item_user_id ON cart_item (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cart_item RENAME INDEX idx_f0fe2527a76ed395 TO user_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_27BA704B613FECDF ON history
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE history DROP session_id, DROP status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment CHANGE status status VARCHAR(20) DEFAULT 'pending' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment RENAME INDEX uniq_6d28840d613fecdf TO UNIQ_payment_session_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL
        SQL);
    }
}
