<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507170558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE highline (id SERIAL NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(32) NOT NULL, length INT NOT NULL, height INT NOT NULL, latitude NUMERIC(10, 7) NOT NULL, longitude NUMERIC(10, 7) NOT NULL, country VARCHAR(100) DEFAULT NULL, area VARCHAR(150) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, rating INT DEFAULT NULL, anchoring VARCHAR(50) DEFAULT NULL, description TEXT DEFAULT NULL, point_one_info VARCHAR(512) DEFAULT NULL, point_two_info VARCHAR(512) DEFAULT NULL, first_ascent_by VARCHAR(150) DEFAULT NULL, first_ascent_date DATE DEFAULT NULL, name_history TEXT DEFAULT NULL, approach_minutes INT DEFAULT NULL, tensioning_minutes INT DEFAULT NULL, legacy_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_34E105D55E237E06 ON highline (name)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline.first_ascent_date IS '(DC2Type:date_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline
        SQL);
    }
}
