<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613091812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create longline_crossing table (longline deník)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE longline_crossing (id SERIAL NOT NULL, user_id INT NOT NULL, crossed_at DATE NOT NULL, length INT NOT NULL, place VARCHAR(120) NOT NULL, style VARCHAR(20) DEFAULT NULL, comment TEXT DEFAULT NULL, legacy_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_13B69974A76ED395 ON longline_crossing (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_longline_crossed_at ON longline_crossing (crossed_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN longline_crossing.crossed_at IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN longline_crossing.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE longline_crossing ADD CONSTRAINT FK_13B69974A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE longline_crossing DROP CONSTRAINT FK_13B69974A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE longline_crossing
        SQL);
    }
}
