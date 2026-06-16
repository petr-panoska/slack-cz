<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add HighlinePhoto.legacy_id (maps gallery photos to legacy highline_foto.id) and
 * Highline.cover_photo_id (self-hosted cover, FK -> highline_photo, SET NULL on delete).
 * Feeds app:import:highline-photos.
 */
final class Version20260616152434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highline_photo.legacy_id + highline.cover_photo_id (legacy photo import)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ADD legacy_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD cover_photo_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD CONSTRAINT FK_34E105D5A69B8AD7 FOREIGN KEY (cover_photo_id) REFERENCES highline_photo (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_34E105D5A69B8AD7 ON highline (cover_photo_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP CONSTRAINT FK_34E105D5A69B8AD7
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_34E105D5A69B8AD7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP cover_photo_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo DROP legacy_id
        SQL);
    }
}
