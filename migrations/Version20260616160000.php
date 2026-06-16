<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make highline_photo.created_at nullable. It is the user-facing photo date:
 * new uploads carry the upload time; legacy-imported photos carry the line's
 * first-tensioning date, or NULL when unknown (legacy had no per-photo date).
 */
final class Version20260616160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'highline_photo.created_at nullable (legacy photos may have unknown date)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ALTER created_at DROP NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE highline_photo SET created_at = now() WHERE created_at IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline_photo ALTER created_at SET NOT NULL
        SQL);
    }
}
