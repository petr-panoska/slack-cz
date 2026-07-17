<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add required user-selected map emoji';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD emoji VARCHAR(8) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP emoji');
    }
}
