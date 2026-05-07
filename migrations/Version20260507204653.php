<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507204653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE highline_crossing (id SERIAL NOT NULL, highline_id INT NOT NULL, user_id INT NOT NULL, crossed_at DATE NOT NULL, style VARCHAR(20) DEFAULT NULL, rating INT DEFAULT NULL, comment TEXT DEFAULT NULL, legacy_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_13C974C647791474 ON highline_crossing (highline_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_13C974C6A76ED395 ON highline_crossing (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_crossing_crossed_at ON highline_crossing (crossed_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_crossing.crossed_at IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_crossing.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_crossing ADD CONSTRAINT FK_13C974C647791474 FOREIGN KEY (highline_id) REFERENCES highline (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_crossing ADD CONSTRAINT FK_13C974C6A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER is_active DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_crossing DROP CONSTRAINT FK_13C974C647791474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_crossing DROP CONSTRAINT FK_13C974C6A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline_crossing
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ALTER is_active SET DEFAULT true
        SQL);
    }
}
