<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Final touch of the highline→line rename: the id columns are SERIAL (not IDENTITY),
 * so each renamed table still draws ids from a highline_*_id_seq sequence. Rename those
 * sequences to line_*_id_seq for naming consistency. ALTER SEQUENCE ... RENAME is safe:
 * the column DEFAULT references the sequence by OID, so nextval() keeps working.
 */
final class Version20260621140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename highline_*_id_seq sequences to line_*_id_seq';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER SEQUENCE highline_id_seq RENAME TO line_id_seq');
        $this->addSql('ALTER SEQUENCE highline_crossing_id_seq RENAME TO line_crossing_id_seq');
        $this->addSql('ALTER SEQUENCE highline_edit_id_seq RENAME TO line_edit_id_seq');
        $this->addSql('ALTER SEQUENCE highline_photo_id_seq RENAME TO line_photo_id_seq');
        $this->addSql('ALTER SEQUENCE highline_photo_comment_id_seq RENAME TO line_photo_comment_id_seq');
        $this->addSql('ALTER SEQUENCE highline_photo_like_id_seq RENAME TO line_photo_like_id_seq');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER SEQUENCE line_photo_like_id_seq RENAME TO highline_photo_like_id_seq');
        $this->addSql('ALTER SEQUENCE line_photo_comment_id_seq RENAME TO highline_photo_comment_id_seq');
        $this->addSql('ALTER SEQUENCE line_photo_id_seq RENAME TO highline_photo_id_seq');
        $this->addSql('ALTER SEQUENCE line_edit_id_seq RENAME TO highline_edit_id_seq');
        $this->addSql('ALTER SEQUENCE line_crossing_id_seq RENAME TO highline_crossing_id_seq');
        $this->addSql('ALTER SEQUENCE line_id_seq RENAME TO highline_id_seq');
    }
}
