<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remap highline types after the enum change: "Top Highline" and "Urban Line" are
 * dropped and folded into "Highline". New types (Longline, Waterline) carry no legacy
 * rows, so nothing to migrate there.
 */
final class Version20260608120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fold legacy highline types top_highline/urban_line into highline.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE highline SET type = 'highline' WHERE type IN ('top_highline', 'urban_line')
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible: the original top_highline / urban_line distinction is lost.
        $this->throwIrreversibleMigrationException();
    }
}
