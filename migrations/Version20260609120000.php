<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the redundant highline.latitude / highline.longitude columns. A line is now
 * defined solely by its two anchor points (point1/point2) plus optional parking; the
 * single representative coordinate is derived from point1 at read time.
 *
 * These columns were a denormalized "midpoint" that drifted badly: of the lines with
 * both anchors, almost none matched the real midpoint (legacy import seeded them from
 * an old single GPS point and they were only recomputed when a line got re-saved).
 */
final class Version20260609120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop highline.latitude/longitude; lines are defined by point1/point2 only.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE highline DROP COLUMN latitude');
        $this->addSql('ALTER TABLE highline DROP COLUMN longitude');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE highline ADD COLUMN latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE highline ADD COLUMN longitude NUMERIC(10, 7) DEFAULT NULL');
        // Restore from the first anchor (every line has one) so NOT NULL can be re-applied.
        $this->addSql('UPDATE highline SET latitude = point1_latitude, longitude = point1_longitude');
        $this->addSql('ALTER TABLE highline ALTER COLUMN latitude SET NOT NULL');
        $this->addSql('ALTER TABLE highline ALTER COLUMN longitude SET NOT NULL');
    }
}
