<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add highline_photo.gps_lat / gps_lng — capture GPS extracted from EXIF at upload
 * (App\Service\PhotoNormalizer), so it survives the WebP re-encode that strips metadata.
 */
final class Version20260616170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add highline_photo.gps_lat / gps_lng (capture GPS from EXIF)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ADD gps_lat NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ADD gps_lng NUMERIC(10, 7) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo DROP gps_lat
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo DROP gps_lng
        SQL);
    }
}
