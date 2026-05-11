<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511103957 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highline_photo table (gallery PR2 MVP).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE highline_photo (id SERIAL NOT NULL, highline_id INT NOT NULL, uploaded_by_id INT DEFAULT NULL, filename VARCHAR(191) NOT NULL, caption VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_593D017947791474 ON highline_photo (highline_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_593D0179A2B28FE8 ON highline_photo (uploaded_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_highline_photo_highline_created ON highline_photo (highline_id, created_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_photo.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ADD CONSTRAINT FK_593D017947791474 FOREIGN KEY (highline_id) REFERENCES highline (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ADD CONSTRAINT FK_593D0179A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo DROP CONSTRAINT FK_593D017947791474
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo DROP CONSTRAINT FK_593D0179A2B28FE8
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline_photo
        SQL);
    }
}
