<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * line_photo.width/height — pixel size of the stored WebP master. The gallery layout
 * needs the aspect ratio before images load; new uploads fill it at normalize time,
 * existing rows via `app:photo:backfill-dimensions`.
 */
final class Version20260704133205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add line_photo.width/height (master pixel dimensions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE line_photo ADD width INT DEFAULT NULL');
        $this->addSql('ALTER TABLE line_photo ADD height INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE line_photo DROP width');
        $this->addSql('ALTER TABLE line_photo DROP height');
    }
}
