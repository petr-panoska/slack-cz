<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508093639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds slug + point1/point2 GPS to highline. Existing rows get a placeholder slug; re-run app:import:highlines --truncate to backfill real slugs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD slug VARCHAR(180)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD point1_latitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD point1_longitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD point2_latitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD point2_longitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE highline SET slug = CONCAT('highline-', id) WHERE slug IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ALTER COLUMN slug SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_34E105D5989D9B62 ON highline (slug)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_34E105D5989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP slug
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP point1_latitude
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP point1_longitude
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP point2_latitude
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP point2_longitude
        SQL);
    }
}
