<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the user.gender column. The field was already removed from the profile edit
 * form (UserForm) and is no longer imported from legacy data — see docs/todo.md
 * "Pohlaví / gender". Nothing in the app reads or writes it anymore, so the column
 * and its App\Enum\Gender enum are removed entirely.
 */
final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop user.gender column (field fully removed from app + import).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN gender');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD gender VARCHAR(1) DEFAULT NULL');
    }
}
