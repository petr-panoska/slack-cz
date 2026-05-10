<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510175252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Highline.parkingLatitude/Longitude (legacy gps type=PARKING via highline.parking_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD parking_latitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline ADD parking_longitude NUMERIC(10, 7) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP parking_latitude
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE highline DROP parking_longitude
        SQL);
    }
}
