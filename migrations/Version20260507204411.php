<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260507204411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD is_active BOOLEAN NOT NULL DEFAULT TRUE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD nick VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD first_name VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD last_name VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD city VARCHAR(50) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD birth_year INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD phone VARCHAR(30) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD gender VARCHAR(1) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD legacy_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD legacy_merged_ids JSON DEFAULT '[]' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD legacy_data_snapshot JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_IDENTIFIER_NICK ON "user" (nick)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_IDENTIFIER_NICK
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP is_active
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP nick
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP first_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP last_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP city
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP birth_year
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP phone
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP gender
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP legacy_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP legacy_merged_ids
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP legacy_data_snapshot
        SQL);
    }
}
