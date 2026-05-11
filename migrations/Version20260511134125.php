<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511134125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highline_photo_like + highline_photo_comment (gallery social layer).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE highline_photo_comment (id SERIAL NOT NULL, photo_id INT NOT NULL, author_id INT DEFAULT NULL, text TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_78B38BD77E9E4C8C ON highline_photo_comment (photo_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_78B38BD7F675F31B ON highline_photo_comment (author_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_highline_photo_comment_photo_created ON highline_photo_comment (photo_id, created_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_photo_comment.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE highline_photo_like (id SERIAL NOT NULL, photo_id INT NOT NULL, user_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FAF245AD7E9E4C8C ON highline_photo_like (photo_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_FAF245ADA76ED395 ON highline_photo_like (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_highline_photo_like_photo_user ON highline_photo_like (photo_id, user_id)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN highline_photo_like.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_comment ADD CONSTRAINT FK_78B38BD77E9E4C8C FOREIGN KEY (photo_id) REFERENCES highline_photo (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_comment ADD CONSTRAINT FK_78B38BD7F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_like ADD CONSTRAINT FK_FAF245AD7E9E4C8C FOREIGN KEY (photo_id) REFERENCES highline_photo (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_like ADD CONSTRAINT FK_FAF245ADA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_comment DROP CONSTRAINT FK_78B38BD77E9E4C8C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_comment DROP CONSTRAINT FK_78B38BD7F675F31B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_like DROP CONSTRAINT FK_FAF245AD7E9E4C8C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo_like DROP CONSTRAINT FK_FAF245ADA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline_photo_comment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE highline_photo_like
        SQL);
    }
}
